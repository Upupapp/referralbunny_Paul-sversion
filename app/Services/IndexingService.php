<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PromoCode;
use App\Models\Promotion;
use App\Models\ApprovalRequest;
use App\Models\Subscription;
use App\Models\Notification;
use App\Models\Lead;
use App\Models\Reseller;
use App\Models\Partner;
use App\Models\Contact;
use App\Models\ReindexJob;
use Illuminate\Support\Facades\DB;

class IndexingService
{
    // Entity type → priority weight (lower = higher priority in results)
    private const ENTITY_PRIORITY = [
        'tenant'           => 1,
        'approval_request' => 2,
        'invoice'          => 3,
        'payment'          => 4,
        'promo_code'       => 5,
        'promotion'        => 6,
        'subscription'     => 7,
        'lead'             => 8,
        'reseller'         => 9,
        'notification'     => 10,
    ];

    public function reindexAll(): array
    {
        $results = [];
        $types = [
            'tenant', 'lead', 'reseller', 'partner', 'admin', 'contact', 'organization',
            'invoice', 'payment', 'promo_code', 'promotion', 'approval_request', 'subscription', 'notification',
        ];

        foreach ($types as $type) {
            try {
                $results[$type] = $this->reindexType($type);
            } catch (\Throwable $e) {
                $results[$type] = 0;
                \Illuminate\Support\Facades\Log::warning("[IndexingService] reindexAll skipped {$type}: " . $e->getMessage());
            }
        }

        return $results;
    }

    public function reindexType(string $entityType): int
    {
        // Create/update job tracking row safely
        $job = null;
        try {
            $job = ReindexJob::updateOrCreate(
                ['entity_type' => $entityType],
                ['status' => 'running', 'last_run' => now(), 'error' => null]
            );
        } catch (\Throwable) {
            // reindex_jobs table may not exist yet — proceed without tracking
        }

        try {
            DB::table('search_index')->where('entity_type', $entityType)->delete();

            $count = match ($entityType) {
                'tenant'           => $this->indexTenants(),
                'invoice'          => $this->indexInvoices(),
                'payment'          => $this->indexPayments(),
                'promo_code'       => $this->indexPromoCodes(),
                'promotion'        => $this->indexPromotions(),
                'approval_request' => $this->indexApprovals(),
                'subscription'     => $this->indexSubscriptions(),
                'lead'             => $this->indexLeads(),
                'reseller'         => $this->indexResellers(),
                'partner'          => $this->indexPartners(),
                'admin'            => $this->indexAdmins(),
                'contact'          => $this->indexContacts(),
                'organization'     => $this->indexOrganizations(),
                'notification'     => $this->indexNotifications(),
                default            => 0,
            };

            $job?->update(['status' => 'completed', 'records_indexed' => $count]);
            return $count;
        } catch (\Throwable $e) {
            $job?->update(['status' => 'failed', 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function indexSingle(string $entityType, string $entityId): void
    {
        $record = $this->buildRecord($entityType, $entityId);
        if (!$record) return;

        DB::table('search_index')->upsert(
            [$record],
            ['entity_type', 'entity_id'],
            ['title','description','keywords','tags','status','url','tenant_id','relationships_json','searchable_text','last_activity_at','updated_at']
        );
    }

    // ── Private indexers ──────────────────────────────────────

    private function indexTenants(): int
    {
        $count = 0;
        Tenant::chunk(200, function ($tenants) use (&$count) {
            $rows = $tenants->map(fn($t) => [
                'entity_type'       => 'tenant',
                'entity_id'         => $t->id,
                'title'             => $t->name,
                'description'       => ($t->industry ?? 'No industry') . ' · ' . $t->status . ' · ' . $t->program_name,
                'keywords'          => implode(' ', array_filter([$t->admin_email, $t->contact_email, $t->contact_person, $t->slug, $t->business_name])),
                'tags'              => '{' . $t->status . ',' . ($t->industry ? str_replace(' ', '_', strtolower($t->industry)) : 'no_industry') . '}',
                'status'            => $t->status,
                'url'               => '/platform/tenants/' . $t->id,
                'tenant_id'         => $t->id,
                'relationships_json'=> json_encode(['tenant_id' => $t->id]),
                'searchable_text'   => implode(' ', array_filter([$t->name, $t->program_name, $t->industry, $t->admin_email, $t->contact_email, $t->contact_person, $t->business_name, $t->status, $t->slug])),
                'last_activity_at'  => $t->updated_at,
                'created_at'        => now(),
                'updated_at'        => now(),
            ])->toArray();
            DB::table('search_index')->insert($rows);
            $count += count($rows);
        });
        return $count;
    }

    private function indexInvoices(): int
    {
        $count = 0;
        Invoice::with('tenant')->chunk(500, function ($invoices) use (&$count) {
            $rows = $invoices->map(fn($inv) => [
                'entity_type'       => 'invoice',
                'entity_id'         => $inv->id,
                'title'             => $inv->invoice_number,
                'description'       => ($inv->tenant?->name ?? '—') . ' · ₱' . number_format($inv->final_amount, 2) . ' · ' . $inv->status,
                'keywords'          => $inv->notes ?? '',
                'tags'              => '{' . $inv->status . ',invoice}',
                'status'            => $inv->status,
                'url'               => '/platform/billing?tab=0',
                'tenant_id'         => $inv->tenant_id,
                'relationships_json'=> json_encode(['tenant_id' => $inv->tenant_id, 'subscription_id' => $inv->subscription_id]),
                'searchable_text'   => implode(' ', array_filter([$inv->invoice_number, $inv->tenant?->name, $inv->status, (string) $inv->final_amount])),
                'last_activity_at'  => $inv->updated_at,
                'created_at'        => now(),
                'updated_at'        => now(),
            ])->toArray();
            DB::table('search_index')->insert($rows);
            $count += count($rows);
        });
        return $count;
    }

    private function indexPayments(): int
    {
        $count = 0;
        Payment::with('tenant')->chunk(500, function ($payments) use (&$count) {
            $rows = $payments->map(fn($p) => [
                'entity_type'       => 'payment',
                'entity_id'         => $p->id,
                'title'             => $p->external_payment_id ?? ('Payment #' . substr($p->id, 0, 8)),
                'description'       => ($p->tenant?->name ?? '—') . ' · ₱' . number_format($p->base_amount_php, 2) . ' · ' . $p->status,
                'keywords'          => implode(' ', array_filter([$p->payment_method, $p->provider, $p->failure_reason])),
                'tags'              => '{' . $p->status . ',payment}',
                'status'            => $p->status,
                'url'               => '/platform/billing?tab=0',
                'tenant_id'         => $p->tenant_id,
                'relationships_json'=> json_encode(['tenant_id' => $p->tenant_id, 'invoice_id' => $p->invoice_id]),
                'searchable_text'   => implode(' ', array_filter([$p->external_payment_id, $p->tenant?->name, $p->status, $p->payment_method, (string) $p->base_amount_php])),
                'last_activity_at'  => $p->updated_at,
                'created_at'        => now(),
                'updated_at'        => now(),
            ])->toArray();
            DB::table('search_index')->insert($rows);
            $count += count($rows);
        });
        return $count;
    }

    private function indexPromoCodes(): int
    {
        $count = 0;
        PromoCode::chunk(200, function ($codes) use (&$count) {
            $rows = $codes->map(fn($c) => [
                'entity_type'       => 'promo_code',
                'entity_id'         => $c->id,
                'title'             => $c->code,
                'description'       => $c->name . ' · ' . $c->discount_value . ($c->discount_type === 'percentage' ? '%' : ' PHP') . ' · ' . $c->status,
                'keywords'          => $c->description ?? '',
                'tags'              => '{' . $c->status . ',promo_code}',
                'status'            => $c->status,
                'url'               => '/platform/billing?tab=2',
                'tenant_id'         => null,
                'relationships_json'=> json_encode([]),
                'searchable_text'   => implode(' ', array_filter([$c->code, $c->name, $c->status, $c->discount_type, (string) $c->discount_value])),
                'last_activity_at'  => $c->updated_at,
                'created_at'        => now(),
                'updated_at'        => now(),
            ])->toArray();
            DB::table('search_index')->insert($rows);
            $count += count($rows);
        });
        return $count;
    }

    private function indexPromotions(): int
    {
        $count = 0;
        Promotion::chunk(200, function ($promotions) use (&$count) {
            $rows = $promotions->map(fn($p) => [
                'entity_type'       => 'promotion',
                'entity_id'         => $p->id,
                'title'             => $p->name,
                'description'       => $p->promotion_type . ' · ' . $p->discount_value . ($p->discount_type === 'percentage' ? '%' : ' PHP') . ' · ' . $p->status,
                'keywords'          => $p->description ?? '',
                'tags'              => '{' . $p->status . ',promotion}',
                'status'            => $p->status,
                'url'               => '/platform/billing?tab=3',
                'tenant_id'         => null,
                'relationships_json'=> json_encode([]),
                'searchable_text'   => implode(' ', array_filter([$p->name, $p->status, $p->promotion_type, $p->target_scope])),
                'last_activity_at'  => $p->updated_at,
                'created_at'        => now(),
                'updated_at'        => now(),
            ])->toArray();
            DB::table('search_index')->insert($rows);
            $count += count($rows);
        });
        return $count;
    }

    private function indexApprovals(): int
    {
        $count = 0;
        ApprovalRequest::chunk(200, function ($approvals) use (&$count) {
            $rows = $approvals->map(fn($a) => [
                'entity_type'       => 'approval_request',
                'entity_id'         => $a->id,
                'title'             => ucwords(str_replace('_', ' ', $a->request_type)),
                'description'       => $a->status . ' · ref: ' . ($a->reference_type ?? '—') . ' · ' . ($a->notes ?? 'No notes'),
                'keywords'          => $a->reference_id ?? '',
                'tags'              => '{' . $a->status . ',approval}',
                'status'            => $a->status,
                'url'               => '/platform/billing?tab=6',
                'tenant_id'         => null,
                'relationships_json'=> json_encode(['reference_id' => $a->reference_id, 'reference_type' => $a->reference_type]),
                'searchable_text'   => implode(' ', array_filter([$a->request_type, $a->status, $a->reference_type, $a->notes])),
                'last_activity_at'  => $a->approved_at ?? $a->created_at,
                'created_at'        => now(),
                'updated_at'        => now(),
            ])->toArray();
            DB::table('search_index')->insert($rows);
            $count += count($rows);
        });
        return $count;
    }

    private function indexSubscriptions(): int
    {
        $count = 0;
        \App\Models\Subscription::with(['tenant', 'plan'])->chunk(200, function ($subs) use (&$count) {
            $rows = $subs->map(fn($s) => [
                'entity_type'       => 'subscription',
                'entity_id'         => $s->id,
                'title'             => ($s->tenant?->name ?? '—') . ' — ' . ($s->plan?->name ?? 'No plan'),
                'description'       => $s->status . ' · ' . $s->billing_cycle . ' · ' . ($s->trial_end_date ? 'Trial until ' . $s->trial_end_date : ''),
                'keywords'          => '',
                'tags'              => '{' . $s->status . ',subscription}',
                'status'            => $s->status,
                'url'               => '/platform/billing?tab=0',
                'tenant_id'         => $s->tenant_id,
                'relationships_json'=> json_encode(['tenant_id' => $s->tenant_id, 'plan_id' => $s->plan_id]),
                'searchable_text'   => implode(' ', array_filter([$s->tenant?->name, $s->plan?->name, $s->status, $s->billing_cycle])),
                'last_activity_at'  => $s->updated_at,
                'created_at'        => now(),
                'updated_at'        => now(),
            ])->toArray();
            DB::table('search_index')->insert($rows);
            $count += count($rows);
        });
        return $count;
    }

    private function indexLeads(): int
    {
        $count = 0;
        Lead::with('tenant')->chunk(500, function ($leads) use (&$count) {
            $rows = $leads->map(fn($l) => [
                'entity_type'       => 'lead',
                'entity_id'         => $l->id,
                'title'             => $l->name,
                'description'       => ($l->tenant?->name ?? '—') . ' · ' . str_replace('_', ' ', $l->stage) . ' · ₱' . number_format($l->deal_value),
                'keywords'          => $l->reseller_name ?? '',
                'tags'              => '{' . $l->status . ',' . $l->stage . ',lead}',
                'status'            => $l->status,
                'url'               => '/tenant/' . $l->tenant_id . '/deals/' . $l->id,
                'tenant_id'         => $l->tenant_id,
                'relationships_json'=> json_encode(['tenant_id' => $l->tenant_id, 'reseller' => $l->reseller_name]),
                'searchable_text'   => implode(' ', array_filter([$l->name, $l->reseller_name, $l->stage, $l->status, $l->tenant?->name])),
                'last_activity_at'  => $l->updated_at,
                'created_at'        => now(),
                'updated_at'        => now(),
            ])->toArray();
            DB::table('search_index')->insert($rows);
            $count += count($rows);
        });
        return $count;
    }

    private function indexResellers(): int
    {
        $count = 0;
        Reseller::with('tenant')->chunk(500, function ($resellers) use (&$count) {
            $rows = $resellers->map(fn($r) => [
                'entity_type'       => 'reseller',
                'entity_id'         => $r->id,
                'title'             => $r->name,
                'description'       => ($r->tenant?->name ?? '—') . ' · ' . ($r->territory ?? 'No territory') . ' · ' . $r->status,
                'keywords'          => $r->email,
                'tags'              => '{' . $r->status . ',reseller}',
                'status'            => $r->status,
                'url'               => '/tenant/' . $r->tenant_id . '/referrers/' . $r->id,
                'tenant_id'         => $r->tenant_id,
                'relationships_json'=> json_encode(['tenant_id' => $r->tenant_id]),
                'searchable_text'   => implode(' ', array_filter([$r->name, $r->email, $r->territory, $r->status, $r->tenant?->name])),
                'last_activity_at'  => $r->updated_at,
                'created_at'        => now(),
                'updated_at'        => now(),
            ])->toArray();
            DB::table('search_index')->insert($rows);
            $count += count($rows);
        });
        return $count;
    }

    private function indexPartners(): int
    {
        $count = 0;
        Partner::with('tenant')->chunk(500, function ($partners) use (&$count) {
            $rows = $partners->map(fn($p) => [
                'entity_type'       => 'partner',
                'entity_id'         => (string) $p->id,
                'title'             => $p->display_name,
                'description'       => ($p->tenant?->name ?? '—') . ' · ' . $p->status,
                'keywords'          => $p->email,
                'tags'              => '{' . $p->status . ',partner}',
                'status'            => $p->status,
                'url'               => '/tenant/' . $p->tenant_id . '/partners',
                'tenant_id'         => $p->tenant_id,
                'relationships_json'=> json_encode(['tenant_id' => $p->tenant_id]),
                'searchable_text'   => implode(' ', array_filter([$p->display_name, $p->email, $p->status, $p->tenant?->name])),
                'last_activity_at'  => $p->updated_at,
                'created_at'        => now(),
                'updated_at'        => now(),
            ])->toArray();
            DB::table('search_index')->insert($rows);
            $count += count($rows);
        });
        return $count;
    }

    private function indexAdmins(): int
    {
        $rows = DB::table('tenant_memberships as tm')
            ->join('users as u', 'u.id', '=', 'tm.user_id')
            ->join('tenants as t', 't.id', '=', 'tm.tenant_id')
            ->whereIn('tm.role', ['owner', 'admin', 'manager'])
            ->whereNull('u.deleted_at')
            ->select('u.id', 'tm.tenant_id', 'u.name', 'u.email', 'tm.role', 't.name as tenant_name', 'u.updated_at')
            ->get()
            ->map(fn($r) => [
                'entity_type'       => 'admin',
                'entity_id'         => $r->id . ':' . $r->tenant_id,
                'title'             => $r->name,
                'description'       => $r->tenant_name . ' · ' . ucfirst($r->role),
                'keywords'          => $r->email,
                'tags'              => '{admin,' . $r->role . '}',
                'status'            => 'active',
                'url'               => '/tenant/' . $r->tenant_id . '/settings/team',
                'tenant_id'         => $r->tenant_id,
                'relationships_json'=> json_encode(['tenant_id' => $r->tenant_id, 'role' => $r->role]),
                'searchable_text'   => implode(' ', array_filter([$r->name, $r->email, $r->role, $r->tenant_name])),
                'last_activity_at'  => $r->updated_at,
                'created_at'        => now(),
                'updated_at'        => now(),
            ])
            ->toArray();

        if (empty($rows)) return 0;
        DB::table('search_index')->insert($rows);
        return count($rows);
    }

    private function indexContacts(): int
    {
        $count = 0;
        Contact::with('tenant')->chunk(500, function ($contacts) use (&$count) {
            $rows = $contacts->map(fn($c) => [
                'entity_type'       => 'contact',
                'entity_id'         => (string) $c->id,
                'title'             => trim(($c->first_name ?? '') . ' ' . ($c->last_name ?? '')),
                'description'       => ($c->tenant?->name ?? '—') . ' · ' . ($c->job_title ?? '') . ($c->company_or_organization ? ' · ' . $c->company_or_organization : ''),
                'keywords'          => implode(' ', array_filter([$c->email, $c->phone])),
                'tags'              => '{contact}',
                'status'            => $c->status ?? 'active',
                'url'               => '/tenant/' . $c->tenant_id . '/contacts/' . $c->id,
                'tenant_id'         => $c->tenant_id,
                'relationships_json'=> json_encode(['tenant_id' => $c->tenant_id, 'organization_id' => $c->organization_id]),
                'searchable_text'   => implode(' ', array_filter([$c->first_name, $c->last_name, $c->email, $c->job_title, $c->company_or_organization, $c->department, $c->tenant?->name])),
                'last_activity_at'  => $c->updated_at,
                'created_at'        => now(),
                'updated_at'        => now(),
            ])->toArray();
            DB::table('search_index')->insert($rows);
            $count += count($rows);
        });
        return $count;
    }

    private function indexOrganizations(): int
    {
        try {
            $count = 0;
            \App\Models\Organization::with('tenant')->chunk(500, function ($orgs) use (&$count) {
                $rows = $orgs->map(fn($o) => [
                    'entity_type'       => 'organization',
                    'entity_id'         => (string) $o->id,
                    'title'             => $o->name,
                    'description'       => ($o->tenant?->name ?? '—') . ($o->industry ? ' · ' . $o->industry : ''),
                    'keywords'          => implode(' ', array_filter([$o->website, $o->email])),
                    'tags'              => '{organization}',
                    'status'            => $o->status ?? 'active',
                    'url'               => '/tenant/' . $o->tenant_id . '/contacts?org=' . $o->id,
                    'tenant_id'         => $o->tenant_id,
                    'relationships_json'=> json_encode(['tenant_id' => $o->tenant_id]),
                    'searchable_text'   => implode(' ', array_filter([$o->name, $o->industry, $o->website, $o->email, $o->tenant?->name])),
                    'last_activity_at'  => $o->updated_at,
                    'created_at'        => now(),
                    'updated_at'        => now(),
                ])->toArray();
                DB::table('search_index')->insert($rows);
                $count += count($rows);
            });
            return $count;
        } catch (\Throwable) {
            return 0;
        }
    }

    private function indexNotifications(): int
    {
        $rows = Notification::with('tenant')->where('is_dismissed', false)->latest()->limit(500)->get()->map(fn($n) => [
            'entity_type'       => 'notification',
            'entity_id'         => $n->id,
            'title'             => Str()->limit($n->message, 60),
            'description'       => $n->category . ' · ' . $n->priority . ' · ' . ($n->is_read ? 'read' : 'unread'),
            'keywords'          => $n->action_url ?? '',
            'tags'              => '{' . $n->priority . ',' . $n->category . ',notification}',
            'status'            => $n->is_read ? 'read' : 'unread',
            'url'               => $n->action_url ?? '/platform/dashboard',
            'tenant_id'         => $n->tenant_id,
            'relationships_json'=> json_encode(['tenant_id' => $n->tenant_id]),
            'searchable_text'   => implode(' ', array_filter([$n->message, $n->category, $n->priority, $n->tenant?->name])),
            'last_activity_at'  => $n->created_at,
            'created_at'        => now(),
            'updated_at'        => now(),
        ])->toArray();

        if (empty($rows)) return 0;
        DB::table('search_index')->insert($rows);
        return count($rows);
    }

    private function buildRecord(string $entityType, string $entityId): ?array
    {
        // Used for single-entity re-index — route to correct indexer
        return null; // Full implementation deferred to batch indexers
    }
}
