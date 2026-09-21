<?php
namespace App\Console\Commands;
use Illuminate\Console\Command;
use App\Models\ReferrerProgramMembership;
use App\Services\Programs\ProgramInviteDelivery;
class RefreshProgramInviteDelivery extends Command
{
 protected $signature='programs:refresh-invite-delivery';
 protected $description='Refresh recent program invitation delivery without sending mail';
 public function handle(): int {
  if(!config('programs.enabled') || !config('services.resend.key'))return self::SUCCESS;
  $checked=0;
  $members=ReferrerProgramMembership::where('tenant_id','!=','lgu-ids')
   ->whereNotNull('metadata->invite_delivery->provider_id')
   ->where('metadata->invite_delivery->sent_at','>=',now()->subDays(30)->toIso8601String())
   ->where(function($q){$q->whereNull('metadata->invite_delivery->checked_attempt_at')->orWhere('metadata->invite_delivery->checked_attempt_at','<',now()->subMinutes(5)->toIso8601String());})
   ->orderByRaw("COALESCE(metadata->'invite_delivery'->>'checked_attempt_at', '') ASC")->limit(20)->get();
  foreach($members as $member){
   if(\App\Support\ProtectedTenants::isProtected($member->tenant_id))continue;
   if(app(ProgramInviteDelivery::class)->check($member))$checked++;
   if(!app()->runningUnitTests())usleep(600000);
  }
  $this->info("Refreshed {$checked} invitation delivery statuses.");return self::SUCCESS;
 }
}
