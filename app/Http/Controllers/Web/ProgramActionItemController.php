<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\MemberActionItem;
use App\Models\PartnerProgramMembership;
use App\Models\Program;
use App\Models\ReferrerProgramMembership;
use Illuminate\Http\Request;

/**
 * Manages ad-hoc action item creation and resolution for the Programs V4
 * workspace "Action Items" tab.
 *
 * Authorization: ProgramPolicy::managePeople() (manage_programs permission).
 * Lookups are always scoped to BOTH tenant_id and program_id from the
 * route — never trust a bare id from the client.
 */
class ProgramActionItemController extends Controller
{
    private const ACTION_TYPES = [
        'accept_terms', 'review_contract', 'upload_document',
        'complete_profile', 'resolve_application', 'acknowledge_program_ending',
    ];

    public function store(Request $request, string $tenantId, string $programId)
    {
        abort_unless(config('programs.enabled'), 404);

        $program = Program::forTenant($tenantId)->findOrFail($programId);
        $this->authorize('managePeople', $program);

        $data = $request->validate([
            'membership_type' => ['required', 'in:referrer,partner'],
            'membership_id'   => ['required', 'string'],
            'action_type'     => ['required', 'in:' . implode(',', self::ACTION_TYPES)],
            'due_at'          => ['nullable', 'date'],
        ]);

        $this->resolveMembership($program, $data['membership_type'], $data['membership_id']);

        MemberActionItem::create([
            'tenant_id'           => $tenantId,
            'program_id'          => $program->id,
            'membership_type'     => $data['membership_type'],
            'membership_id'       => $data['membership_id'],
            'action_type'         => $data['action_type'],
            'status'              => 'pending',
            'due_at'              => $data['due_at'] ?? null,
            'notification_status' => 'not_sent',
        ]);

        return back()->with('success', 'Action item created.');
    }

    public function transition(Request $request, string $tenantId, string $programId, string $actionItemId)
    {
        abort_unless(config('programs.enabled'), 404);

        $program = Program::forTenant($tenantId)->findOrFail($programId);
        $this->authorize('managePeople', $program);

        $item = MemberActionItem::where('tenant_id', $tenantId)
            ->where('program_id', $program->id)
            ->findOrFail($actionItemId);

        $data = $request->validate([
            'status' => ['required', 'in:completed,dismissed'],
        ]);

        abort_if($item->status !== 'pending', 422, 'Only pending action items can be resolved.');

        $item->update([
            'status'       => $data['status'],
            'completed_at' => $data['status'] === 'completed' ? now() : null,
        ]);

        return back()->with('success', 'Action item updated.');
    }

    private function resolveMembership(Program $program, string $type, string $id): ReferrerProgramMembership|PartnerProgramMembership
    {
        if ($type === 'referrer') {
            return ReferrerProgramMembership::where('tenant_id', $program->tenant_id)
                ->where('program_id', $program->id)
                ->findOrFail($id);
        }

        return PartnerProgramMembership::where('tenant_id', $program->tenant_id)
            ->where('program_id', $program->id)
            ->findOrFail($id);
    }
}
