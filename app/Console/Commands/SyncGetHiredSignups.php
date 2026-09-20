<?php
namespace App\Console\Commands;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\ProgramConnection;
use App\Services\Programs\GetHiredSignupSync;
class SyncGetHiredSignups extends Command {
 protected $signature='programs:sync-gethired-signups {--connection=}';
 protected $description='Import verified GetHired referral signups without creating rewards';
 public function handle(GetHiredSignupSync $sync): int {
  if(!config('programs.enabled')||!config('services.gethired.enabled'))return self::SUCCESS;
  $connections=ProgramConnection::where('platform','gethired')->whereNotNull('platform_connected_at')->where('tenant_id','!=','lgu-ids');
  if($id=$this->option('connection'))$connections->whereKey($id);
  $failed=false;
  foreach($connections->get() as $connection){
   try{$n=$sync->sync($connection);$this->info($connection->id.': '.$n.' new signups synced.');}
   catch(\Throwable $e){$failed=true;DB::table('program_signup_sync')->where('connection_id',$connection->id)->update(['status'=>'unavailable']);$this->error($connection->id.': signup sync unavailable; retained existing records.');}
  }
  return $failed?self::FAILURE:self::SUCCESS;
 }
}
