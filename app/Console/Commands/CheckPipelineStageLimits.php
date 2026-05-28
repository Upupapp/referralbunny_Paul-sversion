<?php

namespace App\Console\Commands;

use App\Mail\PipelineStageWarning;
use App\Services\EmailLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * LOCKED — LGU IDS pipeline stage time limit enforcement.
 * Do not alter the rule logic without an explicit "Update LGU IDS commission computation" instruction.
 */
class CheckPipelineStageLimits extends Command
{
    protected $signature   = 'leads:check-pipeline-limits';
    protected $description = 'Warn resellers and admins when deals are approaching stage time limits (LGU IDS)';

    public function handle(): int
    {
        $rules = DB::table('tenant_pipeline_stage_rules')
            ->get()
            ->groupBy('tenant_id');

        $warned    = 0;
        $notified  = 0;

        foreach ($rules as $tenantId => $stageRules) {
            $tenantName = DB::table('tenants')->where('id', $tenantId)->value('name') ?? $tenantId;

            // Resolve admin recipients once per tenant — avoids N+1 inside lead loop
            $adminIds = DB::table('tenant_memberships as tm')
                ->join('tenant_users as u', 'tm.tenant_user_id', '=', 'u.id')
                ->where('tm.tenant_id', $tenantId)
                ->where('tm.status', 'active')
                ->whereIn('tm.role', ['owner', 'admin', 'manager'])
                ->pluck('u.id')
                ->toArray();

            foreach ($stageRules as $rule) {
                // Deals in this stage that are at or below the warning threshold
                $warningLeads = DB::table('leads')
                    ->where('tenant_id', $tenantId)
                    ->where('stage', $rule->stage)
                    ->whereIn('status', ['active', 'expiring'])
                    ->where('days_left', '<=', $rule->warning_days)
                    ->where('days_left', '>', 0)
                    ->select('id', 'name', 'reseller_name', 'days_left', 'stage')
                    ->get();

                if ($warningLeads->isEmpty()) continue;

                // Batch-fetch reseller email + id for all warning leads — avoids N+1
                $resellerNames  = $warningLeads->pluck('reseller_name')->unique()->filter()->values()->toArray();
                $resellerByName = DB::table('resellers')
                    ->where('tenant_id', $tenantId)
                    ->whereIn('name', $resellerNames)
                    ->get(['id', 'name', 'email'])
                    ->keyBy('name');

                $today = now()->format('Y-m-d');

                foreach ($warningLeads as $lead) {
                    // Create one in-app notification per active admin/manager for this tenant
                    foreach ($adminIds as $adminId) {
                        // Dedup via indexed deduplication_key — avoids non-sargable metadata_json scan
                        $dedupKey    = "pipeline_warning.{$tenantId}.{$lead->id}.{$adminId}.{$today}";
                        $alreadySent = DB::table('notifications')
                            ->where('tenant_id', $tenantId)
                            ->where('deduplication_key', $dedupKey)
                            ->exists();
                        if ($alreadySent) continue;

                        DB::table('notifications')->insert([
                            'id'                => (string) Str::uuid(),
                            'tenant_id'         => $tenantId,
                            'notifiable_type'   => 'tenant_admin',
                            'notifiable_id'     => $adminId,
                            'category'          => 'deal_pipeline',
                            'type'              => 'warning',
                            'priority'          => $lead->days_left <= 1 ? 'critical' : 'high',
                            'message'           => "Deal \"{$lead->name}\" (assigned to {$lead->reseller_name}) has {$lead->days_left} day(s) left in the {$rule->stage} stage.",
                            'action_url'        => "/tenant/{$tenantId}/deals?status=expiring",
                            'channel'           => 'in_app',
                            'frequency_type'    => 'instant',
                            'deduplication_key' => $dedupKey,
                            'metadata_json'     => json_encode([
                                'type'          => 'pipeline_stage_warning',
                                'lead_id'       => $lead->id,
                                'stage'         => $rule->stage,
                                'days_left'     => $lead->days_left,
                                'reseller_name' => $lead->reseller_name,
                            ]),
                            'is_read'           => false,
                            'is_dismissed'      => false,
                            'sent_at'           => now(),
                            'created_at'        => now(),
                        ]);
                        $warned++;
                    }

                    // Notify reseller via email — use batched lookup
                    $resellerRow   = $resellerByName[$lead->reseller_name] ?? null;
                    $resellerEmail = $resellerRow?->email ?? null;
                    $resellerId    = $resellerRow?->id    ?? null;

                    if ($resellerEmail) {
                        $urgency = $lead->days_left <= 1 ? 'URGENT: ' : '';
                        $queued  = EmailLogger::send(
                            mailable:      new PipelineStageWarning(
                                resellerName:  $lead->reseller_name,
                                resellerEmail: $resellerEmail,
                                tenantName:    $tenantName,
                                dealName:      $lead->name,
                                stage:         $rule->stage,
                                daysLeft:      $lead->days_left,
                                loginUrl:      url('/reseller/login'),
                            ),
                            recipientEmail: $resellerEmail,
                            recipientType:  'reseller',
                            emailKey:       'pipeline_warning.' . $tenantId . '.' . $lead->id . '.' . substr(md5($lead->reseller_name), 0, 12),
                            subject:        "{$urgency}\"{$lead->name}\" needs your attention — {$lead->days_left}d left",
                            recipientId:    $resellerId ? (string) $resellerId : null,
                            tenantId:       $tenantId,
                            dailyDedup:     true,
                        );
                        if ($queued) $notified++;
                    }
                }
            }
        }

        $this->info("Pipeline warnings: {$warned} notifications created, {$notified} emails sent.");

        return self::SUCCESS;
    }
}
