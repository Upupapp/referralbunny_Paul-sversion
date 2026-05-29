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
            ->whereNull('deleted_at')
            ->select('tenant_id', 'name', 'reseller_name', 'days_left', 'deal_value')
            ->orderBy('tenant_id')
            ->orderBy('days_left')
            ->get()
            ->groupBy('tenant_id');

        $tenantCount   = 0;
        $resellerCount = 0;

        foreach ($byTenant as $tenantId => $leads) {
            $count = $leads->count();

            // Resolve admin recipients once per tenant
            $adminIds = DB::table('tenant_memberships as tm')
                ->join('tenant_users as u', 'tm.tenant_user_id', '=', 'u.id')
                ->where('tm.tenant_id', $tenantId)
                ->where('tm.status', 'active')
                ->whereIn('tm.role', ['owner', 'admin', 'manager'])
                ->pluck('u.id')
                ->toArray();

            // ── Tenant admin notification (one per admin, daily dedup per user) ──
            foreach ($adminIds as $adminId) {
                $alreadySent = DB::table('notifications')
                    ->where('tenant_id', $tenantId)
                    ->where('notifiable_id', $adminId)
                    ->whereRaw("metadata_json->>'type' = 'tenant_expiring_daily'")
                    ->whereRaw("metadata_json->>'date' = ?", [$today])
                    ->exists();

                if (!$alreadySent) {
                    DB::table('notifications')->insert([
                        'id'             => (string) Str::uuid(),
                        'tenant_id'      => $tenantId,
                        'notifiable_type'=> 'tenant_admin',
                        'notifiable_id'  => $adminId,
                        'category'       => 'system',
                        'type'           => 'warning',
                        'priority'       => $count >= 5 ? 'critical' : 'high',
                        'message'        => "{$count} deal" . ($count > 1 ? 's are' : ' is') . " expiring soon — review and take action before they expire.",
                        'action_url'     => "/tenant/{$tenantId}/deals?status=expiring",
                        'channel'        => 'in_app',
                        'frequency_type' => 'daily',
                        'metadata_json'  => json_encode([
                            'type'  => 'tenant_expiring_daily',
                            'count' => $count,
                            'date'  => $today,
                        ]),
                        'is_read'        => false,
                        'is_dismissed'   => false,
                        'sent_at'        => now(),
                        'created_at'     => now(),
                    ]);
                    $tenantCount++;
                }
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
                    ->whereRaw('LOWER(name) = ?', [strtolower($resellerName)])
                    ->whereNull('deleted_at')
                    ->select('id', 'email')
                    ->first();

                $alreadySentReseller = DB::table('notifications')
                    ->where('tenant_id', $tenantId)
                    ->whereRaw("metadata_json->>'type' = 'reseller_expiring_daily'")
                    ->whereRaw("metadata_json->>'reseller_name' = ?", [$resellerName])
                    ->whereRaw("metadata_json->>'date' = ?", [$today])
                    ->exists();

                // Skip if no reseller record found — cannot identify recipient
                if ($reseller?->id && !$alreadySentReseller) {
                    $encodedName = urlencode($resellerName);
                    DB::table('notifications')->insert([
                        'id'             => (string) Str::uuid(),
                        'tenant_id'      => $tenantId,
                        'notifiable_type'=> 'reseller',
                        'notifiable_id'  => $reseller->id,
                        'category'       => 'system',
                        'type'           => 'warning',
                        'priority'       => 'high',
                        'message'        => "You have {$rCount} deal" . ($rCount > 1 ? 's' : '') . " expiring soon. Take action to keep them active.",
                        'action_url'     => "/tenant/{$tenantId}/deals?status=expiring&reseller_name={$encodedName}",
                        'channel'        => 'in_app',
                        'frequency_type' => 'daily',
                        'metadata_json'  => json_encode([
                            'type'          => 'reseller_expiring_daily',
                            'reseller_name' => $resellerName,
                            'reseller_id'   => $reseller->id,
                            'reseller_email'=> $reseller->email,
                            'count'         => $rCount,
                            'date'          => $today,
                        ]),
                        'is_read'        => false,
                        'is_dismissed'   => false,
                        'sent_at'        => now(),
                        'created_at'     => now(),
                    ]);
                    $resellerCount++;
                }
            }
        }

        // ── Partner notifications (one digest per active Partner) ────────
        $partnerCount = 0;

        $partnerDeals = DB::table('deal_partner_splits as dps')
            ->join('leads as l', function($j) {
                $j->on(DB::raw('l.id::text'), '=', 'dps.deal_id')
                  ->on('l.tenant_id', '=', 'dps.tenant_id');
            })
            ->where('l.status', 'expiring')
            ->where('dps.status', 'active')
            ->whereNotNull('dps.partner_user_id')
            ->whereNull('dps.deleted_at')
            ->select('dps.tenant_id', 'dps.partner_user_id', 'dps.partner_name', 'l.name as deal_name', 'l.days_left')
            ->orderBy('dps.tenant_id')
            ->orderBy('l.days_left')
            ->get()
            ->groupBy(fn($r) => $r->tenant_id . ':' . $r->partner_user_id);

        foreach ($partnerDeals as $groupKey => $rows) {
            $first    = $rows->first();
            $tenantId = $first->tenant_id;
            $partnerId= $first->partner_user_id;
            $pCount   = $rows->count();

            $alreadySentPartner = DB::table('notifications')
                ->where('tenant_id', $tenantId)
                ->whereRaw("notifiable_type = 'partner'")
                ->whereRaw("notifiable_id = ?", [$partnerId])
                ->whereRaw("metadata_json->>'type' = 'partner_expiring_daily'")
                ->whereRaw("metadata_json->>'date' = ?", [$today])
                ->exists();

            if (!$alreadySentPartner) {
                DB::table('notifications')->insert([
                    'id'            => (string) \Illuminate\Support\Str::uuid(),
                    'tenant_id'     => $tenantId,
                    'notifiable_type'=> 'partner',
                    'notifiable_id' => $partnerId,
                    'category'      => 'deal_pipeline',
                    'type'          => 'warning',
                    'priority'      => $pCount >= 3 ? 'high' : 'normal',
                    'title'         => "Deal" . ($pCount > 1 ? 's' : '') . " expiring soon",
                    'message'       => "You have {$pCount} deal" . ($pCount > 1 ? 's' : '') . " expiring soon that you are associated with. Take action before they expire.",
                    'action_url'    => "/partner/dashboard",
                    'channel'       => 'in_app',
                    'frequency_type'=> 'daily',
                    'metadata_json' => json_encode([
                        'type'       => 'partner_expiring_daily',
                        'partner_id' => $partnerId,
                        'count'      => $pCount,
                        'date'       => $today,
                    ]),
                    'is_read'       => false,
                    'is_dismissed'  => false,
                    'sent_at'       => now(),
                    'created_at'    => now(),
                ]);
                $partnerCount++;
            }
        }

        $this->info("Tenant notifications: {$tenantCount} | Reseller notifications: {$resellerCount} | Partner notifications: {$partnerCount}");

        return self::SUCCESS;
    }
}
