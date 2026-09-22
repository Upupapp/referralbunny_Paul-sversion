<?php
namespace App\Http\Controllers\Web;
use App\Http\Controllers\Controller;
use App\Models\{Program,ProgramLandingPage,Reseller,ReferrerProgramMembership};
use App\Services\Programs\{GetHiredPublicLanding,ProgramReferrerInvite};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Cache,RateLimiter};
use Illuminate\Validation\ValidationException;
class GetHiredPublicLandingController extends Controller {
 private function live(GetHiredPublicLanding $landing): Program {
  $p=$landing->program();abort_unless($p->tenant?->status==='active' && ProgramLandingPage::where('tenant_id',$p->tenant_id)->where('published',true)->find($p->id),404);return $p;
 }
 public function show(Request $request,GetHiredPublicLanding $landing) {
  $p=$this->live($landing);return $this->render($request,$landing,$p,false);
 }
 private function render(Request $request,GetHiredPublicLanding $landing,Program $p,bool $preview) {
  if(!$preview)$request->session()->put('gethired_landing_opened',now()->timestamp);
  $terms=$landing->terms($p);$pricing=$landing->packages($p);$content=$landing->content();$open=$landing->canEnroll($p)&&$terms['ready'];
  return response()->view('public.gethired.referrers',compact('content','terms','pricing','open','preview'))->header('Cache-Control','private, no-store');
 }
 public function preview(Request $request,string $tenantId,string $programId,GetHiredPublicLanding $landing) {
  $p=Program::forTenant($tenantId)->findOrFail($programId);abort_unless(GetHiredPublicLanding::matches($p),404);$this->authorize('update',$p);return $this->render($request,$landing,$p,true);
 }
 public function update(Request $request,string $tenantId,string $programId,GetHiredPublicLanding $landing) {
  $p=Program::forTenant($tenantId)->findOrFail($programId);abort_unless(GetHiredPublicLanding::matches($p),404);$this->authorize('update',$p);
  $rules=['published'=>'required|boolean','content'=>'required|array:'.implode(',',array_keys(GetHiredPublicLanding::FIELDS))];
  foreach(GetHiredPublicLanding::FIELDS as $key=>$max)$rules['content.'.$key]='required|string|max:'.$max;
  $data=$request->validate($rules);
  if($data['published'] && (!$landing->terms($p)['ready'] || !$landing->canEnroll($p)))throw ValidationException::withMessages(['published'=>'Publish the approved one-year offer and activate enrollment before publishing this page.']);
  ProgramLandingPage::updateOrCreate(['program_id'=>$p->id,'tenant_id'=>$p->tenant_id],['content'=>$data['content'],'published'=>$data['published'],'updated_by'=>(string)(auth('tenant')->id()??auth('web')->id())]);
  return back()->with('success','Public referral page saved.');
 }
 public function invite(Request $request,GetHiredPublicLanding $landing) {
  $p=$this->live($landing);abort_unless($landing->canEnroll($p)&&$landing->terms($p)['ready'],422,'Enrollment is currently unavailable.');
  $data=$request->validate(['name'=>'required|string|max:150','email'=>'required|email:rfc|max:254','phone'=>'nullable|string|max:50','website'=>'nullable|string|max:0','program_id'=>'prohibited','tenant_id'=>'prohibited','package_id'=>'prohibited','package_price'=>'prohibited']);
  $opened=$request->session()->get('gethired_landing_opened');
  if(!$opened || now()->timestamp-$opened<2 || now()->timestamp-$opened>7200)return response()->json(['message'=>'Please reload this page and try again.'],422);
  $ipKey='public-gethired-hour:'.hash_hmac('sha256',(string)$request->ip(),config('app.key'));
  if(RateLimiter::tooManyAttempts($ipKey,20))return response()->json(['message'=>'Too many requests. Please try again later.'],429);
  RateLimiter::hit($ipKey,3600);
  $email=strtolower(trim($data['email']));$key='public-gethired:'.hash_hmac('sha256',$email,config('app.key'));
  if(RateLimiter::tooManyAttempts($key,1))return response()->json(['message'=>'Please check your inbox or wait a few minutes before requesting another invitation.'],429);
  $lock=Cache::lock($key.':lock',120);if(!$lock->get())return response()->json(['message'=>'Your request is being processed. Please wait.'],429);
  try {
   RateLimiter::hit($key,60);
   $r=Reseller::withTrashed()->where('tenant_id',$p->tenant_id)->whereRaw('LOWER(email) = ?',[$email])->first();
   $m=$r?ReferrerProgramMembership::where('tenant_id',$p->tenant_id)->where('program_id',$p->id)->where('reseller_id',$r->id)->first():null;
   $state=$m && in_array($m->status,['active','approved']) && $r->password?'existing':($m?'resent':'sent');
   app(ProgramReferrerInvite::class)->send($p,trim($data['name']),$email,['phone'=>$data['phone']??null],true);
   RateLimiter::clear($key);RateLimiter::hit($key,600);
   return response()->json(['state'=>$state,'email'=>$email]);
  } catch(ValidationException $e) {
   return response()->json(['message'=>'We couldn’t send another invitation right now. Check your inbox for an earlier invite, sign in if you already have an account, or try again later.'],422);
  } catch(\Throwable $e) {
   report($e);return response()->json(['message'=>'We couldn’t send your invite right now. Please try again in a moment.'],503);
  } finally {$lock->release();}
 }
}
