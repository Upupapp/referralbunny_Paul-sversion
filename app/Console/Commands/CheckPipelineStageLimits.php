<?php

namespace App\Console\Commands;

use App\Mail\PipelineStageWarning;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
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

                foreach ($warningLeads as $lead) {
                    // Create one in-app notification per active admin/manager for this tenant
                    foreach ($adminIds as $adminId) {
                        DB::table('notifications')->insert([
                            'id'             => (string) Str::uuid(),
                            'tenant_id'      => $tenantId,
                            'notifiable_type'=> 'App\\Models\\TenantUser',
                            'notifiable_id'  => $adminId,
                            'category'       => 'system',
                            'type'           => 'warning',
                            'priority'       => $lead->days_left <= 1 ? 'critical' : 'high',
                            'message'        => "Deal \"{$lead->name}\" (assigned to {$lead->reseller_name}) has {$lead->days_left} day(s) left in the {$rule->stage} stage.",
                            'action_url'     => "/tenant/{$tenantId}/deals?status=expiring",
                            'channel'        => 'in_app',
                            'frequency_type' => 'instant',
                            'metadata_json'  => json_encode([
                                'type'          => 'pipeline_stage_warning',
                                'lead_id'       => $lead->id,
                                'stage'         => $rule->stage,
                                'days_left'     => $lead->days_left,
                                'reseller_name' => $lead->reseller_name,
                            ]),
                            'is_read'        => false,
                            'is_dismissed'   => false,
                            'sent_at'        => now(),
                            'created_at'     => now(),
                        ]);
                        $warned++;
                    }

                    // Notify reseller via email (if they have one)
                    $resellerEmail = DB::table('resellers')
                        ->where('tenant_id', $tenantId)
                        ->where('name', $lead->reseller_name)
                        ->value('email');

                    if ($resellerEmail) {
                        try {
                            Mail::send(new PipelineStageWarning(
                                resellerName:  $lead->reseller_name,
                                resellerEmail: $resellerEmail,
                                tenantName:    $tenantName,
                                dealName:      $lead->name,
                                stage:         $rule->stage,
                                daysLeft:      $lead->days_left,
                                loginUrl:      url('/reseller/login'),
                            ));
                            $notified++;
                        } catch (\Throwable $e) {
                            Log::warning("Pipeline warning email failed: {$e->getMessage()}");
                        }
                    }
                }
            }
        }

        $this->info("Pipeline warnings: {$warned} notifications created, {$notified} emails sent.");

        return self::SUCCESS;
    }
}
