<?php
namespace App\Services\Programs;
use App\Models\{Program,Reseller,ExportRequest};
use App\Services\ExportPermissionService;
use Illuminate\Http\Request;
class ReferralAccountExport {
 public function rows(ExportRequest $export): array {
  abort_unless($export->requester_type==='reseller',403);
  $p=Program::forTenant($export->tenant_id)->findOrFail($export->export_scope['program_id'] ?? '');
  $r=Reseller::where('tenant_id',$export->tenant_id)->findOrFail($export->requester_id);
  $permissions=app(ExportPermissionService::class);
  abort_unless($permissions->checkReferrerExport($r,'deals',$permissions->getSettings($p->tenant_id))['allowed'],403);
  $model=app(ReferralAccountReadModel::class);$model->authorize($p,$r);
  $f=$model->filters(Request::create('/','GET',$export->export_scope),$p);
  $rows=[['Referral reference','Referred on (UTC)','Registered (UTC)','Progress','Distinct recorded payments','Reward currency','Net recorded rewards (not payout balance)','Reversals','Eligibility','Latest activity (UTC)','Cohort from','Cohort to','Program timezone']];
  foreach($model->matching($model->cohort($p,$r,$f),$f)->cursor() as $a) {
   $rows[]=[$a->reference,$a->referred_at ?? 'Date unavailable',$a->registered_at ?? 'Date unavailable',$a->payments>1?'Repeat payment recorded':($a->payments?'First payment recorded':($a->registered_at?'Registered':'Activity recorded')),(int)$a->payments,$f['currency'],number_format($a->rewards/100,2,'.',''),number_format($a->reversals/100,2,'.',''),'Not available',$a->activity_at,$f['from'] ?? 'All time',$f['to'] ?? 'All time',$p->timezone ?: 'UTC'];
  }
  // Text is fixed vocabulary, validated dates/currency, or generated references. Preserve numeric negatives.
  foreach($rows as &$row) foreach($row as $i=>&$value) if(!in_array($i,[4,6,7]) && is_string($value) && preg_match('/^[=+@\-\t\r\n]/',$value)) $value="'".$value;
  return $rows;
 }
}
