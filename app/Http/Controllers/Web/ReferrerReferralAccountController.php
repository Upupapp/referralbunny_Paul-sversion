<?php
namespace App\Http\Controllers\Web;
use App\Http\Controllers\Controller;
use App\Models\{Tenant,ExportRequest};
use App\Services\Programs\{ReferralAccountReadModel,ReferrerProgramContext,ReferrerReferralLink};
use App\Services\{ExportPermissionService,ExportApprovalService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
class ReferrerReferralAccountController extends Controller
{
    private function context(Request $request,string $tenantId): array {
        $c=app(ReferrerProgramContext::class)->resolve($tenantId,$request);
        abort_unless($c['program'],404);app(ReferralAccountReadModel::class)->authorize($c['program'],$c['reseller']);return $c;
    }
    private function permission($r,string $tenantId): array {
        $service=app(ExportPermissionService::class);
        return $service->checkReferrerExport($r,'deals',$r->can_export_own_data ? $service->getSettings($tenantId) : $service->defaultSettings());
    }
    public function index(Request $request,string $tenantId) {
        $c=$this->context($request,$tenantId);extract($c);
        $tenant=Tenant::findOrFail($tenantId);
        $referralAccounts=app(ReferralAccountReadModel::class)->report($program,$reseller,$request);
        $membership=$program->referrerMemberships()->where('tenant_id',$tenantId)->where('reseller_id',$reseller->id)->first();
        $referralLink=app(ReferrerReferralLink::class)->forMembership($program,$membership);
        $exportPermission=$this->permission($reseller,$tenantId);
        return view('reseller.programs.referrals',$c+compact('tenant','referralAccounts','referralLink','exportPermission'));
    }
    public function detail(Request $request,string $tenantId,string $reference) {
        $c=$this->context($request,$tenantId);$request->validate(['currency'=>'required|regex:/^[A-Z]{3}$/','history_page'=>'nullable|integer|min:1']);
        return response()->json(app(ReferralAccountReadModel::class)->detail($c['program'],$c['reseller'],$reference,$request->currency,(int)$request->input('history_page',1)))->header('Cache-Control','private, no-store');
    }
    public function export(Request $request,string $tenantId) {
        $c=$this->context($request,$tenantId);$permission=$this->permission($c['reseller'],$tenantId);abort_unless($permission['allowed'],403,$permission['reason']);
        $model=app(ReferralAccountReadModel::class);$report=$model->report($c['program'],$c['reseller'],$request);
        $settings=app(ExportPermissionService::class)->getSettings($tenantId);
        $request->validate(['reason'=>($settings['require_reason_referrer'] ? 'required' : 'nullable').'|string|max:1000']);
        $scope=$report['f']+ ['report'=>'referral_accounts','program_id'=>$c['program']->id];
        if ($scope['from']) $scope['range']='custom'; // Freeze the requested cohort while awaiting approval.
        $export=app(ExportApprovalService::class)->createRequest($tenantId,'reseller',$c['reseller']->id,'referrer','deals','csv',$scope,[],$request->input('reason'),$permission['requires_approval'],app(ExportPermissionService::class)->isSensitive('deals'));
        return redirect()->route('reseller.referral-accounts.export-status',[$tenantId,$export->id,'program_id'=>$c['program']->id])->with('success','Export requested.');
    }
    public function exportStatus(Request $request,string $tenantId,string $exportId) {
        $c=$this->context($request,$tenantId);
        $export=ExportRequest::forTenant($tenantId)->where('requester_type','reseller')->where('requester_id',$c['reseller']->id)->findOrFail($exportId);
        abort_unless(($export->export_scope['report']??null)==='referral_accounts' && ($export->export_scope['program_id']??null)===$c['program']->id,404);
        if($request->boolean('download')) {
            abort_unless($this->permission($c['reseller'],$tenantId)['allowed'] && $export->canBeDownloaded(),403);
            abort_unless($export->file_path && str_starts_with($export->file_path,'exports/'.$tenantId.'/') && Storage::disk('local')->exists($export->file_path),404);
            app(ExportApprovalService::class)->recordDownload($export);
            return Storage::disk('local')->download($export->file_path,$export->file_name);
        }
        return view('reseller.programs.referral-export',$c+['tenant'=>Tenant::findOrFail($tenantId),'export'=>$export]);
    }
}
