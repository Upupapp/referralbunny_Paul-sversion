<?php
namespace App\Http\Controllers\Web;
use App\Http\Controllers\Controller;
use App\Models\{ProgramConnection, Program, Tenant, TenantMembership};
use App\Services\Platform\GetHiredConnector;
use App\Support\ProtectedTenants;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
class GetHiredConnectionController extends Controller
{
    private function connection(string $tenantId, string $programId): ProgramConnection
    {
        abort_unless(config('programs.enabled') && !ProtectedTenants::isProtected($tenantId), 404);
        $user = auth('tenant')->user();
        abort_unless($user && $user->status === 'active' && Tenant::whereKey($tenantId)->where('status','active')->exists(), 403);
        abort_unless(TenantMembership::where('tenant_id',$tenantId)->where('tenant_user_id',$user->id)->where('status','active')->whereIn('role',['owner','admin'])->exists(),403);
        Program::forTenant($tenantId)->findOrFail($programId);
        return ProgramConnection::where('tenant_id',$tenantId)->where('program_id',$programId)->firstOrFail();
    }
    public function start(Request $request, string $tenantId, string $programId, GetHiredConnector $api)
    {
        $connection = $this->connection($tenantId,$programId);
        if (config('services.gethired.owner_connection_id')) {
            abort_unless($connection->id === config('services.gethired.owner_connection_id'), 403);
            $result = $api->request('owner-connect', ['connectionId'=>$connection->id, 'programId'=>$programId, 'eventSecret'=>$connection->secret]);
            abort_unless(($result['connectionId'] ?? '') === $connection->id, 502);
            $connection->update(['platform'=>'gethired','platform_connected_at'=>now()]);
            $request->session()->forget('gethired.'.$connection->id);
            return redirect()->route('tenant.quick-program.connection',[$tenantId,$programId])->with('platform_message','GetHired is connected. No extra login, website code or API keys are needed.');
        }
        $state = Str::random(64); $verifier = Str::random(64);
        $result = $api->request('requests', [
            'connectionId'=>$connection->id, 'programId'=>$programId,
            'programName'=>Program::findOrFail($programId)->name, 'businessName'=>Tenant::findOrFail($tenantId)->name,
            'state'=>$state, 'challenge'=>rtrim(strtr(base64_encode(hash('sha256',$verifier,true)), '+/', '-_'), '='),
            'callbackPath'=>parse_url(route('tenant.quick-program.gethired.callback',[$tenantId,$programId]),PHP_URL_PATH),
        ]);
        abort_unless(isset($result['requestId']) && preg_match('/^[a-f0-9]{64}$/',$result['requestId']),502);
        $request->session()->put('gethired.'.$connection->id, ['state'=>$state,'verifier'=>$verifier,'request_id'=>$result['requestId'],'expires'=>now()->timestamp+600,'user_id'=>auth('tenant')->id()]);
        return redirect()->away(rtrim(config('services.gethired.web_url'),'/').'/integrations/referral-bunny?request='.urlencode($result['requestId']));
    }
    public function callback(Request $request, string $tenantId, string $programId, GetHiredConnector $api)
    {
        $connection=$this->connection($tenantId,$programId);
        $pending=$request->session()->get('gethired.'.$connection->id);
        if (!$pending || $pending['expires']<now()->timestamp) {
            $request->session()->forget('gethired.'.$connection->id);
            return redirect()->route('tenant.quick-program.connection',[$tenantId,$programId])->withErrors(['platform'=>'This connection request expired or was already completed. Check the connection below or connect again.']);
        }
        abort_unless($pending['user_id']===auth('tenant')->id() && hash_equals($pending['state'],(string)$request->query('state')),403);
        $return=route('tenant.quick-program.connection',[$tenantId,$programId]);
        if ($request->query('error') === 'access_denied') {
            $request->session()->forget('gethired.'.$connection->id);
            return redirect($return)->with('platform_message','Connection cancelled. Your program is unchanged.');
        }
        if (!preg_match('/^[a-f0-9]{64}$/',(string)$request->query('code'))) return redirect($return)->withErrors(['platform'=>'GetHired did not complete the connection. Please connect again.']);
        try { $result=$api->request('exchange',['requestId'=>$pending['request_id'],'code'=>$request->query('code'),'verifier'=>$pending['verifier'],'eventSecret'=>$connection->secret]); } catch (\Illuminate\Validation\ValidationException $e) { return redirect($return)->withErrors($e->errors()); }
        abort_unless(($result['connectionId'] ?? '')===$connection->id,502);
        $connection->update(['platform'=>'gethired','platform_connected_at'=>now()]);
        $request->session()->forget('gethired.'.$connection->id);
        return redirect($return)->with('platform_message','GetHired is connected. No website code or API keys are needed.');
    }
    public function status(Request $request, string $tenantId, string $programId, GetHiredConnector $api)
    {
        $connection=$this->connection($tenantId,$programId);
        if (!config('services.gethired.enabled')) return response()->json(['account'=>'unavailable','signups'=>'unknown','payments'=>'not_available'])->header('Cache-Control','no-store');
        try {
            $result=$api->request('status',['connectionId'=>$connection->id]);
            abort_unless(in_array($result['account'] ?? '',['connected','not_connected','disconnected','reconnect_required'],true)
                && in_array($result['signups'] ?? '',['ready','not_connected','paused'],true)
                && in_array($result['payments'] ?? '',['not_available','authorization_required','ready','receiving','attention'],true),502);
            return response()->json(\Illuminate\Support\Arr::only($result,['account','signups','payments','connectedAt','lastSignupAt','lastPaymentAt']))->header('Cache-Control','no-store');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['account'=>'unknown','signups'=>'unknown','payments'=>'not_available','message'=>'GetHired could not be checked. Your saved connection has not been changed. Please try again.'])->header('Cache-Control','no-store');
        }
    }
    public function review(Request $request, string $tenantId, string $programId, GetHiredConnector $api)
    {
        $connection=$this->connection($tenantId,$programId);
        $filters=$request->validate(['kind'=>'sometimes|in:payment,refund','status'=>'sometimes|in:held,failed,pending,delivered','page'=>'sometimes|integer|min:1|max:10000']);
        $filters=array_merge(['kind'=>'payment','status'=>'held','page'=>1],$filters);
        $result=null; $unavailable=false;
        try { $result=$api->request('review',array_merge($filters,['connectionId'=>$connection->id])); }
        catch (\Illuminate\Validation\ValidationException $e) { $unavailable=true; }
        return response()->view('tenant.programs.gethired-review',[
            'tenant'=>Tenant::findOrFail($tenantId),'program'=>Program::forTenant($tenantId)->findOrFail($programId),
            'filters'=>$filters,'result'=>$result,'unavailable'=>$unavailable,
        ])->header('Cache-Control','no-store');
    }
    public function retryDelivery(Request $request, string $tenantId, string $programId, GetHiredConnector $api)
    {
        $connection=$this->connection($tenantId,$programId);
        $input=$request->validate(['kind'=>'required|in:payment,refund','id'=>'required|regex:/^[a-f0-9]{64}$/','note'=>'required|string|min:5|max:500','confirmed'=>'accepted']);
        try {
            $api->request('review/retry',['connectionId'=>$connection->id,'kind'=>$input['kind'],'id'=>$input['id'],'note'=>$input['note'],'actorId'=>(string)auth('tenant')->id()]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors(['review'=>'This item could not be queued. Refresh the list and check that GetHired is connected. Its status may have changed.']);
        }
        return redirect()->route('tenant.quick-program.gethired.review',[$tenantId,$programId,'kind'=>$input['kind'],'status'=>'pending'])->with('review_message','Retry queued. The worker will recheck eligibility and delivery order before sending it.');
    }
    public function disconnect(Request $request, string $tenantId, string $programId, GetHiredConnector $api)
    {
        $connection=$this->connection($tenantId,$programId);
        $api->request('disconnect',['connectionId'=>$connection->id]);
        $connection->update(['platform'=>null]);
        $request->session()->forget('gethired.'.$connection->id);
        return back()->with('platform_message','GetHired disconnected. Existing referral records are retained.');
    }
}
