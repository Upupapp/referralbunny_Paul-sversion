<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Program;
use App\Models\RequestForm;
use App\Models\RequestFormField;
use App\Models\RequestFormRecipientOption;
use App\Models\RequestFormSubmission;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Services\TenantContext;
use App\Support\ProtectedTenants;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RequestFormController extends Controller
{
    // ── List ─────────────────────────────────────────────────────────────────

    public function index(Request $request, string $tenantId): \Illuminate\View\View
    {
        $this->authorizeAdmin($tenantId);
        $tenant = Tenant::findOrFail($tenantId);

        $search = $request->query('q', '');
        $status = $request->query('status', 'all');
        $sort   = $request->query('sort', 'updated');

        $sortMap = [
            'updated'   => ['updated_at', 'desc'],
            'created'   => ['created_at', 'desc'],
            'title'     => ['title', 'asc'],
            'responses' => ['submissions_count', 'desc'],
            'last'      => ['submissions_max_submitted_at', 'desc'],
        ];
        [$sortCol, $sortDir] = $sortMap[$sort] ?? ['updated_at', 'desc'];

        $forms = RequestForm::where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->withCount('submissions')
            ->withMax('submissions', 'submitted_at')
            ->when($search, fn($q) => $q->where(fn($inner) => $inner
                ->where('title', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%")
            ))
            ->when($status && $status !== 'all', fn($q) => $q->where('status', $status))
            ->orderBy($sortCol, $sortDir)
            ->paginate(20)
            ->withQueryString();

        return view('tenant.request-forms.index', compact(
            'tenant', 'forms', 'search', 'status', 'sort'
        ));
    }

    // ── Create / Store ────────────────────────────────────────────────────────

    public function create(Request $request, string $tenantId): \Illuminate\View\View
    {
        $this->authorizeAdmin($tenantId);
        $tenant = Tenant::findOrFail($tenantId);

        $programId = $request->query('program_id');
        if ($programId) {
            // Never trust the query param blindly — confirm it belongs to this
            // tenant and isn't archived. Resolved before eligibleRecipients()
            // so a bad id fails fast. Protected tenants (lgu-ids) must never
            // get a program_id resolved here either, even though Programs V4
            // itself is already blocked for them by ProgramPolicy -- this is
            // the same choke point applied to this side door.
            abort_if(ProtectedTenants::isProtected($tenantId), 404);
            $programId = Program::forTenant($tenantId)->where('status', '!=', 'archived')->findOrFail($programId)->id;
        }

        $teamMembers = $this->eligibleRecipients($tenantId);

        return view('tenant.request-forms.create', compact('tenant', 'teamMembers', 'programId'));
    }

    public function store(Request $request, string $tenantId): \Illuminate\Http\RedirectResponse
    {
        $this->authorizeAdmin($tenantId);

        $data = $request->validate([
            'title'                     => 'required|string|max:120',
            'description'               => 'nullable|string|max:500',
            'success_message'           => 'nullable|string|max:300',
            'program_id'                => 'nullable|string',
            'allow_multiple_recipients' => 'boolean',
            'max_recipients'            => 'integer|min:1|max:10',
            'fields'                    => 'required|array|min:1',
            'fields.*.label'            => 'required|string|max:120',
            'fields.*.field_key'        => 'required|string|max:60',
            'fields.*.field_type'       => 'required|in:text,email,textarea,select,multi_select,checkbox,radio,number,date,hidden',
            'fields.*.placeholder'      => 'nullable|string|max:120',
            'fields.*.helper_text'      => 'nullable|string|max:300',
            'fields.*.options'          => 'nullable',
            'fields.*.is_required'      => 'boolean',
            'recipients'                => 'nullable|array',
            'recipients.*.recipient_id' => 'nullable|string',
            'recipients.*.display_name' => 'required_with:recipients|string|max:120',
            'recipients.*.email'        => 'required_with:recipients|email',
            'recipients.*.role_snapshot'=> 'nullable|string',
        ]);

        [$actorType, $actorId] = $this->resolveActor();

        // 'nullable|string' alone doesn't confirm tenant ownership — never trust
        // a client-supplied program_id without re-deriving it from this tenant.
        // Archived programs are excluded -- a program that's done shouldn't
        // accept new intake forms.
        // Same protected-tenant choke point as create() -- a side door into
        // Program-linked data must not exist just because this controller
        // predates Programs V4's ProgramPolicy guardrail.
        abort_if(!empty($data['program_id']) && ProtectedTenants::isProtected($tenantId), 404);

        $programId = null;
        if (!empty($data['program_id'])) {
            $programId = Program::forTenant($tenantId)->where('status', '!=', 'archived')->findOrFail($data['program_id'])->id;
        }

        try { $form = DB::transaction(function () use ($data, $tenantId, $programId, $actorType, $actorId, $request) {
            $form = RequestForm::create([
                'tenant_id'                 => $tenantId,
                'program_id'                => $programId,
                'created_by_type'           => $actorType,
                'created_by_id'             => $actorId,
                'title'                     => $data['title'],
                'description'               => $data['description'] ?? null,
                'success_message'           => $data['success_message'] ?? 'Your request has been submitted successfully.',
                'allow_multiple_recipients' => $data['allow_multiple_recipients'] ?? true,
                'max_recipients'            => $data['max_recipients'] ?? 5,
                'status'                    => 'published',
                'published_at'              => now(),
            ]);

            // System field: Deadline — always present and required on every form
            RequestFormField::create([
                'tenant_id'       => $tenantId,
                'request_form_id' => $form->id,
                'label'           => 'Deadline',
                'field_key'       => 'deadline',
                'field_type'      => 'date',
                'placeholder'     => null,
                'helper_text'     => 'When do you need this completed?',
                'options'         => null,
                'is_required'     => true,
                'sort_order'      => 0,
                'is_system_field' => true,
            ]);

            foreach (($data['fields'] ?? []) as $idx => $field) {
                $options = null;
                if (!empty($field['options'])) {
                    if (is_string($field['options'])) {
                        $options = array_values(array_filter(
                            array_map('trim', explode("\n", $field['options']))
                        ));
                    } elseif (is_array($field['options'])) {
                        $options = $field['options'];
                    }
                }

                RequestFormField::create([
                    'tenant_id'       => $tenantId,
                    'request_form_id' => $form->id,
                    'label'           => $field['label'],
                    'field_key'       => $field['field_key'],
                    'field_type'      => $field['field_type'],
                    'placeholder'     => $field['placeholder'] ?? null,
                    'helper_text'     => $field['helper_text'] ?? null,
                    'options'         => $options,
                    'is_required'     => (bool) ($field['is_required'] ?? false),
                    'sort_order'      => $idx + 1, // +1 because deadline takes sort_order 0
                ]);
            }

            $seenStoreEmails = [];
            foreach ($request->input('recipient_data', []) as $recipientId => $rec) {
                if (empty($rec['email'])) continue;
                $normalEmail = strtolower(trim($rec['email']));
                if (in_array($normalEmail, $seenStoreEmails, true)) continue; // dedup
                $isTenantMember = DB::table('tenant_memberships')
                    ->where('tenant_id', $tenantId)
                    ->where('tenant_user_id', $recipientId)
                    ->where('status', 'active')
                    ->exists();
                if (!$isTenantMember && !empty($rec['recipient_id'])) continue;
                $seenStoreEmails[] = $normalEmail;
                RequestFormRecipientOption::create([
                    'tenant_id'       => $tenantId,
                    'request_form_id' => $form->id,
                    'recipient_type'  => 'tenant_user',
                    'recipient_id'    => $rec['recipient_id'] ?? $recipientId,
                    'display_name'    => $rec['display_name'] ?? $rec['email'],
                    'email'           => $normalEmail,
                    'role_snapshot'   => $rec['role_snapshot'] ?? null,
                    'sort_order'      => 0,
                ]);
            }

            return $form;
        }); } catch (\Throwable $e) {
            \Log::error('RequestForm::store failed: ' . $e->getMessage(), [
                'file' => $e->getFile(), 'line' => $e->getLine(),
            ]);
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 500);
            }
            throw $e;
        }

        // AJAX callers (create page fetch) get JSON back
        if ($request->expectsJson()) {
            return response()->json([
                'id'         => $form->id,
                'title'      => $form->title,
                'public_url' => $form->publicUrl(),
                'edit_url'   => route('tenant.request-forms.edit', [$tenantId, $form->id]),
                'list_url'   => route('tenant.request-forms', $tenantId),
            ], 201);
        }

        return redirect()->route('tenant.request-forms.edit', [$tenantId, $form->id])
            ->with('form_created', true)
            ->with('success', 'Form created and saved as draft.');
    }

    // ── Edit / Update ─────────────────────────────────────────────────────────

    public function edit(string $tenantId, string $formId): \Illuminate\View\View
    {
        $this->authorizeAdmin($tenantId);
        $tenant      = Tenant::findOrFail($tenantId);
        $form        = RequestForm::where('tenant_id', $tenantId)->findOrFail($formId);
        $teamMembers = $this->eligibleRecipients($tenantId);

        $form->load(['fields', 'recipientOptions']);

        return view('tenant.request-forms.edit', compact('tenant', 'form', 'teamMembers'));
    }

    public function update(Request $request, string $tenantId, string $formId): \Illuminate\Http\RedirectResponse
    {
        $this->authorizeAdmin($tenantId);
        $form = RequestForm::where('tenant_id', $tenantId)->findOrFail($formId);

        $data = $request->validate([
            'title'           => 'required|string|max:120',
            'description'     => 'nullable|string|max:500',
            'success_message' => 'nullable|string|max:300',
        ]);

        DB::transaction(function () use ($data, $form, $tenantId, $request) {
            $form->update([
                'title'           => $data['title'],
                'description'     => $data['description'] ?? null,
                'success_message' => $data['success_message'] ?? null,
            ]);

            // Sync recipient options (delete all, recreate from form data).
            // Deduplicate by email — prevent same recipient being added twice.
            RequestFormRecipientOption::where('request_form_id', $form->id)->delete();
            $seenEmails = [];
            foreach ($request->input('recipient_data', []) as $recipientId => $rec) {
                if (empty($rec['email'])) continue;
                $normalEmail = strtolower(trim($rec['email']));
                if (in_array($normalEmail, $seenEmails, true)) continue; // skip duplicate
                $seenEmails[] = $normalEmail;
                RequestFormRecipientOption::create([
                    'tenant_id'       => $tenantId,
                    'request_form_id' => $form->id,
                    'recipient_type'  => 'tenant_user',
                    'recipient_id'    => $rec['recipient_id'] ?? $recipientId,
                    'display_name'    => $rec['display_name'] ?? $rec['email'],
                    'email'           => $normalEmail,
                    'role_snapshot'   => $rec['role_snapshot'] ?? null,
                    'sort_order'      => 0,
                ]);
            }
        });

        return redirect()->route('tenant.request-forms', $tenantId)->with('success', 'Form updated.');
    }

    // ── Field Management (AJAX) ──────────────────────────────────────────────

    public function addField(Request $request, string $tenantId, string $formId): \Illuminate\Http\JsonResponse
    {
        $this->authorizeAdmin($tenantId);
        $form = RequestForm::where('tenant_id', $tenantId)->findOrFail($formId);

        $data = $request->validate([
            'label'       => 'required|string|max:120',
            'field_type'  => 'required|in:text,email,textarea,select,multi_select,checkbox,radio,number,date',
            'placeholder' => 'nullable|string|max:120',
            'helper_text' => 'nullable|string|max:300',
            'options'     => 'nullable|string',  // newline-separated
            'is_required' => 'boolean',
        ]);

        $maxSort = RequestFormField::where('request_form_id', $form->id)->max('sort_order') ?? -1;

        $options = null;
        if (!empty($data['options'])) {
            $options = array_values(array_filter(array_map('trim', explode("\n", $data['options']))));
        }

        $field = RequestFormField::create([
            'tenant_id'       => $tenantId,
            'request_form_id' => $form->id,
            'label'           => $data['label'],
            'field_key'       => \Illuminate\Support\Str::slug($data['label']) . '_' . \Illuminate\Support\Str::random(4),
            'field_type'      => $data['field_type'],
            'placeholder'     => $data['placeholder'] ?? null,
            'helper_text'     => $data['helper_text'] ?? null,
            'options'         => $options,
            'is_required'     => (bool) ($data['is_required'] ?? false),
            'sort_order'      => $maxSort + 1,
        ]);

        return response()->json(['field' => $this->formatField($field)], 201);
    }

    public function updateField(Request $request, string $tenantId, string $formId, string $fieldId): \Illuminate\Http\JsonResponse
    {
        $this->authorizeAdmin($tenantId);
        $form  = RequestForm::where('tenant_id', $tenantId)->findOrFail($formId);
        $field = RequestFormField::where('request_form_id', $form->id)->findOrFail($fieldId);

        $data = $request->validate([
            'label'       => 'sometimes|required|string|max:120',
            'placeholder' => 'nullable|string|max:120',
            'helper_text' => 'nullable|string|max:300',
            'options'     => 'nullable|string',
            'is_required' => 'boolean',
        ]);

        $update = array_filter([
            'label'       => $data['label'] ?? null,
            'placeholder' => $data['placeholder'] ?? null,
            'helper_text' => $data['helper_text'] ?? null,
            'is_required' => isset($data['is_required']) ? (bool) $data['is_required'] : null,
        ], fn($v) => $v !== null);

        if (isset($data['options'])) {
            $update['options'] = array_values(array_filter(array_map('trim', explode("\n", $data['options'] ?? ''))));
        }

        $field->update($update);
        return response()->json(['field' => $this->formatField($field->fresh())]);
    }

    public function destroyField(string $tenantId, string $formId, string $fieldId): \Illuminate\Http\JsonResponse
    {
        $this->authorizeAdmin($tenantId);
        $form  = RequestForm::where('tenant_id', $tenantId)->findOrFail($formId);
        $field = RequestFormField::where('request_form_id', $form->id)->findOrFail($fieldId);

        if ($field->is_system_field) {
            return response()->json(['error' => 'The Deadline field is required on all forms and cannot be removed.'], 422);
        }

        $field->delete();
        return response()->json(['deleted' => true]);
    }

    public function reorderFields(Request $request, string $tenantId, string $formId): \Illuminate\Http\JsonResponse
    {
        $this->authorizeAdmin($tenantId);
        $form = RequestForm::where('tenant_id', $tenantId)->findOrFail($formId);

        $order = $request->validate(['ids' => 'required|array', 'ids.*' => 'required|string'])['ids'];

        \Illuminate\Support\Facades\DB::transaction(function () use ($order, $form) {
            foreach ($order as $idx => $fieldId) {
                RequestFormField::where('request_form_id', $form->id)->where('id', $fieldId)
                    ->update(['sort_order' => $idx]);
            }
        });

        return response()->json(['reordered' => true]);
    }

    private function formatField(RequestFormField $f): array
    {
        $typeLabel = match($f->field_type) {
            'multi_select' => 'Multi-select', default => ucfirst(str_replace('_', ' ', $f->field_type))
        };
        return [
            'id'              => $f->id,
            'label'           => $f->label,
            'field_key'       => $f->field_key,
            'field_type'      => $f->field_type,
            'type_label'      => $typeLabel,
            'placeholder'     => $f->placeholder,
            'helper_text'     => $f->helper_text,
            'options'         => $f->options ?? [],
            'is_required'     => (bool) $f->is_required,
            'is_system_field' => (bool) $f->is_system_field,
            'sort_order'      => $f->sort_order,
        ];
    }

    // ── Publish / Unpublish ───────────────────────────────────────────────────

    public function publish(string $tenantId, string $formId): \Illuminate\Http\JsonResponse
    {
        $this->authorizeAdmin($tenantId);
        $form = RequestForm::where('tenant_id', $tenantId)->findOrFail($formId);
        $form->update(['status' => 'published', 'published_at' => now()]);
        return response()->json(['status' => 'published', 'public_url' => $form->publicUrl()]);
    }

    public function unpublish(string $tenantId, string $formId): \Illuminate\Http\JsonResponse
    {
        $this->authorizeAdmin($tenantId);
        $form = RequestForm::where('tenant_id', $tenantId)->findOrFail($formId);
        $form->update(['status' => 'unpublished']);
        return response()->json(['status' => 'unpublished']);
    }

    // ── Duplicate ─────────────────────────────────────────────────────────────

    public function duplicate(string $tenantId, string $formId): \Illuminate\Http\RedirectResponse
    {
        $this->authorizeAdmin($tenantId);
        $original = RequestForm::where('tenant_id', $tenantId)
            ->with(['fields', 'recipientOptions'])
            ->findOrFail($formId);

        [$actorType, $actorId] = $this->resolveActor();

        $newForm = DB::transaction(function () use ($original, $tenantId, $actorType, $actorId) {
            $copy = RequestForm::create([
                'tenant_id'                 => $tenantId,
                'created_by_type'           => $actorType,
                'created_by_id'             => $actorId,
                'title'                     => 'Copy of ' . $original->title,
                'description'               => $original->description,
                'success_message'           => $original->success_message,
                'allow_multiple_recipients' => $original->allow_multiple_recipients,
                'max_recipients'            => $original->max_recipients,
                'status'                    => 'draft',
            ]);

            foreach ($original->fields as $field) {
                RequestFormField::create([
                    'tenant_id'       => $tenantId,
                    'request_form_id' => $copy->id,
                    'label'           => $field->label,
                    'field_key'       => $field->field_key,
                    'field_type'      => $field->field_type,
                    'placeholder'     => $field->placeholder,
                    'helper_text'     => $field->helper_text,
                    'options'         => $field->options,
                    'is_required'     => $field->is_required,
                    'sort_order'      => $field->sort_order,
                ]);
            }

            foreach ($original->recipientOptions as $opt) {
                RequestFormRecipientOption::create([
                    'tenant_id'       => $tenantId,
                    'request_form_id' => $copy->id,
                    'recipient_type'  => $opt->recipient_type,
                    'recipient_id'    => $opt->recipient_id,
                    'display_name'    => $opt->display_name,
                    'email'           => $opt->email,
                    'role_snapshot'   => $opt->role_snapshot,
                    'sort_order'      => $opt->sort_order,
                ]);
            }

            return $copy;
        });

        return redirect()->route('tenant.request-forms.edit', [$tenantId, $newForm->id])
            ->with('success', 'Form duplicated. Edit and publish when ready.');
    }

    // ── Submissions list ──────────────────────────────────────────────────────

    public function submissions(Request $request, string $tenantId, string $formId): \Illuminate\View\View
    {
        $this->authorizeAdmin($tenantId);
        $tenant = Tenant::findOrFail($tenantId);
        $form   = RequestForm::where('tenant_id', $tenantId)->findOrFail($formId);

        $search    = $request->query('q', '');
        $dateFrom  = $request->query('date_from', '');
        $dateTo    = $request->query('date_to', '');

        $submissionsError = null;

        try {
            // Build base query scoped to this form and tenant.
            $submissionsQuery = RequestFormSubmission::where('request_form_id', $formId)
                ->where('tenant_id', $tenantId)
                ->with(['submissionRecipients']);

            // Use a raw subquery with explicit CAST to avoid PostgreSQL's
            // "operator does not exist: uuid = character varying" error.
            // tasks.source_id is varchar; request_form_submissions.id is uuid.
            static $hasTasks = null;
            $hasTasks ??= \Illuminate\Support\Facades\Schema::hasTable('tasks')
                       && \Illuminate\Support\Facades\Schema::hasColumn('tasks', 'source_type');

            if ($hasTasks) {
                $submissionsQuery->selectRaw(
                    '"request_form_submissions".*, ' .
                    // Total tasks linked to this submission
                    '(SELECT COUNT(*) FROM "tasks" ' .
                    ' WHERE "tasks"."source_id" = CAST("request_form_submissions"."id" AS TEXT) ' .
                    ' AND "tasks"."source_type" = ? ' .
                    ' AND "tasks"."deleted_at" IS NULL' .
                    ') AS "tasks_count", ' .
                    // Completed tasks
                    '(SELECT COUNT(*) FROM "tasks" ' .
                    ' WHERE "tasks"."source_id" = CAST("request_form_submissions"."id" AS TEXT) ' .
                    ' AND "tasks"."source_type" = ? ' .
                    ' AND "tasks"."status" = \'completed\' ' .
                    ' AND "tasks"."deleted_at" IS NULL' .
                    ') AS "completed_tasks_count", ' .
                    // Status of the most recent linked task (for in-progress / cancelled detection)
                    '(SELECT "tasks"."status" FROM "tasks" ' .
                    ' WHERE "tasks"."source_id" = CAST("request_form_submissions"."id" AS TEXT) ' .
                    ' AND "tasks"."source_type" = ? ' .
                    ' AND "tasks"."deleted_at" IS NULL ' .
                    ' ORDER BY "tasks"."created_at" DESC LIMIT 1' .
                    ') AS "latest_task_status"',
                    ['request_form_submission', 'request_form_submission', 'request_form_submission']
                );
            }

            $submissions = $submissionsQuery
                ->when($search, fn($q) => $q->where(fn($inner) => $inner
                    ->where('submitter_name', 'like', "%{$search}%")
                    ->orWhere('submitter_email', 'like', "%{$search}%")
                    ->orWhere('request_for', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%")
                ))
                ->when($dateFrom, fn($q) => $q->where('submitted_at', '>=', $dateFrom))
                ->when($dateTo,   fn($q) => $q->where('submitted_at', '<=', $dateTo . ' 23:59:59'))
                ->orderByDesc('submitted_at')
                ->paginate(25)
                ->withQueryString();
        } catch (\Throwable $e) {
            \Log::error('submissions() query failed', [
                'form_id'   => $formId,
                'tenant_id' => $tenantId,
                'error'     => $e->getMessage(),
                'file'      => $e->getFile(),
                'line'      => $e->getLine(),
            ]);
            $submissionsError = 'Could not load responses: ' . $e->getMessage();
            $submissions = new \Illuminate\Pagination\LengthAwarePaginator([], 0, 25);
        }

        return view('tenant.request-forms.submissions', compact(
            'tenant', 'form', 'submissions', 'search', 'dateFrom', 'dateTo', 'submissionsError'
        ));
    }

    // ── Submission Detail ─────────────────────────────────────────────────────

    public function submissionShow(string $tenantId, string $formId, string $submissionId): \Illuminate\View\View
    {
        $this->authorizeAdmin($tenantId);
        $tenant     = Tenant::findOrFail($tenantId);
        $form       = RequestForm::where('tenant_id', $tenantId)->with('fields')->findOrFail($formId);
        $submission = RequestFormSubmission::where('tenant_id', $tenantId)
            ->where('request_form_id', $formId)
            ->with(['submissionRecipients', 'tasks.activities', 'tasks.completionResponses'])
            ->findOrFail($submissionId);

        return view('tenant.request-forms.submission-show', compact(
            'tenant', 'form', 'submission'
        ));
    }

    // ── Bulk Destroy ──────────────────────────────────────────────────────────

    public function bulkDestroy(Request $request, string $tenantId): \Illuminate\Http\RedirectResponse
    {
        $this->authorizeAdmin($tenantId);
        $ids = array_filter((array) $request->input('form_ids', []));

        if (empty($ids)) {
            return redirect()->route('tenant.request-forms', $tenantId)
                ->with('error', 'No forms selected.');
        }

        // Explicit tenant_id scope prevents IDOR — only forms owned by this tenant are deleted
        $forms = RequestForm::where('tenant_id', $tenantId)->whereIn('id', $ids)->get();

        DB::transaction(function () use ($forms) {
            foreach ($forms as $form) {
                $submissionIds = RequestFormSubmission::where('request_form_id', $form->id)->pluck('id');
                if ($submissionIds->isNotEmpty()) {
                    \App\Models\RequestFormSubmissionRecipient::whereIn('request_form_submission_id', $submissionIds)->delete();
                }
                RequestFormSubmission::where('request_form_id', $form->id)->delete();
                RequestFormField::where('request_form_id', $form->id)->delete();
                \App\Models\RequestFormRecipientOption::where('request_form_id', $form->id)->delete();
                $form->forceDelete();
            }
        });

        $count = $forms->count();
        return redirect()->route('tenant.request-forms', $tenantId)
            ->with('success', "Permanently deleted {$count} " . \Illuminate\Support\Str::plural('form', $count) . '.');
    }

    // ── Destroy Submission ────────────────────────────────────────────────────

    public function destroySubmission(string $tenantId, string $formId, string $submissionId): \Illuminate\Http\RedirectResponse
    {
        $this->authorizeAdmin($tenantId);
        RequestForm::where('tenant_id', $tenantId)->findOrFail($formId);
        $submission = RequestFormSubmission::where('request_form_id', $formId)
            ->where('tenant_id', $tenantId)
            ->findOrFail($submissionId);

        DB::transaction(function () use ($submission) {
            \App\Models\RequestFormSubmissionRecipient::where('request_form_submission_id', $submission->id)->delete();
            $submission->delete();
        });

        return redirect()->route('tenant.request-forms.submissions', [$tenantId, $formId])
            ->with('success', 'Response deleted.');
    }

    // ── Destroy ───────────────────────────────────────────────────────────────

    public function destroy(string $tenantId, string $formId): \Illuminate\Http\RedirectResponse
    {
        $this->authorizeAdmin($tenantId);
        $form = RequestForm::where('tenant_id', $tenantId)->findOrFail($formId);

        DB::transaction(function () use ($form) {
            // Delete submission recipients before submissions
            $submissionIds = RequestFormSubmission::where('request_form_id', $form->id)
                ->pluck('id');
            if ($submissionIds->isNotEmpty()) {
                \App\Models\RequestFormSubmissionRecipient::whereIn('request_form_submission_id', $submissionIds)->delete();
            }
            RequestFormSubmission::where('request_form_id', $form->id)->delete();

            RequestFormField::where('request_form_id', $form->id)->delete();
            \App\Models\RequestFormRecipientOption::where('request_form_id', $form->id)->delete();

            $form->forceDelete();
        });

        return redirect()->route('tenant.request-forms', $tenantId)->with('success', 'Form deleted permanently.');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function authorizeAdmin(string $tenantId): void
    {
        $ctxId = TenantContext::id();
        abort_unless(!$ctxId || $ctxId === $tenantId, 403);
        if (!Auth::guard('tenant')->check() && !Auth::guard('web')->check()) abort(403);

        if (Auth::guard('tenant')->check()) {
            $role = \App\Models\TenantMembership::where('tenant_user_id', Auth::guard('tenant')->id())
                ->where('tenant_id', $tenantId)
                ->where('status', 'active')
                ->value('role');
            if (!in_array($role, ['owner', 'admin', 'manager'])) {
                abort(403, 'Only Owners, Admins, and Managers can manage request forms.');
            }
        }
    }

    private function resolveActor(): array
    {
        if (Auth::guard('tenant')->check()) {
            $u = Auth::guard('tenant')->user();
            return ['tenant_user', (string) $u->id];
        }
        if (Auth::guard('web')->check()) {
            $u = Auth::guard('web')->user();
            return ['admin_user', (string) $u->id];
        }
        return ['system', 'system'];
    }

    private function eligibleRecipients(string $tenantId): \Illuminate\Support\Collection
    {
        // Name built in PHP rather than via SQL CONCAT() -- CONCAT() is
        // Postgres/MySQL syntax, not supported by sqlite (used in tests),
        // and this is otherwise behaviorally identical.
        return DB::table('tenant_users as tu')
            ->join('tenant_memberships as tm', 'tm.tenant_user_id', '=', 'tu.id')
            ->where('tm.tenant_id', $tenantId)
            ->where('tm.status', 'active')
            ->whereIn('tm.role', ['owner', 'admin', 'manager'])
            ->select('tu.id', 'tu.first_name', 'tu.last_name', 'tu.email', 'tm.role')
            ->orderBy('tm.role')
            ->orderBy('tu.first_name')
            ->get()
            ->map(function ($row) {
                $row->name = trim(($row->first_name ?? '') . ' ' . ($row->last_name ?? ''));
                return $row;
            });
    }
}
