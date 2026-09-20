<?php
namespace App\Http\Controllers;

use App\Models\{Program, ProgramConnection, ProgramOfferVersion, ReferrerProgramMembership, Tenant};
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ProgramConversionController extends Controller
{
    public function store(Request $request, string $connectionId)
    {
        abort_if(strlen($request->getContent()) > 16384, 413);
        $connection = ProgramConnection::findOrFail($connectionId);
        $timestamp = $request->header('X-RB-Timestamp', '');
        abort_unless(ctype_digit($timestamp) && abs(time() - (int) $timestamp) <= 300, 401);
        $signature = hash_hmac('sha256', $timestamp.'.'.$request->getContent(), $connection->secret);
        abort_unless(hash_equals($signature, $request->header('X-RB-Signature', '')), 401);
        abort_unless(Tenant::whereKey($connection->tenant_id)->where('status', 'active')->exists(), 403);
        abort_if(\App\Support\ProtectedTenants::isProtected($connection->tenant_id), 403);
        $data = $request->validate([
            'event_id' => 'required|string|max:120', 'type' => 'required|in:test,payment,refund',
            'customer_id' => 'required_unless:type,test|string|max:120',
            'invoice_id' => 'required_unless:type,test|string|max:120',
            'membership_id' => 'required_if:type,payment|string|max:100',
            'currency' => ['required_unless:type,test', Rule::in(['PHP','USD','EUR','GBP','AUD','SGD','CAD'])],
            'amount_minor' => 'required_unless:type,test|integer|min:1|max:10000000000',
            'occurred_at' => 'required_unless:type,test|date|before_or_equal:'.now()->addMinutes(5)->toIso8601String(),
            'referred_at' => 'required_if:type,payment|date',
            'signed_up_at' => 'nullable|date',
            'first_payment' => 'required_if:type,payment|boolean',
            'self_referral' => 'exclude_unless:type,payment|required|declined',
        ]);
        return DB::transaction(function () use ($connection, $data, $request) {
            $connection = ProgramConnection::whereKey($connection->id)->lockForUpdate()->firstOrFail();
            $program = Program::forTenant($connection->tenant_id)->findOrFail($connection->program_id);
            $events = fn () => DB::table('program_conversion_events')->where('connection_id', $connection->id);
            $hash = hash('sha256', $request->getContent());
            if ($existing = $events()->where('external_id', $data['event_id'])->first()) {
                abort_unless(hash_equals($existing->payload_hash, $hash), 409, 'Event ID already used with a different payload.');
                return response()->json(['received' => true, 'duplicate' => true]);
            }
            if ($data['type'] === 'test') {
                if ($connection->status !== 'connected') $connection->update(['status' => 'tested']);
                return response()->json(['received' => true, 'mode' => 'test', 'message' => 'Signature verified. Send a real payment to activate tracking.']);
            }
            $invoice = $events()->where('invoice_id', $data['invoice_id'])->where('type', 'payment')->first();
            $occurred = Carbon::parse($data['occurred_at'])->utc();
            if ($data['type'] === 'refund') {
                abort_unless($invoice && $invoice->customer_id === $data['customer_id'] && $invoice->currency === $data['currency'], 409, 'Send the original payment before its refund.');
                $refunds = $events()->where('invoice_id', $data['invoice_id'])->where('type', 'refund');
                $alreadyRefunded = (int) (clone $refunds)->sum('amount_minor');
                abort_if($alreadyRefunded + $data['amount_minor'] > $invoice->amount_minor, 422, 'Refund exceeds the collected payment.');
                $alreadyReversed = -(int) (clone $refunds)->sum('reward_minor');
                $targetReversed = (int) round($invoice->reward_minor * ($alreadyRefunded + $data['amount_minor']) / $invoice->amount_minor);
                $reward = -($targetReversed - $alreadyReversed);
                $referrerId = $invoice->referrer_id;
                $versionId = $invoice->offer_version_id;
                $available = null;
                $status = 'reversal';
            } else {
                abort_unless($program->status === 'active', 409, 'Program is not active.');
                abort_if($invoice, 409, 'Invoice already recorded. Retry with the original event ID.');
                $member = ReferrerProgramMembership::where('tenant_id', $connection->tenant_id)
                    ->where('program_id', $program->id)->where('status', 'active')->findOrFail($data['membership_id']);
                abort_unless(\App\Models\Reseller::whereKey($member->reseller_id)->where('tenant_id', $connection->tenant_id)->whereIn('status', ['active', 'nda_signed'])->exists(), 403);
                $first = $events()->where('customer_id', $data['customer_id'])->where('type', 'payment')->orderBy('occurred_at')->first();
                if ($first) {
                    abort_if($data['first_payment'] || $first->referrer_id !== $member->reseller_id || $first->currency !== $data['currency'], 422, 'Customer attribution cannot be changed.');
                    abort_if($occurred->lt(Carbon::parse($first->occurred_at)), 409, 'Payments must arrive in order.');
                    $version = ProgramOfferVersion::findOrFail($first->offer_version_id);
                } else {
                    abort_unless($data['first_payment'], 422, 'Only newly referred paying customers qualify.');
                    $referred = Carbon::parse($data['referred_at'])->utc();
                    if ($connection->platform === 'gethired') {
                        abort_unless(!empty($data['signed_up_at']), 422, 'Verified signup timestamp is required.');
                        $signup = Carbon::parse($data['signed_up_at'])->utc();
                        abort_if($signup->lt($referred) || $signup->gt($referred->copy()->addDays($program->attribution_window_days ?? 30)), 422, 'Signup is outside the referral window.');
                        abort_if($occurred->lt($signup) || $occurred->gt($signup->copy()->addDays(30)), 422, 'First payment must be within 30 days after signup.');
                    } else {
                        abort_if($referred->gt($occurred) || $referred->lt($occurred->copy()->subDays($program->attribution_window_days ?? 30)), 422, 'Referral is outside the attribution window.');
                    }
                    $version = $program->offers()->where('status', 'active')->firstOrFail()->currentVersion;
                }
                abort_unless($version && $version->currency === $data['currency'], 422, 'Currency does not match the program.');
                $rules = $version->reward_rules;
                $eligible = !$first || (($rules['scope'] ?? '') === 'recurring'
                    && $occurred->lt(Carbon::parse($first->occurred_at)->addMonthsNoOverflow((int) $rules['duration_months'])));
                $reward = !$eligible ? 0 : ($version->reward_model === 'percentage'
                    ? (int) round($data['amount_minor'] * (float) $version->percentage_rate / 100)
                    : (int) round((float) $version->fixed_amount * 100));
                $referrerId = $member->reseller_id;
                $versionId = $version->id;
                $available = $occurred->copy()->addDays((int) ($rules['hold_days'] ?? 30));
                $status = $eligible ? 'pending_review' : 'not_eligible';
            }
            $events()->insert([
                'id' => (string) Str::uuid(), 'connection_id' => $connection->id,
                'external_id' => $data['event_id'], 'payload_hash' => $hash,
                'customer_id' => $data['customer_id'], 'invoice_id' => $data['invoice_id'],
                'referrer_id' => $referrerId, 'offer_version_id' => $versionId,
                'type' => $data['type'], 'currency' => $data['currency'],
                'amount_minor' => $data['amount_minor'], 'reward_minor' => $reward,
                'status' => $status, 'occurred_at' => $occurred, 'available_at' => $available,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $connection->update(['status' => 'connected', 'last_event_at' => now()]);
            return response()->json(['received' => true, 'reward_minor' => $reward, 'status' => $status], 201);
        });
    }
}
