<?php
namespace App\Services\Programs;
use App\Models\{Program,ProgramOfferVersion};
class RewardIncomeEstimator {
 public function offers(Program $p) {
  return $p->offers()->where('tenant_id',$p->tenant_id)->where('status','active')->with('currentVersion')->get()->filter(function($o)use($p){$v=$o->currentVersion;return $v && $v->status==='published' && $v->tenant_id===$p->tenant_id && $v->program_id===$p->id && (!$v->effective_from || $v->effective_from->lte(now())) && (!$v->effective_until || $v->effective_until->gt(now()));})->values();
 }
 // Decimal strings, integer arithmetic and half-up rounding; never float-based reward inputs.
 private function scaled($value,int $places): ?int {
  if(!preg_match('/^\d+(?:\.\d{1,4})?$/',(string)$value))return null;
  [$whole,$fraction]=array_pad(explode('.',(string)$value),2,'');
  $fraction=str_pad($fraction,4,'0');$units=(int)$whole*10000+(int)$fraction;
  $div=10**(4-$places);return intdiv($units+intdiv($div,2),$div);
 }
 public function scenario(ProgramOfferVersion $v,array $package,string $cadence): array {
  $bad=fn($message)=>['error'=>$message];$rules=$v->reward_rules??[];
  if($v->currency!==$package['currency'])return $bad('Package and reward currencies differ. No currency conversion is applied.');
  if(!in_array($v->reward_model,['percentage','fixed']) || $v->qualifying_event!=='payment_received' || ($rules['basis']??'')!=='net_collected_excluding_tax' || $v->cap_amount!==null)return $bad('Calculator unavailable for this custom reward structure.');
  if(!isset($rules['hold_days']) || !is_numeric($rules['hold_days']) || $rules['hold_days']<0)return $bad('The published hold period is incomplete.');
  $scope=$rules['scope']??'';
  if(!in_array($scope,['first_payment','recurring']))return $bad('Estimate unavailable for this reward structure.');
  $duration=$rules['duration_months']??null;
  if($scope==='recurring' && (!is_numeric($duration) || (int)$duration!=$duration || $duration<1))return $bad('The published reward duration is incomplete.');
  $amount=$package[$cadence.'_amount_minor']??null;
  if(!is_int($amount)||$amount<1)return $bad('This billing cadence is unavailable for the selected package.');
  $rate=$this->scaled($v->percentage_rate,4);$fixed=$this->scaled($v->fixed_amount,2);
  if($v->reward_model==='percentage' && ($rate===null || $rate>1000000))return $bad('The reward rate is unavailable.');
  if($v->reward_model==='fixed' && $fixed===null)return $bad('The fixed reward is unavailable.');
  $per=$v->reward_model==='percentage'?intdiv($amount*$rate+500000,1000000):$fixed;
  $payments=$cadence==='annual'||$scope==='first_payment'?1:min(12,(int)$duration);
  $year=$per*100*$payments;
  return ['referrals'=>100,'currency'=>$package['currency'],'price_minor'=>$amount,'per_minor'=>$per,'eligible_payments'=>$payments,'monthly_minor'=>$cadence==='annual'?intdiv($year+6,12):$per*100,'year_minor'=>$year,'rate'=>$v->reward_model==='percentage'?rtrim(rtrim((string)$v->percentage_rate,'0'),'.').'%':$v->currency.' '.number_format($fixed/100,2).' fixed','monthly_label'=>$cadence==='annual'?'Average monthly equivalent':($scope==='first_payment'?'Estimated first-payment income':'Estimated eligible-month income')];
 }
 public function build(Program $p,array $catalog): array {
  $offers=$this->offers($p);$result=['offers'=>$offers,'calculator'=>['error'=>$catalog['error']??null,'packages'=>[],'default'=>null]];
  if(isset($catalog['error']))return $result;
  if($offers->count()!==1){$result['calculator']['error']=$offers->isEmpty()?'No current published reward offer.':'Multiple reward offers apply — view program mechanics.';return $result;}
  foreach($catalog['packages'] as $package){$package['monthly']=$this->scenario($offers->first()->currentVersion,$package,'monthly');$package['annual']=$this->scenario($offers->first()->currentVersion,$package,'annual');$result['calculator']['packages'][]=$package;}
  $packages=collect($result['calculator']['packages']);$result['calculator']['default']=$packages->firstWhere('id','growth')['id']??$packages->first()['id']??null;
  if(!$result['calculator']['default'])$result['calculator']['error']='No active GetHired packages are currently available.';
  return $result;
 }
}
