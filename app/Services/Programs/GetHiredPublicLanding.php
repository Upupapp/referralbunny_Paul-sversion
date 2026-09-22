<?php
namespace App\Services\Programs;
use App\Models\{Program,ProgramLandingPage,ProgramOfferVersion};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
class GetHiredPublicLanding {
 public const TENANT='test-sp4s9i';
 public const PROGRAM='8184125a-ace1-4db8-b7d9-884e692002b4';
 public const FIELDS=['headline'=>100,'description'=>260,'benefit_one'=>45,'benefit_two'=>45,'benefit_three'=>45,'estimator_title'=>70,'estimator_description'=>120,'form_title'=>70,'form_description'=>180,'footer'=>120];
 public static function matches(Program $p): bool{return $p->tenant_id===self::TENANT && $p->id===self::PROGRAM;}
 public function program(): Program {
  abort_unless(config('programs.enabled'),404);
  return Program::where('tenant_id',self::TENANT)->findOrFail(self::PROGRAM);
 }
 public function defaults(): array {return [
  'headline'=>'Earn by Referring Employers to GetHired',
  'description'=>'Share GetHired with companies that are hiring. Sign up and receive your Referral Bunny invitation by email to start referring.',
  'benefit_one'=>'Free to join','benefit_two'=>'Recurring referral rewards','benefit_three'=>'Invitation by email',
  'estimator_title'=>'Estimate your referral earnings','estimator_description'=>'See how qualifying subscription payments could add up.',
  'form_title'=>'Start earning as a referrer','form_description'=>'We’ll email your invitation to join the GetHired referral program on Referral Bunny.',
  'footer'=>'Built for referral growth with Referral Bunny',
 ];}
 public function content(): array {return array_replace($this->defaults(),array_intersect_key(ProgramLandingPage::where('tenant_id',self::TENANT)->find(self::PROGRAM)?->content??[],self::FIELDS));}
 public function terms(Program $p): array {
  $offers=app(RewardIncomeEstimator::class)->offers($p);
  $v=$offers->count()===1?$offers->first()->currentVersion:null;
  if(!$v || $v->currency!=='PHP' || $v->reward_model!=='percentage' || (int)($v->reward_rules['duration_months']??0)!==12) return ['ready'=>false];
  $scenario=app(RewardIncomeEstimator::class)->scenario($v,['currency'=>'PHP','monthly_amount_minor'=>349000],'monthly');
  if(isset($scenario['error']) || $scenario['eligible_payments']!==12)return ['ready'=>false];
  return ['ready'=>true,'rate'=>(float)$v->percentage_rate,'hold'=>(int)$v->reward_rules['hold_days'],'scenario'=>$scenario];
 }
 public function packages(Program $p): array {
  $catalog=app(GetHiredPricingCatalog::class)->forProgram($p);
  $unavailable=['packages'=>[],'default'=>null,'error'=>'Package pricing is temporarily unavailable. You can still request your invitation.'];
  if(isset($catalog['error']))return $unavailable;
  $offers=app(RewardIncomeEstimator::class)->offers($p);
  if(!$this->terms($p)['ready'] || $offers->count()!==1)return $unavailable;
  $packages=collect($catalog['packages']??[])->filter(fn($r)=>($r['currency']??null)==='PHP' && is_int($r['monthly_amount_minor']??null) && $r['monthly_amount_minor']>0)
   ->sortBy(fn($r)=>$r['sort_order']??$r['monthly_amount_minor'])->map(function($r)use($offers){
    $scenario=app(RewardIncomeEstimator::class)->scenario($offers->first()->currentVersion,$r,'monthly');
    if(isset($scenario['error']))return null;
    return ['id'=>$r['id'],'name'=>$r['name'],'currency'=>$r['currency'],'monthly_amount_minor'=>$r['monthly_amount_minor'],'recommended'=>(bool)($r['recommended']??false),'estimate'=>['per'=>$scenario['per_minor'],'monthly'=>$scenario['monthly_minor'],'annual'=>$scenario['year_minor']]];
   })->filter()->values();
  if($packages->isEmpty())return $unavailable;
  $default=$packages->firstWhere('monthly_amount_minor',349000)??$packages->firstWhere('recommended',true)??$packages->first();
  return ['packages'=>$packages->all(),'default'=>$default['id'],'error'=>null];
 }
 public function canEnroll(Program $p): bool {
  return $p->status==='active' && (!$p->starts_at||$p->starts_at->lte(now())) && (!$p->ends_at||$p->ends_at->gte(now())) && (!$p->enrollment_opens_at||$p->enrollment_opens_at->lte(now())) && (!$p->enrollment_closes_at||$p->enrollment_closes_at->gte(now()));
 }
 // Explicit, idempotent release step; existing versions and customer bindings remain intact.
 public function publishOneYearOffer(string $actor): ProgramOfferVersion {
  return DB::transaction(function()use($actor){
   $p=Program::where('tenant_id',self::TENANT)->lockForUpdate()->findOrFail(self::PROGRAM);
   $offers=app(RewardIncomeEstimator::class)->offers($p);
   if($offers->count()!==1)throw ValidationException::withMessages(['offer'=>'Exactly one current GetHired offer is required.']);
   $o=$offers->first();$old=$o->currentVersion;
   if($old->reward_model!=='percentage' || $old->qualifying_event!=='payment_received' || ($old->reward_rules['scope']??'')!=='recurring')throw ValidationException::withMessages(['offer'=>'Review this custom reward offer before changing its duration.']);
   if((int)($old->reward_rules['duration_months']??0)===12)return $old;
   if((int)($old->reward_rules['duration_months']??0)!==6)throw ValidationException::withMessages(['offer'=>'The current offer differs from the approved six-to-twelve-month change.']);
   $new=$old->replicate(['id','created_at','updated_at']);$new->id=(string)\Illuminate\Support\Str::uuid();
   $new->version_number=ProgramOfferVersion::where('offer_id',$o->id)->max('version_number')+1;
   $new->reward_rules=array_replace($old->reward_rules,['duration_months'=>12]);$new->published_at=now();$new->published_by=$actor;$new->effective_from=now();$new->immutable_snapshot=array_replace($old->immutable_snapshot??[],['reward_rules'=>$new->reward_rules,'previous_version_id'=>$old->id,'change_summary'=>'Approved GetHired recurring reward duration: 12 months']);$new->save();
   $o->update(['current_version_id'=>$new->id,'updated_by'=>$actor]);return $new;
  });
 }
}
