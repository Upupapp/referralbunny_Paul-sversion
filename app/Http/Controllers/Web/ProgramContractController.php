<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\MemberActionItem;
use App\Models\PartnerProgramMembership;
use App\Models\Program;
use App\Models\ProgramContract;
use App\Models\ProgramOfferVersion;
use App\Models\ReferrerProgramMembership;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Manages contract proposal and status transitions for the Programs V4
 * workspace "Contracts" tab.
 *
 * Authorization: ProgramPolicy::managePeople() (manage_programs permission).
 * Contract/membership lookups are always scoped to BOTH tenant_id and
 * program_id from the route — never trust a bare id from the client.
 *
 * Admin-workspace only: there is no member-facing portal acceptance flow,
 * so transitions to "active" are recorded with acceptance_method='api'
 * (system/admin-driven), not a member's literal click.
 */
class ProgramContractController extends Controller
{
    private const ALLOWED_TRANSITIONS = [
        'proposed' => ['active', 'declined'],
        'active'   => ['ended', 'superseded'],
    ];

    private const TRANSITION_TIMESTAMPS = [
        'active'   => 'accepted_at',
        'declined' => 'declined_at',
        'ended'    => 'ended_at',
    ];

    public function propose(Request $request, string $tenantId, string $programId)
    {
        abort_unless(config('programs.enabled'), 404);

        $program = Program::forTenant($tenantId)->findOrFail($programId);
        $this->authorize('managePeople', $program);

        $data = $request->validate([
            'membership_type'  => ['required', 'in:referrer,partner'],
            'membership_id'    => ['required', 'string'],
            // 'exists' alone only checks the row is present anywhere — it does NOT
            // confirm the offer version belongs to this program. Scope it below.
            'offer_version_id' => ['nullable', 'string', 'exists:program_offer_versions,id'],
        ]);

        if (!empty($data['offer_version_id'])) {
            abort_unless(
                ProgramOfferVersion::where('id', $data['offer_version_id'])
                    ->where('program_id', $program->id)
                    ->exists(),
                404
            );
        }

        $membership = $this->resolveMembership($program, $data['membership_type'], $data['membership_id']);

        abort_if(
            !in_array($membership->status, ['active', 'approved'], true),
            422,
            'This member must be active or approved before a contract can be proposed.'
        );

        $duplicate = ProgramContract::where('tenant_id', $tenantId)
            ->where('program_id', $program->id)
            ->where('membership_type', $data['membership_type'])
            ->where('membership_id', $data['membership_id'])
            ->whereIn('status', ['proposed', 'active'])
            ->exists();

        abort_if($duplicate, 422, 'A proposed or active contract already exists for this member.');

        DB::transaction(function () use ($data, $tenantId, $program) {
            $contract = ProgramContract::create([
                'tenant_id'        => $tenantId,
                'program_id'       => $program->id,
                'membership_type'  => $data['membership_type'],
                'membership_id'    => $data['membership_id'],
                'offer_version_id' => $data['offer_version_id'] ?? null,
                'status'           => 'proposed',
                'proposed_at'      => now(),
            ]);

            MemberActionItem::create([
                'tenant_id'       => $tenantId,
                'program_id'      => $program->id,
                'membership_type' => $data['membership_type'],
                'membership_id'   => $data['membership_id'],
                'action_type'     => 'review_contract',
                'target_type'     => 'contract',
                'target_id'       => $contract->id,
                'status'          => 'pending',
            ]);
        });

        return back()->with('success', 'Contract proposed.');
    }

    public function transition(Request $request, string $tenantId, string $programId, string $contractId)
    {
        abort_unless(config('programs.enabled'), 404);

        $program = Program::forTenant($tenantId)->findOrFail($programId);
        $this->authorize('managePeople', $program);

        $contract = ProgramContract::where('tenant_id', $tenantId)
            ->where('program_id', $program->id)
            ->findOrFail($contractId);

        $data = $request->validate([
            'status' => ['required', 'string'],
        ]);

        $allowed = self::ALLOWED_TRANSITIONS[$contract->status] ?? [];

        if (!in_array($data['status'], $allowed, true)) {
            return back()->withErrors([
                'status' => "Cannot move a contract from \"{$contract->status}\" to \"{$data['status']}\".",
            ]);
        }

        DB::transaction(function () use ($contract, $data, $program) {
            $update = ['status' => $data['status']];
            if ($timestampColumn = self::TRANSITION_TIMESTAMPS[$data['status']] ?? null) {
                $update[$timestampColumn] = now();
            }
            if ($data['status'] === 'active') {
                $update['acceptance_method'] = 'api';
            }
            $contract->update($update);

            $membership = $this->resolveMembership($program, $contract->membership_type, $contract->membership_id);

            if ($data['status'] === 'active') {
                $membership->update(['active_contract_id' => $contract->id]);

                ProgramContract::where('tenant_id', $contract->tenant_id)
                    ->where('program_id', $program->id)
                    ->where('membership_type', $contract->membership_type)
                    ->where('membership_id', $contract->membership_id)
                    ->where('status', 'active')
                    ->where('id', '!=', $contract->id)
                    ->update(['status' => 'superseded']);
            }

            if (in_array($data['status'], ['ended', 'superseded'], true) && $membership->active_contract_id === $contract->id) {
                $membership->update(['active_contract_id' => null]);
            }

            if (in_array($data['status'], ['active', 'declined'], true)) {
                MemberActionItem::where('tenant_id', $contract->tenant_id)
                    ->where('target_type', 'contract')
                    ->where('target_id', $contract->id)
                    ->where('status', 'pending')
                    ->update(['status' => 'completed', 'completed_at' => now()]);
            }
        });

        return back()->with('success', 'Contract status updated.');
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
