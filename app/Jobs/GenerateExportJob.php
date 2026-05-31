<?php

namespace App\Jobs;

use App\Models\ExportRequest;
use App\Services\ExportApprovalService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class GenerateExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int    $tries   = 2;
    public int    $timeout = 300;
    public int    $backoff = 60;
    public string $queue   = 'exports';

    public function __construct(
        private string $exportRequestId,
    ) {}

    public function failed(Throwable $e): void
    {
        try {
            $request = ExportRequest::find($this->exportRequestId);
            if ($request && in_array($request->status, ['processing', 'approved', 'pending', 'direct_pending'], true)) {
                app(ExportApprovalService::class)->markFailed($request, 'Job failed permanently: ' . $e->getMessage());
            }
        } catch (\Throwable) {}
    }

    // ── Entry point ───────────────────────────────────────────────────────────

    public function handle(ExportApprovalService $approvalService): void
    {
        $request = ExportRequest::find($this->exportRequestId);

        if (!$request) {
            return;
        }

        // Only process requests that are waiting to be generated
        if (!in_array($request->status, [
            ExportRequest::STATUS_APPROVED,
            ExportRequest::STATUS_PENDING,
            ExportRequest::STATUS_DIRECT_PENDING,
        ], true)) {
            return;
        }

        try {
            $approvalService->markProcessing($request);
            $request->refresh();

            // Referrers export is restricted to tenant admins only
            if ($request->requester_type === 'reseller' && $request->export_type === 'referrers') {
                $approvalService->markFailed($request, 'Referrers export is not available for referrer accounts.');
                return;
            }

            // Generate row data based on export type
            $rows = match ($request->export_type) {
                'deals'          => $this->generateDeals($request),
                'contacts'       => $this->generateContacts($request),
                'organizations'  => $this->generateOrganizations($request),
                'referrers'      => $this->generateReferrers($request),
                'commissions'    => $this->generateCommissions($request),
                'users'          => $this->generateUsers($request),
                'reports'        => $this->generateReports($request),
                'audit_logs',
                'messages',
                'import_summary' => [['Export Type', 'Status', 'Note'], [$request->export_type, 'Not available', 'This export type is not yet implemented. Please contact support.']],
                default          => $this->generateDeals($request),
            };

            // Build CSV content
            $csvContent = $this->buildCsv($rows);

            // Determine storage path and file name
            $shortId  = strtoupper(substr(str_replace('-', '', $request->id), 0, 8));
            $date     = now()->format('Y-m-d');
            $fileName = "{$request->tenant_id}-{$request->export_type}-{$date}-{$shortId}.csv";
            $storagePath = "exports/{$request->tenant_id}/{$request->id}.csv";

            // Write to local storage (disk('local') is never publicly accessible)
            Storage::disk('local')->makeDirectory("exports/{$request->tenant_id}");
            Storage::disk('local')->put($storagePath, $csvContent);

            $fileSize = strlen($csvContent);

            // Retrieve file_expiry_days from tenant settings
            $settings   = $approvalService->getSettings($request->tenant_id);
            $expiryDays = (int) ($settings['file_expiry_days'] ?? 7);

            $approvalService->markReady(
                request:    $request,
                filePath:   $storagePath,
                fileName:   $fileName,
                fileSize:   $fileSize,
                expiryDays: $expiryDays,
            );
        } catch (Throwable $e) {
            $approvalService->markFailed($request, $e->getMessage());
            throw $e; // re-throw so the queue marks the attempt as failed
        }
    }

    // ── CSV builder ───────────────────────────────────────────────────────────

    /**
     * Convert a 2-D array (first row = headers) into a CSV string.
     *
     * @param  array<array<scalar>> $rows
     */
    private function buildCsv(array $rows): string
    {
        $handle = fopen('php://memory', 'r+');

        foreach ($rows as $row) {
            fputcsv($handle, array_map('strval', $row));
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv ?: '';
    }

    // ── Data generators ───────────────────────────────────────────────────────

    /**
     * Deals / Leads export.
     * Referrer-type requesters only see their own deals.
     */
    private function generateDeals(ExportRequest $request): array
    {
        $query = DB::table('leads')
            ->where('tenant_id', $request->tenant_id)
            ->orderByDesc('created_at');

        // Scope-down to only the requester's own deals when the requester is a referrer.
        // Use both reseller_id (authoritative FK) and name-match fallback for legacy rows.
        if ($request->requester_type === 'reseller') {
            $resellerName = DB::table('resellers')
                ->where('id', $request->requester_id)
                ->value('name');

            $query->where(function ($q) use ($request, $resellerName) {
                $q->where('reseller_id', $request->requester_id);
                if ($resellerName) {
                    $q->orWhere('reseller_name', $resellerName);
                }
            });
        }

        // Apply any optional scope filters stored on the request
        $scope = $request->export_scope ?? [];
        if (!empty($scope['stage']))  $query->where('stage', $scope['stage']);
        if (!empty($scope['status'])) $query->where('status', $scope['status']);
        if (!empty($scope['from']))   $query->where('created_at', '>=', $scope['from']);
        if (!empty($scope['to']))     $query->where('created_at', '<=', $scope['to']);

        $rows = $query->limit(50000)->get([
            'id', 'name', 'stage', 'status', 'days_left',
            'reseller_name', 'commission_status', 'deal_value',
            'contract_value', 'base_cost', 'created_at',
        ]);

        $headers = [
            'ID', 'Name', 'Stage', 'Status', 'Days Left',
            'Reseller', 'Commission Status', 'Deal Value',
            'Contract Value', 'Base Cost', 'Created At',
        ];

        $data = [$headers];

        foreach ($rows as $row) {
            $data[] = [
                $row->id ?? '',
                $row->name ?? '',
                ucwords(str_replace('_', ' ', $row->stage ?? '')),
                $row->status ?? '',
                $row->days_left ?? '',
                $row->reseller_name ?? '',
                $row->commission_status ?? '',
                number_format((float) ($row->deal_value ?? 0), 2),
                number_format((float) ($row->contract_value ?? 0), 2),
                number_format((float) ($row->base_cost ?? 0), 2),
                $row->created_at ?? '',
            ];
        }

        return $data;
    }

    /**
     * Contacts export.
     */
    private function generateContacts(ExportRequest $request): array
    {
        $scope = $request->export_scope ?? [];

        $query = DB::table('contacts')
            ->where('tenant_id', $request->tenant_id)
            ->whereNull('deleted_at')
            ->orderByDesc('created_at');

        if ($request->requester_type === 'reseller') {
            $query->where('created_by_type', 'reseller')
                  ->where('created_by_id', $request->requester_id);
        }

        if (!empty($scope['from'])) $query->where('created_at', '>=', $scope['from']);
        if (!empty($scope['to']))   $query->where('created_at', '<=', $scope['to']);

        $rows = $query->limit(50000)->get([
            'id', 'first_name', 'last_name', 'email', 'phone',
            'organization_name', 'title', 'source', 'created_at',
        ]);

        $headers = [
            'ID', 'First Name', 'Last Name', 'Email', 'Phone',
            'Organization', 'Title', 'Source', 'Created At',
        ];

        $data = [$headers];

        foreach ($rows as $row) {
            $data[] = [
                $row->id ?? '',
                $row->first_name ?? '',
                $row->last_name ?? '',
                $row->email ?? '',
                $row->phone ?? '',
                $row->organization_name ?? '',
                $row->title ?? '',
                $row->source ?? '',
                $row->created_at ?? '',
            ];
        }

        return $data;
    }

    /**
     * Organizations export.
     */
    private function generateOrganizations(ExportRequest $request): array
    {
        $scope = $request->export_scope ?? [];

        $query = DB::table('organizations')
            ->where('tenant_id', $request->tenant_id)
            ->orderByDesc('created_at');

        if (!empty($scope['from'])) $query->where('created_at', '>=', $scope['from']);
        if (!empty($scope['to']))   $query->where('created_at', '<=', $scope['to']);

        $rows = $query->limit(50000)->get([
            'id', 'name', 'industry', 'website', 'phone',
            'city', 'province', 'country', 'employees', 'created_at',
        ]);

        $headers = [
            'ID', 'Name', 'Industry', 'Website', 'Phone',
            'City', 'Province', 'Country', 'Employees', 'Created At',
        ];

        $data = [$headers];

        foreach ($rows as $row) {
            $data[] = [
                $row->id ?? '',
                $row->name ?? '',
                $row->industry ?? '',
                $row->website ?? '',
                $row->phone ?? '',
                $row->city ?? '',
                $row->province ?? '',
                $row->country ?? '',
                $row->employees ?? '',
                $row->created_at ?? '',
            ];
        }

        return $data;
    }

    /**
     * Referrers (Resellers) export.
     * Only available to tenant_user requesters — resellers cannot export other resellers.
     */
    private function generateReferrers(ExportRequest $request): array
    {
        if ($request->requester_type === 'reseller') {
            return [
                ['Export Type', 'Status', 'Note'],
                ['referrers', 'Not available', 'Referrers export is not available for referrer accounts.'],
            ];
        }

        $scope = $request->export_scope ?? [];

        $query = DB::table('resellers')
            ->where('tenant_id', $request->tenant_id)
            ->orderByDesc('created_at');

        if (!empty($scope['status'])) $query->where('status', $scope['status']);
        if (!empty($scope['from']))   $query->where('created_at', '>=', $scope['from']);
        if (!empty($scope['to']))     $query->where('created_at', '<=', $scope['to']);

        $rows = $query->limit(50000)->get([
            'id', 'name', 'email', 'phone', 'status',
            'total_deals', 'active_deals', 'commission_earned',
            'can_export_own_data', 'created_at',
        ]);

        $headers = [
            'ID', 'Name', 'Email', 'Phone', 'Status',
            'Total Deals', 'Active Deals', 'Commission Earned',
            'Can Export Own Data', 'Created At',
        ];

        $data = [$headers];

        foreach ($rows as $row) {
            $data[] = [
                $row->id ?? '',
                $row->name ?? '',
                $row->email ?? '',
                $row->phone ?? '',
                $row->status ?? '',
                $row->total_deals ?? 0,
                $row->active_deals ?? 0,
                number_format((float) ($row->commission_earned ?? 0), 2),
                !empty($row->can_export_own_data) ? 'Yes' : 'No',
                $row->created_at ?? '',
            ];
        }

        return $data;
    }

    /**
     * Commissions export.
     */
    private function generateCommissions(ExportRequest $request): array
    {
        $scope = $request->export_scope ?? [];

        $query = DB::table('leads')
            ->where('tenant_id', $request->tenant_id)
            ->whereNotNull('commission_status')
            ->orderByDesc('created_at');

        // Referrers only see their own commission data
        if ($request->requester_type === 'reseller') {
            $resellerName = DB::table('resellers')
                ->where('id', $request->requester_id)
                ->value('name');

            $query->where(function ($q) use ($request, $resellerName) {
                $q->where('reseller_id', $request->requester_id);
                if ($resellerName) {
                    $q->orWhere('reseller_name', $resellerName);
                }
            });
        }

        if (!empty($scope['commission_status'])) $query->where('commission_status', $scope['commission_status']);
        if (!empty($scope['from']))              $query->where('created_at', '>=', $scope['from']);
        if (!empty($scope['to']))                $query->where('created_at', '<=', $scope['to']);

        $rows = $query->limit(50000)->get([
            'id', 'name', 'reseller_name', 'commission_status',
            'deal_value', 'contract_value', 'base_cost',
            'company_share', 'reseller_share', 'stage', 'created_at',
        ]);

        $headers = [
            'Lead ID', 'Lead Name', 'Reseller', 'Commission Status',
            'Deal Value', 'Contract Value', 'Base Cost',
            'Company Share', 'Reseller Share', 'Stage', 'Created At',
        ];

        $data = [$headers];

        foreach ($rows as $row) {
            $data[] = [
                $row->id ?? '',
                $row->name ?? '',
                $row->reseller_name ?? '',
                $row->commission_status ?? '',
                number_format((float) ($row->deal_value ?? 0), 2),
                number_format((float) ($row->contract_value ?? 0), 2),
                number_format((float) ($row->base_cost ?? 0), 2),
                number_format((float) ($row->company_share ?? 0), 2),
                number_format((float) ($row->reseller_share ?? 0), 2),
                ucwords(str_replace('_', ' ', $row->stage ?? '')),
                $row->created_at ?? '',
            ];
        }

        return $data;
    }

    /**
     * Users export — tenant_user requesters only.
     * Joins tenant_users with their active memberships.
     */
    private function generateUsers(ExportRequest $request): array
    {
        $scope = $request->export_scope ?? [];

        $rows = DB::table('tenant_users as tu')
            ->join('tenant_memberships as tm', function ($join) use ($request) {
                $join->on('tu.id', '=', 'tm.tenant_user_id')
                     ->where('tm.tenant_id', '=', $request->tenant_id);
            })
            ->where('tm.tenant_id', $request->tenant_id)
            ->select([
                'tu.id',
                DB::raw("TRIM(CONCAT(COALESCE(tu.first_name, ''), ' ', COALESCE(tu.last_name, ''))) as name"),
                'tu.email',
                'tu.phone',
                'tm.role',
                'tm.status',
                'tu.created_at',
            ])
            ->orderByDesc('tu.created_at')
            ->limit(10000)
            ->get();

        $headers = ['ID', 'Name', 'Email', 'Phone', 'Role', 'Status', 'Joined At'];

        $data = [$headers];

        foreach ($rows as $row) {
            $data[] = [
                $row->id ?? '',
                $row->name ?? '',
                $row->email ?? '',
                $row->phone ?? '',
                ucfirst($row->role ?? ''),
                ucfirst($row->status ?? ''),
                $row->created_at ?? '',
            ];
        }

        return $data;
    }

    /**
     * Reports export — summarised pipeline metrics per reseller.
     */
    private function generateReports(ExportRequest $request): array
    {
        $scope = $request->export_scope ?? [];

        $query = DB::table('leads')
            ->where('tenant_id', $request->tenant_id)
            ->select([
                'reseller_name',
                DB::raw('COUNT(*) as total_leads'),
                DB::raw("SUM(CASE WHEN status IN ('active','expiring') THEN 1 ELSE 0 END) as active_leads"),
                DB::raw("SUM(CASE WHEN status = 'expired' THEN 1 ELSE 0 END) as expired_leads"),
                DB::raw("SUM(CASE WHEN status = 'won' THEN 1 ELSE 0 END) as won_leads"),
                DB::raw("SUM(CASE WHEN commission_status = 'paid' THEN 1 ELSE 0 END) as paid_commissions"),
                DB::raw("SUM(CASE WHEN commission_status = 'pending' THEN 1 ELSE 0 END) as pending_commissions"),
                DB::raw('SUM(deal_value) as total_deal_value'),
                DB::raw('SUM(reseller_share) as total_reseller_share'),
            ])
            ->groupBy('reseller_name')
            ->orderByDesc('total_leads');

        if (!empty($scope['from'])) $query->where('created_at', '>=', $scope['from']);
        if (!empty($scope['to']))   $query->where('created_at', '<=', $scope['to']);

        $rows = $query->limit(10000)->get();

        $headers = [
            'Referrer', 'Total Deals', 'Active Deals', 'Expired Deals', 'Won Deals',
            'Paid Commissions', 'Pending Commissions',
            'Total Deal Value', 'Total Referrer Share',
        ];

        $data = [$headers];

        foreach ($rows as $row) {
            $data[] = [
                $row->reseller_name ?? '',
                $row->total_leads,
                $row->active_leads,
                $row->expired_leads,
                $row->won_leads,
                $row->paid_commissions,
                $row->pending_commissions,
                number_format((float) ($row->total_deal_value ?? 0), 2),
                number_format((float) ($row->total_reseller_share ?? 0), 2),
            ];
        }

        return $data;
    }
}
