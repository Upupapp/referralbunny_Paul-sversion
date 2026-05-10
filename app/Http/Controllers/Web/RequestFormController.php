<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\RequestForm;
use App\Models\RequestFormField;
use App\Models\RequestFormRecipientOption;
use App\Models\RequestFormSubmission;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Services\TenantContext;
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

    public function create(string $tenantId): \Illuminate\View\View
    {
        $this->authorizeAdmin($tenantId);
        $tenant      = Tenant::findOrFail($tenantId);
        $teamMembers = $this->eligibleRecipients($tenantId);

        return view('tenant.request-forms.create', compact('tenant', 'teamMembers'));
    }

    public function store(Request $request, string $tenantId): \Illuminate\Http\RedirectResponse
    {
        $this->authorizeAdmin($tenantId);

        $data = $request->validate([
            'title'                     => 'required|string|max:120',
            'description'               => 'nullable|string|max:500',
            'success_message'           => 'nullable|string|max:300',
            'allow_multiple_recipients' => 'boolean',
            'max_recipients'            => 'integer|min:1|max:10',
            'fields'                    => 'required|array|min:1',
            'fields.*.label'            => 'required|string|max:120',
            'fields.*.field_key'        => 'required|string|max:60',
            'fields.*.field_type'       => 'required|in:text,email,textarea,select,multi_select,checkbox,radio,number,date,hidden',
            'fields.*.placeholder'      => 'nullable|string|max:120',
            'fields.*.helper_text'      => 'nullable|string|max:300',
            'fields.*.options'          => 'nullable|array',
            'fields.*.is_required'      => 'boolean',
            'recipients'                => 'nullable|array',
            'recipients.*.recipient_id' => 'nullable|string',
            'recipients.*.display_name' => 'required_with:recipients|string|max:120',
            'recipients.*.email'        => 'required_with:recipients|email',
            'recipients.*.role_snapshot'=> 'nullable|string',
        ]);

        [$actorType, $actorId] = $this->resolveActor();

        $form = DB::transaction(function () use ($data, $tenantId, $actorType, $actorId, $request) {
            $form = RequestForm::create([
                'tenant_id'                 => $tenantId,
                'created_by_type'           => $actorType,
                'created_by_id'             => $actorId,
                'title'                     => $data['title'],
                'description'               => $data['description'] ?? null,
                'success_message'           => $data['success_message'] ?? 'Your request has been submitted successfully.',
                'allow_multiple_recipients' => $data['allow_multiple_recipients'] ?? true,
                'max_recipients'            => $data['max_recipients'] ?? 5,
                'status'                    => 'draft',
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
                    'sort_order'      => $idx,
                ]);
            }

            foreach ($request->input('recipient_data', []) as $recipientId => $rec) {
                if (empty($rec['email'])) continue;
                $isTenantMember = DB::table('tenant_memberships')
                    ->where('tenant_id', $tenantId)
                    ->where('tenant_user_id', $recipientId)
                    ->where('status', 'active')
                    ->exists();
                if (!$isTenantMember && !empty($rec['recipient_id'])) continue;

                RequestFormRecipientOption::create([
                    'tenant_id'       => $tenantId,
                    'request_form_id' => $form->id,
                    'recipient_type'  => 'tenant_user',
                    'recipient_id'    => $rec['recipient_id'] ?? $recipientId,
                    'display_name'    => $rec['display_name'] ?? $rec['email'],
                    'email'           => $rec['email'],
                    'role_snapshot'   => $rec['role_snapshot'] ?? null,
                    'sort_order'      => 0,
                ]);
            }

            return $form;
        });

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

            // Sync recipient options (delete all, recreate from form data)
            RequestFormRecipientOption::where('request_form_id', $form->id)->delete();
            foreach ($request->input('recipient_data', []) as $recipientId => $rec) {
                if (empty($rec['email'])) continue;
                RequestFormRecipientOption::create([
                    'tenant_id'       => $tenantId,
                    'request_form_id' => $form->id,
                    'recipient_type'  => 'tenant_user',
                    'recipient_id'    => $rec['recipient_id'] ?? $recipientId,
                    'display_name'    => $rec['display_name'] ?? $rec['email'],
                    'email'           => $rec['email'],
                    'role_snapshot'   => $rec['role_snapshot'] ?? null,
                    'sort_order'      => 0,
                ]);
            }
        });

        return redirect()->route('tenant.request-forms', $tenantId)->with('success', 'Form updated.');
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

        $submissions = RequestFormSubmission::where('tenant_id', $tenantId)
            ->where('request_form_id', $formId)
            ->with(['submissionRecipients'])
            ->withCount(['tasks' => fn($q) => $q->whereNull('deleted_at')])
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

        return view('tenant.request-forms.submissions', compact(
            'tenant', 'form', 'submissions', 'search', 'dateFrom', 'dateTo'
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

    // ── Destroy ───────────────────────────────────────────────────────────────

    public function destroy(string $tenantId, string $formId): \Illuminate\Http\RedirectResponse
    {
        $this->authorizeAdmin($tenantId);
        $form = RequestForm::where('tenant_id', $tenantId)->findOrFail($formId);
        $form->delete();
        return redirect()->route('tenant.request-forms', $tenantId)->with('success', 'Form archived.');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function authorizeAdmin(string $tenantId): void
    {
        $ctxId = TenantContext::id();
        if ($ctxId && $ctxId !== $tenantId) abort(403);
        if (!Auth::guard('tenant')->check() && !Auth::guard('web')->check()) abort(403);
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
        return DB::table('tenant_users as tu')
            ->join('tenant_memberships as tm', 'tm.tenant_user_id', '=', 'tu.id')
            ->where('tm.tenant_id', $tenantId)
            ->where('tm.status', 'active')
            ->whereIn('tm.role', ['owner', 'admin', 'manager'])
            ->selectRaw("tu.id, TRIM(CONCAT(COALESCE(tu.first_name,''), ' ', COALESCE(tu.last_name,''))) as name, tu.email, tm.role")
            ->orderBy('tm.role')
            ->orderBy('tu.first_name')
            ->get();
    }
}
