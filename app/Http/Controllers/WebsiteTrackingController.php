<?php

namespace App\Http\Controllers;

use App\Models\{Program, ProgramConnection, ReferrerProgramMembership, Reseller, Tenant};
use App\Services\QuickProgram\TrackingOrigins;
use App\Support\ProtectedTenants;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class WebsiteTrackingController extends Controller
{
    public function visit(Request $request, string $connectionId, TrackingOrigins $origins)
    {
        abort_unless(config('programs.enabled'), 404);
        abort_if(strlen($request->getContent()) > 2048, 413);
        $connection = ProgramConnection::findOrFail($connectionId);
        abort_if(ProtectedTenants::isProtected($connection->tenant_id), 403);
        $origin = $request->header('Origin', '');
        abort_unless($origins->accepts($connection, $origin), 403);
        abort_unless(Tenant::whereKey($connection->tenant_id)->where('status', 'active')->exists(), 403);
        $program = Program::forTenant($connection->tenant_id)->whereIn('status', ['active', 'paused'])->findOrFail($connection->program_id);

        if ($request->isMethod('OPTIONS')) {
            return response('', 204)->withHeaders([
                'Access-Control-Allow-Origin' => $origin,
                'Access-Control-Allow-Methods' => 'POST, OPTIONS',
                'Access-Control-Allow-Headers' => 'Content-Type',
                'Access-Control-Max-Age' => '600', 'Vary' => 'Origin',
            ]);
        }

        // text/plain JSON lets the tiny browser beacon avoid unnecessary preflights.
        $input = json_decode($request->getContent(), true);
        abort_unless(is_array($input), 422);
        $data = Validator::make($input, [
            'program_id' => 'required|string|max:100',
            'membership_id' => 'nullable|string|max:100|regex:/^[a-zA-Z0-9_-]+$/',
        ])->validate();
        abort_unless($data['program_id'] === $program->id, 422);

        $now = now();
        // Installation telemetry is domain-level only: no visitor IDs, emails, URL
        // paths, cookies or payment data. Coalesce timestamp writes to reduce load.
        ProgramConnection::whereKey($connection->id)->where(function ($query) use ($now, $origin) {
            $query->whereNull('snippet_installed_at')->orWhereNull('snippet_last_seen_at')
                ->orWhere('snippet_last_seen_at', '<', $now->copy()->subMinutes(5))
                ->orWhere('snippet_origin', '!=', $origin);
        })->update([
            'snippet_installed_at' => $connection->snippet_installed_at ?? $now,
            'snippet_last_seen_at' => $now, 'snippet_origin' => $origin,
        ]);

        $referral = null;
        if (!empty($data['membership_id']) && $program->status === 'active') {
            $member = ReferrerProgramMembership::where('tenant_id', $connection->tenant_id)
                ->where('program_id', $program->id)->where('status', 'active')
                ->find($data['membership_id']);
            if ($member && Reseller::whereKey($member->reseller_id)->where('tenant_id', $connection->tenant_id)
                ->whereIn('status', ['active', 'nda_signed'])->exists()) {
                $referral = ['membership_id' => $member->id];
            }
        }

        return response()->json([
            'program_id' => $program->id,
            'attribution_window_days' => min(365, max(1, $program->attribution_window_days ?? 30)),
            'allowed_origins' => $origins->forConnection($connection),
            'referral' => $referral,
        ])->withHeaders([
            'Access-Control-Allow-Origin' => $origin,
            'Vary' => 'Origin', 'Cache-Control' => 'no-store, private',
        ]);
    }
}
