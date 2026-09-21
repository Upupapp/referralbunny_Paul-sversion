<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\PartnerProgramMembership;
use App\Models\Program;
use App\Models\ReferrerProgramMembership;
use Illuminate\Http\Request;

/**
 * Manages referrer/partner membership attachment and status transitions for
 * the Programs V4 workspace "Members" tab.
 *
 * Authorization: ProgramPolicy::managePeople() (manage_programs permission).
 * Membership lookups are always scoped to BOTH tenant_id and program_id from
 * the route — never trust a bare membership id from the client.
 */
class ProgramMembershipController extends Controller
{
    /**
     * Legal status transitions, keyed by current status.
     * Public so the workspace view can render only the transition buttons
     * the server will actually accept, instead of hand-duplicating this map.
     */
    public const ALLOWED_TRANSITIONS = [
        'invited'   => ['approved', 'removed'],
        'applied'   => ['approved', 'removed'],
        'approved'  => ['active', 'suspended', 'removed'],
        'active'    => ['paused', 'suspended', 'removed'],
        'paused'    => ['active', 'suspended', 'removed'],
        'suspended' => ['active', 'removed'],
    ];

    private const TRANSITION_TIMESTAMPS = [
        'approved'  => 'approved_at',
        'active'    => 'activated_at',
        'paused'    => 'paused_at',
        'suspended' => 'suspended_at',
        'removed'   => 'removed_at',
    ];

    public function inviteReferrer(Request $request, string $tenantId, string $programId)
    {
        abort_unless(config('programs.enabled'),404);
        abort_if(\App\Support\ProtectedTenants::isProtected($tenantId),404);
        $program=Program::forTenant($tenantId)->findOrFail($programId);
        $this->authorize('managePeople',$program);
        $data=$request->validate(['name'=>'required|string|max:150','email'=>'required|email|max:254','phone'=>'nullable|string|max:50','territory'=>'nullable|string|max:150','message'=>'nullable|string|max:1000']);
        try { app(\App\Services\Programs\ProgramReferrerInvite::class)->send($program,$data['name'],$data['email'],$data); }
        catch (\Illuminate\Validation\ValidationException $e) { throw $e; }
        catch (\Throwable $e) { report($e); if($request->expectsJson())return response()->json(['message'=>'Email could not be sent. Please retry.'],503); return back()->withErrors(['email'=>'The email could not be sent. Please retry; an existing membership will be reused.']); }
        if($request->expectsJson())return response()->json(['message'=>'Invitation sent.']);
        return back()->with('success','Program invite sent to '.$data['email'].'.');
    }

    public function attachReferrer(Request $request, string $tenantId, string $programId)
    {
        abort_unless(config('programs.enabled'), 404);

        $program = Program::forTenant($tenantId)->findOrFail($programId);
        $this->authorize('managePeople', $program);

        $data = $request->validate([
            'reseller_id' => ['required', 'string', 'exists:resellers,id'],
        ]);

        // (program_id, reseller_id) has a flat DB-level unique constraint — at most
        // one membership row can ever exist per program+reseller, regardless of
        // status. So a previously removed/expired membership must be revived in
        // place rather than inserted fresh, or the INSERT would violate the
        // constraint.
        $membership = ReferrerProgramMembership::where('tenant_id', $program->tenant_id)
            ->forProgram($program->id)
            ->forReseller($data['reseller_id'])
            ->first();

        abort_if(
            $membership && !in_array($membership->status, ['removed', 'expired'], true),
            422,
            'This referrer is already a member of this program.'
        );

        $attributes = [
            'status'           => 'active',
            'source'           => 'direct',
            'joined_at'        => now(),
            'activated_at'     => now(),
            'last_activity_at' => now(),
        ];

        if ($membership) {
            $membership->update($attributes);
        } else {
            ReferrerProgramMembership::create([
                'tenant_id'   => $tenantId,
                'program_id'  => $program->id,
                'reseller_id' => $data['reseller_id'],
                ...$attributes,
            ]);
        }

        return back()->with('success', 'Referrer added to program.');
    }

    public function attachPartner(Request $request, string $tenantId, string $programId)
    {
        abort_unless(config('programs.enabled'), 404);

        $program = Program::forTenant($tenantId)->findOrFail($programId);
        $this->authorize('managePeople', $program);

        $data = $request->validate([
            'partner_id' => ['required', 'string', 'exists:partner_users,id'],
        ]);

        // Same flat (program_id, partner_id) unique constraint as the referrer
        // side — revive a removed/expired row in place rather than inserting.
        $membership = PartnerProgramMembership::where('tenant_id', $program->tenant_id)
            ->forProgram($program->id)
            ->forPartner($data['partner_id'])
            ->first();

        abort_if(
            $membership && !in_array($membership->status, ['removed', 'expired'], true),
            422,
            'This partner is already a member of this program.'
        );

        $attributes = [
            'status'           => 'active',
            'source'           => 'direct',
            'joined_at'        => now(),
            'activated_at'     => now(),
            'last_activity_at' => now(),
        ];

        if ($membership) {
            $membership->update($attributes);
        } else {
            PartnerProgramMembership::create([
                'tenant_id'  => $tenantId,
                'program_id' => $program->id,
                'partner_id' => $data['partner_id'],
                ...$attributes,
            ]);
        }

        return back()->with('success', 'Partner added to program.');
    }

    public function transitionReferrer(Request $request, string $tenantId, string $programId, string $membershipId)
    {
        abort_unless(config('programs.enabled'), 404);

        $program    = Program::forTenant($tenantId)->findOrFail($programId);
        $this->authorize('managePeople', $program);

        $membership = ReferrerProgramMembership::where('tenant_id', $tenantId)
            ->where('program_id', $program->id)
            ->findOrFail($membershipId);

        return $this->transition($request, $membership);
    }

    public function transitionPartner(Request $request, string $tenantId, string $programId, string $membershipId)
    {
        abort_unless(config('programs.enabled'), 404);

        $program    = Program::forTenant($tenantId)->findOrFail($programId);
        $this->authorize('managePeople', $program);

        $membership = PartnerProgramMembership::where('tenant_id', $tenantId)
            ->where('program_id', $program->id)
            ->findOrFail($membershipId);

        return $this->transition($request, $membership);
    }

    private function transition(Request $request, ReferrerProgramMembership|PartnerProgramMembership $membership)
    {
        $data = $request->validate([
            'status' => ['required', 'string'],
        ]);

        $allowed = self::ALLOWED_TRANSITIONS[$membership->status] ?? [];

        if (!in_array($data['status'], $allowed, true)) {
            return back()->withErrors([
                'status' => "Cannot move a member from \"{$membership->status}\" to \"{$data['status']}\".",
            ]);
        }

        $update = [
            'status'           => $data['status'],
            'last_activity_at' => now(),
        ];

        if ($timestampColumn = self::TRANSITION_TIMESTAMPS[$data['status']] ?? null) {
            $update[$timestampColumn] = now();
        }

        $membership->update($update);

        return back()->with('success', 'Member status updated.');
    }
}
