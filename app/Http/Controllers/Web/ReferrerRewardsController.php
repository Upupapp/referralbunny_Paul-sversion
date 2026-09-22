<?php
namespace App\Http\Controllers\Web;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\Programs\{ReferrerProgramContext,ReferrerRewardHistory,RewardIncomeEstimator,GetHiredPricingCatalog,ReferralAccountReadModel};
use Illuminate\Http\Request;
class ReferrerRewardsController extends Controller {
 public function index(Request $request,string $tenantId) {
  $context=app(ReferrerProgramContext::class)->resolve($tenantId,$request);extract($context);abort_unless($program,404);app(ReferralAccountReadModel::class)->authorize($program,$reseller);
  $tenant=Tenant::findOrFail($tenantId);$rewardError=null;$rewards=null;
  try{$rewards=app(ReferrerRewardHistory::class)->report($program,$reseller,$request);}catch(\Illuminate\Database\QueryException $e){report($e);$rewardError='Reward history is temporarily unavailable. Please retry.';}
  $estimate=app(RewardIncomeEstimator::class)->build($program,app(GetHiredPricingCatalog::class)->forProgram($program));
  $rewardsPage=true;$records=$rewards['records']??null;
  return view('reseller.programs.rewards',$context+compact('tenant','rewards','records','rewardError','estimate','rewardsPage'));
 }
}
