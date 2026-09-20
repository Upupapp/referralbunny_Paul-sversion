<?php
namespace App\Services\Programs;

use App\Models\ReferrerProgramMembership;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Crypt, DB};
use Illuminate\Support\Str;

class ReferralClickTracking
{
    public const COOKIE = 'rb_link_visitor';

    public function excluded(Request $request, ReferrerProgramMembership $member): bool
    {
        if ($request->isMethod('HEAD') || $request->boolean('preview')) return true;
        if (preg_match('/bot|crawler|spider|preview|facebookexternalhit|slack|discord|whatsapp|telegram|linkedin|pinterest/i', $request->userAgent() ?? '')) return true;
        if (str_contains(strtolower($request->header('Sec-Purpose', '').' '.$request->header('Purpose', '')), 'prefetch')) return true;
        if (auth('reseller')->id() === $member->reseller_id) return true;
        return auth('tenant')->check() && auth('tenant')->user()->memberships()
            ->where('tenant_id',$member->tenant_id)->where('status','active')->exists();
    }

    public function issue(Request $request, string $code, ReferrerProgramMembership $member): array
    {
        if ($this->excluded($request,$member)) return [null,null];
        $visitor=$request->cookie(self::COOKIE);
        if (!is_string($visitor) || !Str::isUuid($visitor)) $visitor=(string) Str::uuid();
        return [Crypt::encryptString(json_encode([
            'id'=>(string) Str::uuid(), 'code'=>$code, 'member'=>$member->id,
            'visitor'=>hash_hmac('sha256',$member->id.'|'.$visitor,config('app.key')),
            'expires'=>now()->addMinutes(10)->timestamp,
        ])),$visitor];
    }

    public function record(Request $request, string $code, ReferrerProgramMembership $member): void
    {
        if ($this->excluded($request,$member)) return;
        abort_if(strlen($request->getContent()) > 8192,413);
        try { $token=json_decode(Crypt::decryptString((string)$request->input('token')),true,512,JSON_THROW_ON_ERROR); }
        catch (\Throwable $e) { abort(422); }
        abort_unless(is_array($token) && ($token['code'] ?? null)===$code && ($token['member'] ?? null)===$member->id && ($token['expires'] ?? 0)>=now()->timestamp,422);
        // A page load can be delivered more than once; its signed event ID counts once.
        DB::table('program_referral_clicks')->insertOrIgnore([
            'id'=>$token['id'],'membership_id'=>$member->id,'visitor_hash'=>$token['visitor'],'created_at'=>now(),
        ]);
    }
}
