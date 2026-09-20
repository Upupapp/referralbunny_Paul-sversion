<?php
namespace App\Http\Controllers;
use App\Models\{ProgramConnection,Program,ReferrerProgramMembership,Reseller,Tenant};
use Illuminate\Http\Request;
class PlatformReferralController extends Controller
{
    public function validateReferral(Request $request, string $connectionId)
    {
        abort_unless(config('programs.enabled'),404);
        abort_if(strlen($request->getContent())>2048,413);
        $connection=ProgramConnection::findOrFail($connectionId);
        abort_if(\App\Support\ProtectedTenants::isProtected($connection->tenant_id),403);
        abort_unless(Tenant::whereKey($connection->tenant_id)->where('status','active')->exists(),403);
        $timestamp=$request->header('X-RB-Timestamp','');
        abort_unless(ctype_digit($timestamp) && abs(time()-(int)$timestamp)<=300,401);
        abort_unless(hash_equals(hash_hmac('sha256',$timestamp.'.'.$request->getContent(),$connection->secret),$request->header('X-RB-Signature','')),401);
        $data=$request->validate(['membership_id'=>'required|string|max:100','click_token'=>'nullable|string|max:1800']);
        $program=Program::forTenant($connection->tenant_id)->where('status','active')->findOrFail($connection->program_id);
        $member=ReferrerProgramMembership::where('tenant_id',$connection->tenant_id)->where('program_id',$program->id)->where('status','active')->findOrFail($data['membership_id']);
        $referrer=Reseller::where('tenant_id',$connection->tenant_id)->whereIn('status',['active','nda_signed'])->findOrFail($member->reseller_id);
        $clickId=null;
        if (!empty($data['click_token'])) {
            try {
                $token=json_decode(\Illuminate\Support\Facades\Crypt::decryptString($data['click_token']),true);
                if (($token['member']??null)===$member->id && ($token['expires']??0)>=time() && \Illuminate\Support\Str::isUuid($token['id']??'')) $clickId=$token['id'];
            } catch (\Throwable $e) { /* Invalid optional analytics must not break referral attribution. */ }
        }
        return response()->json(['clickId'=>$clickId,'membershipId'=>$member->id,'windowDays'=>min(365,max(1,$program->attribution_window_days ?? 30)),
            'emailFingerprint'=>hash_hmac('sha256',strtolower(trim($referrer->email)),$connection->secret)])
            ->header('Cache-Control','no-store');
    }
}
