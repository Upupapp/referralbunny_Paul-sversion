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

    public function index(string $tenantId): \Illuminate\View\View
    {
        $this->authorizeAdmin($tenantId);
        $tenant = Tenant::findOrFail($tenantId);

        $forms = RequestForm::where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->withCount('submissions')
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('tenant.request-forms.index', compact('tenant', 'forms'));
    }

    // ── Create / Store ────────────────────────────────────────────────────────

    public function create(string $tenantId): \Illuminate\View\View
    {
        $this->authorizeAdmin($tenantId);
        $tenant     = Tenant::findOrFail($tenantId);
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

        DB::transaction(function () use ($data, $tenantId, $actorType, $actorId) {
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
                // Options come as newline-separated text from the form builder textarea
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

            // Recipients are posted as recipient_data[{uuid}][field] from form checkboxes
            foreach ($request->input('recipient_data', []) as $recipientId => $rec) {
                if (empty($rec['email'])) continue;
                // Verify this recipient belongs to this tenant
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

        return redirect()->route('tenant.request-forms', $tenantId)
            ->with('success', 'Request form created. Publish it when ready.');
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

    // ── Update ───────────────────────────────────────────────────────────────

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

            // Sync recipient options
            $form->recipientOptions()->delete();
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

    // ── Submissions list ──────────────────────────────────────────────────────

    public function submissions(string $tenantId, string $formId): \Illuminate\View\View
    {
        $this->authorizeAdmin($tenantId);
        $tenant = Tenant::findOrFail($tenantId);
        $form   = RequestForm::where('tenant_id', $tenantId)->findOrFail($formId);

        $submissions = RequestFormSubmission::where('tenant_id', $tenantId)
            ->where('request_form_id', $formId)
            ->with('submissionRecipients')
            ->orderByDesc('submitted_at')
            ->paginate(25);

        return view('tenant.request-forms.submissions', compact('tenant', 'form', 'submissions'));
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
