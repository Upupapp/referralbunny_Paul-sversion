<?php
namespace App\Services\Programs;
use App\Models\{Program,ProgramConnection};
use App\Support\ProtectedTenants;
use Carbon\CarbonImmutable as Date;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Schema};
class SubscriptionDashboard {
 public function compute(Program $program, Request $request): array {
  abort_if(ProtectedTenants::isProtected($program->tenant_id),404);
  $tz=$program->timezone ?: 'UTC';
  $v=$request->validate(['from'=>'nullable|date_format:Y-m-d','to'=>'nullable|date_format:Y-m-d|after_or_equal:from','currency'=>'nullable|string|size:3']);
  $from=Date::parse($v['from'] ?? Date::now($tz)->subDays(29)->toDateString(),$tz)->startOfDay();
  $to=Date::parse($v['to'] ?? Date::now($tz)->toDateString(),$tz)->endOfDay();
  abort_if($to->lt($from) || $from->diffInDays($to)>3660,422,'Choose a date range of at most ten years.');
  $connection=ProgramConnection::where('tenant_id',$program->tenant_id)->where('program_id',$program->id)->first();
  $base=DB::table('program_conversion_events')->where('connection_id',$connection?->id ?? 'none')->whereIn('type',['payment','refund']);
  $currencies=(clone $base)->distinct()->pluck('currency')->push($program->default_currency ?: 'PHP')->unique()->values();
  $currency=$v['currency'] ?? ($program->default_currency ?: 'PHP');
  abort_unless($currencies->contains($currency),422,'Choose an available currency.');
  $events=(clone $base)->where('currency',$currency)->whereBetween('occurred_at',[$from->utc(),$to->utc()])->get();
  // First paid customers are counted once across all currencies and all recorded history.
  $first=(clone $base)->where('type','payment')->whereNotNull('referrer_id')->selectRaw('customer_id, MIN(occurred_at) as first_at')->groupBy('customer_id')->get();
  $daily=[]; for($d=$from;$d->lte($to);$d=$d->addDay()) $daily[$d->toDateString()]=0;
  foreach($first as $r) { $day=Date::parse($r->first_at,'UTC')->setTimezone($tz)->toDateString(); if(isset($daily[$day])) $daily[$day]++; }
  $revenue=$events->sum(fn($e)=>$e->type==='payment'?$e->amount_minor:-$e->amount_minor)/100;
  $rewards=$events->sum('reward_minor')/100;
  $target=Schema::hasTable('program_referral_targets') ? DB::table('program_referral_targets')->where('tenant_id',$program->tenant_id)->where('program_id',$program->id)->first() : null;
  $average=$target ? $target->total/(Date::parse($target->starts_on)->diffInDays(Date::parse($target->ends_on))+1) : null;
  $targetDaily=[]; foreach($daily as $day=>$value) $targetDaily[]=$target && $day >= $target->starts_on && $day <= $target->ends_on ? $average : null;
  $top=$events->whereNotNull('referrer_id')->groupBy('referrer_id')->map(function($rows,$id) use($program) {
   $name=DB::table('resellers')->where('tenant_id',$program->tenant_id)->where('id',$id)->value('name') ?? 'Former referrer';
   return ['name'=>$name,'revenue'=>$rows->sum(fn($e)=>$e->type==='payment'?$e->amount_minor:-$e->amount_minor)/100,'rewards'=>$rows->sum('reward_minor')/100];
  })->sortByDesc('revenue')->take(5);
  $unread=Schema::hasTable('program_messages') ? DB::table('program_messages')->where('tenant_id',$program->tenant_id)->where('program_id',$program->id)->where('sender_type','referrer')->whereNull('read_at')->count() : 0;
  return compact('from','to','tz','connection','currencies','currency','revenue','rewards','daily','target','average','targetDaily','top','unread');
 }
}
