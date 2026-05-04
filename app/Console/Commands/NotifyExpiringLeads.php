<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class NotifyExpiringLeads extends Command
{
    protected $signature   = 'leads:notify-expiring';
    protected $description = 'Send daily in-app notifications for expiring leads to tenant admins and resellers';

    public function handle(): int
    {
        $today = now()->format('Y-m-d');

        // All expiring leads grouped by tenant
        $byTenant = DB::table('leads')
            ->where('status', 'expiring')
            ->select('tenant_id', 'name', 'reseller_name', 'days_left', 'deal_value')
            ->orderBy('tenant_id')
            ->orderBy('days_left')
            ->get()
            ->groupBy('tenant_id');

        $tenantCount   = 0;
        $resellerCount = 0;

        foreach ($byTenant as $tenantId => $leads) {
            $count = $leads->count();

            // ── Tenant admin notification ────────────────────────────
            $alreadySent = DB::table('notifications')
                ->where('tenant_id', $tenantId)
                ->whereRaw("metadata_json->>'type' = 'tenant_expiring_daily'")
                ->whereRaw("metadata_json->>'date' = ?", [$today])
                ->exists();

            if (!$alreadySent) {
                DB::table('notifications')->insert([
                    'id'            => (string) Str::uuid(),
                    'tenant_id'     => $tenantId,
                    'category'      => 'system',
                    'type'          => 'warning',
                    'priority'      => $count >= 5 ? 'critical' : 'high',
                    'message'       => "{$count} deal" . ($count > 1 ? 's are' : ' is') . " expiring soon — review and take action before they expire.",
                    'action_url'    => "/tenant/{$tenantId}/deals?status=expiring",
                    'channel'       => 'in_app',
                    'frequency_type'=> 'daily',
                    'metadata_json' => json_encode([
                        'type'  => 'tenant_expiring_daily',
                        'count' => $count,
                        'date'  => $today,
                    ]),
                    'is_read'       => false,
                    'is_dismissed'  => false,
                    'sent_at'       => now(),
                    'created_at'    => now(),
                ]);
                $tenantCount++;
            }

            // ── Reseller notifications (one per reseller) ────────────
            $byReseller = $leads
                ->filter(fn($l) => !empty($l->reseller_name))
                ->groupBy('reseller_name');

            foreach ($byReseller as $resellerName => $resellerLeads) {
                $rCount = $resellerLeads->count();

                // Look up reseller email from resellers table
                $reseller = DB::table('resellers')
                    ->where('tenant_id', $tenantId)
                    ->where('name', $resellerName)
                    ->select('id', 'email')
                    ->first();

                $alreadySentReseller = DB::table('notifications')
                    ->where('tenant_id', $tenantId)
                    ->whereRaw("metadata_json->>'type' = 'reseller_expiring_daily'")
                    ->whereRaw("metadata_json->>'reseller_name' = ?", [$resellerName])
                    ->whereRaw("metadata_json->>'date' = ?", [$today])
                    ->exists();

                if (!$alreadySentReseller) {
                    $encodedName = urlencode($resellerName);
                    DB::table('notifications')->insert([
                        'id'            => (string) Str::uuid(),
                        'tenant_id'     => $tenantId,
                        'category'      => 'system',
                        'type'          => 'warning',
                        'priority'      => 'high',
                        'message'       => "You have {$rCount} deal" . ($rCount > 1 ? 's' : '') . " expiring soon. Take action to keep them active.",
                        'action_url'    => "/tenant/{$tenantId}/deals?status=expiring&reseller_name={$encodedName}",
                        'channel'       => 'in_app',
                        'frequency_type'=> 'daily',
                        'metadata_json' => json_encode([
                            'type'          => 'reseller_expiring_daily',
                            'reseller_name' => $resellerName,
                            'reseller_id'   => $reseller?->id,
                            'reseller_email'=> $reseller?->email,
                            'count'         => $rCount,
                            'date'          => $today,
                        ]),
                        'is_read'       => false,
                        'is_dismissed'  => false,
                        'sent_at'       => now(),
                        'created_at'    => now(),
                    ]);
                    $resellerCount++;
                }
            }
        }

        $this->info("Tenant notifications: {$tenantCount} | Reseller notifications: {$resellerCount}");

        return self::SUCCESS;
    }
}
