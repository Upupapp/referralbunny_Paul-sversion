<?php
namespace App\Console\Commands;
use Illuminate\Console\Command;
use App\Services\Programs\GetHiredPublicLanding;
class PrepareGetHiredPublicLanding extends Command {
 protected $signature='gethired:prepare-public-landing {--apply : Publish the approved twelve-month offer version} {--actor= : Company admin ID for audit}';
 protected $description='Inspect or apply the approved GetHired-only twelve-month reward change; does not publish the landing page.';
 public function handle(GetHiredPublicLanding $landing): int {
  $p=$landing->program();
  if(!$this->option('apply')){$this->info('Dry run: GetHired only. Current one-year mechanics ready: '.($landing->terms($p)['ready']?'yes':'no').'. No changes made.');return self::SUCCESS;}
  $actor=(string)$this->option('actor');
  if(!$actor || !\App\Models\TenantMembership::where('tenant_id',$p->tenant_id)->where('tenant_user_id',$actor)->where('status','active')->whereIn('role',['owner','admin'])->exists()){$this->error('An active GetHired company owner/admin ID is required.');return self::FAILURE;}
  $v=$landing->publishOneYearOffer($actor);$this->info('Current GetHired offer version '.$v->version_number.' has twelve-month rewards. Historical versions unchanged. Page publication remains an admin action.');return self::SUCCESS;
 }
}
