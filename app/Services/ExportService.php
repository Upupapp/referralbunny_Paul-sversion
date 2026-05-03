<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\TenantMetric;
use App\Models\Notification;
use App\Models\Lead;
use Carbon\Carbon;

class ExportService
{
    // ── CSV export ────────────────────────────────────────────

    public function tenantHealthCsv(?string $industry = null, ?string $healthLevel = null): string
    {
        $query = TenantMetric::with('tenant');

        if ($healthLevel) $query->where('health_level', $healthLevel);
        if ($industry) {
            $query->whereHas('tenant', fn($q) => $q->where('industry', $industry));
        }

        $rows   = $query->get();
        $output = $this->csvRow(['Tenant', 'Industry', 'Health Score', 'Health Level',
            'Leads', 'Stale Leads', 'Setup %', 'Subscription', 'Payment Status',
            'Trial Days Left', 'Last Activity', 'Created At']);

        foreach ($rows as $row) {
            $output .= $this->csvRow([
                $row->tenant?->name ?? '',
                $row->tenant?->industry ?? '',
                $row->health_score,
                $row->health_level,
                $row->leads_count,
                $row->stale_leads_count,
                $row->setup_completion_percentage . '%',
                $row->subscription_status,
                $row->payment_status,
                $row->trial_days_remaining ?? '',
                $row->last_activity_at?->format('Y-m-d H:i') ?? '',
                $row->created_at->format('Y-m-d'),
            ]);
        }

        return $output;
    }

    public function notificationsCsv(?string $priority = null, ?string $category = null,
        ?string $from = null, ?string $to = null): string
    {
        $query = Notification::with('tenant')->orderByDesc('created_at');
        if ($priority) $query->where('priority', $priority);
        if ($category) $query->where('category', $category);
        if ($from)     $query->where('created_at', '>=', Carbon::parse($from));
        if ($to)       $query->where('created_at', '<=', Carbon::parse($to)->endOfDay());

        $rows   = $query->limit(5000)->get();
        $output = $this->csvRow(['Tenant', 'Category', 'Type', 'Priority',
            'Message', 'Channel', 'Read', 'Created At']);

        foreach ($rows as $row) {
            $output .= $this->csvRow([
                $row->tenant?->name ?? 'Platform',
                $row->category,
                $row->type,
                $row->priority,
                $row->message,
                $row->channel,
                $row->is_read ? 'Yes' : 'No',
                $row->created_at->format('Y-m-d H:i'),
            ]);
        }

        return $output;
    }

    public function leadsCsv(?string $tenantId = null, ?string $stage = null,
        ?string $status = null): string
    {
        $query = Lead::with('tenant')->orderByDesc('created_at');
        if ($tenantId) $query->where('tenant_id', $tenantId);
        if ($stage)    $query->where('stage', $stage);
        if ($status)   $query->where('status', $status);

        $rows   = $query->limit(10000)->get();
        $output = $this->csvRow(['Tenant', 'Lead Name', 'Stage', 'Status',
            'Days Left', 'Reseller', 'Commission Status', 'Deal Value', 'Created At']);

        foreach ($rows as $row) {
            $output .= $this->csvRow([
                $row->tenant?->name ?? '',
                $row->name,
                ucwords(str_replace('_', ' ', $row->stage)),
                $row->status,
                $row->days_left,
                $row->reseller_name,
                $row->commission_status,
                number_format($row->deal_value, 2),
                $row->created_at->format('Y-m-d'),
            ]);
        }

        return $output;
    }

    public function weeklyReportCsv(AnalyticsService $analytics): string
    {
        $data   = $analytics->weeklyDigest();
        $output = $this->csvRow(['Metric', 'Value']);
        foreach ($data as $key => $value) {
            if (!is_array($value)) {
                $output .= $this->csvRow([ucwords(str_replace('_', ' ', $key)), $value]);
            }
        }
        return $output;
    }

    // ── Helpers ───────────────────────────────────────────────

    private function csvRow(array $fields): string
    {
        return implode(',', array_map(fn($f) => '"' . str_replace('"', '""', (string) $f) . '"', $fields)) . "\n";
    }

    public function filename(string $prefix, string $ext = 'csv'): string
    {
        return $prefix . '_' . now()->format('Y-m-d_His') . '.' . $ext;
    }
}
