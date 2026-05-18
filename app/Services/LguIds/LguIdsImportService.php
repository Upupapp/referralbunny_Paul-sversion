<?php

namespace App\Services\LguIds;

use App\Models\ImportBatch;
use App\Models\ImportBatchRow;
use App\Models\Lead;
use App\Models\LeadHistory;
use App\Models\PendingReferrerInvite;
use App\Models\Reseller;
use App\Services\ImportSnapshotService;
use App\Services\NotificationDispatchService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LguIdsImportService
{
    const TENANT_ID = 'lgu-ids';

    // Column header aliases → canonical field name
    const COLUMN_ALIASES = [
        // municipality_or_city
        'municipality or city'    => 'municipality_or_city',
        'municipality/city'       => 'municipality_or_city',
        'municipality'            => 'municipality_or_city',
        'city'                    => 'municipality_or_city',
        'lgu'                     => 'municipality_or_city',
        'lgu name'                => 'municipality_or_city',
        'organization'            => 'municipality_or_city',
        'municipality_or_city'    => 'municipality_or_city',
        // province
        'province'                => 'province',
        // deal_amount
        'deal amount'             => 'deal_amount',
        'deal_amount'             => 'deal_amount',
        'contract value'          => 'deal_amount',
        'price'                   => 'deal_amount',
        'selling price'           => 'deal_amount',
        'amount'                  => 'deal_amount',
        // referrer_email
        'referrer email'          => 'referrer_email',
        'referrer_email'          => 'referrer_email',
        'reseller email'          => 'referrer_email',
        'reseller_email'          => 'referrer_email',
        // base_cost
        'base cost'               => 'base_cost',
        'base_cost'               => 'base_cost',
        'cost'                    => 'base_cost',
        'company cost'            => 'base_cost',
        // added_amount
        'added amount'            => 'added_amount',
        'added_amount'            => 'added_amount',
        'amount added'            => 'added_amount',
        'extra amount'            => 'added_amount',
        // other optional
        'deal start date'         => 'deal_start_date',
        'deal_start_date'         => 'deal_start_date',
        'import date'             => 'deal_start_date',
        'start date'              => 'deal_start_date',
        'stage'                   => 'stage',
        'status'                  => 'status',
        'referrer name'           => 'referrer_name',
        'referrer_name'           => 'referrer_name',
        'partner emails'          => 'partner_emails',
        'partner_emails'          => 'partner_emails',
        'partner email'           => 'partner_emails',
        'partner names'           => 'partner_names',
        'partner_names'           => 'partner_names',
        'notes'                   => 'notes',
        'source'                  => 'source',
        'contact person'          => 'contact_person',
        'contact_person'          => 'contact_person',
        'contact email'           => 'contact_email',
        'contact_email'           => 'contact_email',
        'contact phone'           => 'contact_phone',
        'contact_phone'           => 'contact_phone',
        'office / department'     => 'office_department',
        'office/department'       => 'office_department',
        'office_department'       => 'office_department',
        'last activity date'      => 'last_activity_date',
        'last_activity_date'      => 'last_activity_date',
        'next follow-up date'     => 'next_follow_up_date',
        'next_follow_up_date'     => 'next_follow_up_date',
        'supporting document url' => 'supporting_document_url',
        'supporting_document_url' => 'supporting_document_url',
        'tags'                    => 'tags',
        'internal reference id'   => 'internal_reference_id',
        'internal_reference_id'   => 'internal_reference_id',
    ];

    const REQUIRED_FIELDS = ['municipality_or_city', 'province', 'deal_amount', 'referrer_email'];

    const VALID_STAGES = ['introduction', 'presentation', 'contract_sent', 'signed', 'paid'];

    public function __construct(
        private NotificationDispatchService $notifications,
        private ImportSnapshotService       $snapshots,
    ) {}

    // ── CSV parsing ───────────────────────────────────────────────

    public function parseFile(UploadedFile $file): array
    {
        $ext = strtolower($file->getClientOriginalExtension());
        if ($ext === 'csv') {
            return $this->parseCsv($file->getRealPath());
        }
        return $this->parseXlsx($file->getRealPath());
    }

    private function parseCsv(string $path): array
    {
        $rows = [];
        if (($handle = fopen($path, 'r')) === false) {
            throw new \RuntimeException("Cannot open CSV file.");
        }
        $headers = null;
        while (($row = fgetcsv($handle, 0, ',')) !== false) {
            if ($headers === null) {
                $headers = $row;
                continue;
            }
            $combined = [];
            foreach ($headers as $i => $h) {
                $combined[$h] = $row[$i] ?? null;
            }
            $rows[] = $combined;
        }
        fclose($handle);
        return ['headers' => $headers ?? [], 'rows' => $rows];
    }

    private function parseXlsx(string $path): array
    {
        // Minimal XLSX reader using ZipArchive + SimpleXML (no phpspreadsheet needed)
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            throw new \RuntimeException("Cannot open XLSX file.");
        }
        // Read shared strings
        $sharedStrings = [];
        if (($ssXml = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
            $ss = simplexml_load_string($ssXml);
            foreach ($ss->si as $si) {
                $str = '';
                foreach ($si->r ?? [$si] as $r) {
                    $str .= (string) ($r->t ?? $r);
                }
                $sharedStrings[] = $str;
            }
        }
        // Read sheet1
        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        if (!$sheetXml) throw new \RuntimeException("Cannot read XLSX sheet.");
        $sheet   = simplexml_load_string($sheetXml);
        $rawRows = [];
        foreach ($sheet->sheetData->row as $row) {
            $rowData = [];
            foreach ($row->c as $cell) {
                $colLetter = preg_replace('/[0-9]/', '', (string) $cell['r']);
                $colIndex  = $this->colLetterToIndex($colLetter);
                $t         = (string) ($cell['t'] ?? '');
                $v         = (string) ($cell->v ?? '');
                if ($t === 's')          $v = $sharedStrings[(int) $v] ?? '';
                elseif ($t === 'inlineStr') $v = (string) ($cell->is->t ?? '');
                $rowData[$colIndex] = $v;
            }
            $rawRows[] = $rowData;
        }
        if (empty($rawRows)) return ['headers' => [], 'rows' => []];
        // First row = headers
        $headers = $rawRows[0];
        $result  = [];
        foreach (array_slice($rawRows, 1) as $rawRow) {
            $combined = [];
            foreach ($headers as $i => $h) {
                $combined[$h] = $rawRow[$i] ?? null;
            }
            $result[] = $combined;
        }
        return ['headers' => array_values($headers), 'rows' => $result];
    }

    private function colLetterToIndex(string $col): int
    {
        $col = strtoupper($col);
        $n   = 0;
        for ($i = 0, $len = strlen($col); $i < $len; $i++) {
            $n = $n * 26 + (ord($col[$i]) - ord('A') + 1);
        }
        return $n - 1;
    }

    // ── Column detection ──────────────────────────────────────────

    public function detectColumns(array $headers): array
    {
        $mapping = [];
        foreach ($headers as $header) {
            $key = strtolower(trim($header));
            if (isset(self::COLUMN_ALIASES[$key])) {
                $mapping[$header] = self::COLUMN_ALIASES[$key];
            }
        }
        return $mapping;
    }

    public function getMissingRequired(array $mapping): array
    {
        $mapped = array_values($mapping);
        return array_diff(self::REQUIRED_FIELDS, $mapped);
    }

    // ── Normalize a raw row using detected column mapping ─────────

    public function normalizeRow(array $rawRow, array $colMapping, string $importDate): array
    {
        $n = [];
        foreach ($rawRow as $header => $value) {
            $field = $colMapping[$header] ?? null;
            if ($field) $n[$field] = $value !== '' ? trim((string) $value) : null;
        }
        // Normalize amounts
        foreach (['deal_amount', 'base_cost', 'added_amount'] as $f) {
            if (isset($n[$f])) {
                $n[$f] = LguIdsPricingService::normalizeAmount($n[$f]);
            }
        }
        // Normalize referrer_email
        if (!empty($n['referrer_email'])) {
            $n['referrer_email'] = strtolower(trim($n['referrer_email']));
        }
        // Deal start date — default to import date
        if (empty($n['deal_start_date'])) {
            $n['deal_start_date'] = $importDate;
        }
        // Normalize stage
        if (!empty($n['stage'])) {
            $s          = strtolower(str_replace([' ', '-'], '_', trim($n['stage'])));
            $n['stage'] = in_array($s, self::VALID_STAGES) ? $s : 'introduction';
        } else {
            $n['stage'] = 'introduction';
        }
        // Parse partner emails (comma or semicolon separated)
        if (!empty($n['partner_emails'])) {
            $emails              = preg_split('/[,;]+/', $n['partner_emails']);
            $n['partner_emails'] = array_values(array_filter(
                array_map('trim', $emails),
                fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL)
            ));
        } else {
            $n['partner_emails'] = [];
        }
        return $n;
    }

    // ── Validate a normalized row ─────────────────────────────────

    public function validateRow(
        array $normalized,
        int $rowNum,
        string $uploaderRole,
        ?string $uploaderEmail = null
    ): array {
        $issueCodes = [];
        $status     = 'ready';

        // 1. Required fields
        foreach (self::REQUIRED_FIELDS as $f) {
            if (empty($normalized[$f])) {
                $issueCodes[] = ['code' => 'MISSING_REQUIRED', 'field' => $f, 'message' => "Missing required field: $f"];
                $status       = 'failed';
            }
        }
        if ($status === 'failed') {
            return ['status' => 'failed', 'issue_codes' => $issueCodes, 'computed' => [], 'organization_id' => null];
        }

        // 2. Referrer permission check (Referrers can only import their own email)
        if ($uploaderRole === 'reseller' && !empty($normalized['referrer_email'])) {
            if (strtolower($normalized['referrer_email']) !== strtolower($uploaderEmail ?? '')) {
                $issueCodes[] = [
                    'code'    => 'REFERRER_PERMISSION_BLOCKED',
                    'message' => "Referrers can only import deals assigned to themselves.",
                ];
                return ['status' => 'blocked', 'issue_codes' => $issueCodes, 'computed' => [], 'organization_id' => null];
            }
        }

        // 3. Referrer email validation
        $referrerEmail  = $normalized['referrer_email'];
        $referrerStatus = 'ok';
        if (!filter_var($referrerEmail, FILTER_VALIDATE_EMAIL)) {
            $issueCodes[] = ['code' => 'INVALID_EMAIL', 'field' => 'referrer_email', 'message' => "Invalid referrer email: $referrerEmail"];
            $status       = 'failed';
        } else {
            $reseller = Reseller::where('tenant_id', self::TENANT_ID)
                ->where('email', $referrerEmail)
                ->whereIn('status', ['active', 'nda_signed'])
                ->first();
            if (!$reseller) {
                $issueCodes[]   = ['code' => 'UNKNOWN_REFERRER', 'message' => "Referrer email $referrerEmail not found in tenant. They will need to be invited."];
                $referrerStatus = 'unknown';
                if ($status === 'ready') $status = 'unknown_referrer';
            }
        }

        // 4. LGU/organization validation
        $orgId       = null;
        $orgStatus   = 'ok';
        $suggestOrgs = [];
        if (!empty($normalized['municipality_or_city']) && !empty($normalized['province'])) {
            $cityName = $normalized['municipality_or_city'];
            $province = $normalized['province'];
            // Exact match (city field)
            $org = DB::table('organizations')
                ->where('tenant_id', self::TENANT_ID)
                ->whereRaw("LOWER(city) = ?", [strtolower($cityName)])
                ->whereRaw("LOWER(address) = ?", [strtolower($province)])
                ->first();
            if ($org) {
                $orgId = $org->id;
            } else {
                // Fuzzy match using ILIKE — pg_trgm similarity() not available on Supabase
                $suggestions = DB::table('organizations')
                    ->where('tenant_id', self::TENANT_ID)
                    ->whereRaw("LOWER(address) = ?", [strtolower($province)])
                    ->whereRaw("LOWER(city) LIKE ?", ['%' . strtolower($cityName) . '%'])
                    ->limit(3)
                    ->get(['id', 'city', 'address']);
                $suggestOrgs = $suggestions->toArray();
                if (empty($suggestOrgs)) {
                    // Fallback: partial name match across all provinces
                    $byNameOnly  = DB::table('organizations')
                        ->where('tenant_id', self::TENANT_ID)
                        ->whereRaw("LOWER(city) LIKE ?", ['%' . strtolower($cityName) . '%'])
                        ->limit(3)
                        ->get(['id', 'city', 'address']);
                    $suggestOrgs = $byNameOnly->toArray();
                }
                $issueCodes[] = [
                    'code'        => 'UNKNOWN_LGU',
                    'message'     => "LGU '$cityName, $province' not found. " . (count($suggestOrgs) ? 'Suggested matches available.' : 'No suggestions found.'),
                    'suggestions' => $suggestOrgs,
                ];
                $orgStatus = 'unknown';
                if ($status === 'ready') $status = 'unknown_lgu';
            }
        }

        // 5. Pricing computation
        $computed = LguIdsPricingService::compute(
            $normalized['deal_amount'] ?? null,
            $normalized['base_cost'] ?? null,
            $normalized['added_amount'] ?? null
        );
        if (in_array($computed['pricing_status'], ['needs_pricing_review', 'missing_amount', 'amount_mismatch'])) {
            $issueCodes[] = ['code' => 'PRICING_ISSUE', 'message' => $computed['pricing_issue'] ?? 'Pricing needs review.'];
            if ($status === 'ready') $status = 'pricing_issue';
        }

        // 6. Duplicate/overlap detection (only if org found)
        $existingDeal     = null;
        $existingDealData = null;
        if ($orgId) {
            $existingDeal = DB::table('leads')
                ->where('tenant_id', self::TENANT_ID)
                ->where('organization_id', $orgId)
                ->whereNotIn('status', ['expired', 'declined'])
                ->first();
            if ($existingDeal) {
                $issueCodes[] = [
                    'code'          => 'DUPLICATE_DEAL',
                    'message'       => "This LGU already has an active deal (assigned to {$existingDeal->reseller_name}).",
                    'existing_deal' => [
                        'id'         => $existingDeal->id,
                        'reseller'   => $existingDeal->reseller_name,
                        'stage'      => $existingDeal->stage,
                        'deal_value' => $existingDeal->deal_value,
                        'status'     => $existingDeal->status,
                    ],
                ];
                $existingDealData = (array) $existingDeal;
                if ($status === 'ready') $status = 'duplicate';
            }
        }

        // 7. Partner emails validation
        $partnerIssues = [];
        foreach ($normalized['partner_emails'] ?? [] as $pEmail) {
            if (!filter_var($pEmail, FILTER_VALIDATE_EMAIL)) {
                $partnerIssues[] = ['code' => 'INVALID_PARTNER_EMAIL', 'email' => $pEmail];
            } else {
                $exists = DB::table('partner_users')
                    ->where('tenant_id', self::TENANT_ID)
                    ->where('email', strtolower($pEmail))
                    ->exists();
                if (!$exists) {
                    $partnerIssues[] = [
                        'code'    => 'UNKNOWN_PARTNER',
                        'email'   => $pEmail,
                        'message' => "Partner email $pEmail not found — will need to be invited.",
                    ];
                    if ($status === 'ready') $status = 'needs_review';
                }
            }
        }
        if (!empty($partnerIssues)) {
            $issueCodes = array_merge($issueCodes, $partnerIssues);
        }

        // Determine final status if still ready
        if ($status === 'ready' && !empty($issueCodes)) $status = 'needs_review';

        return [
            'status'          => $status,
            'issue_codes'     => $issueCodes,
            'organization_id' => $orgId,
            'existing_deal'   => $existingDealData,
            'referrer_status' => $referrerStatus,
            'org_status'      => $orgStatus,
            'computed'        => [
                'normalized_deal_amount'  => $computed['deal_amount'],
                'normalized_base_cost'    => $computed['base_cost'],
                'normalized_added_amount' => $computed['added_amount'],
                'display_pct'             => $computed['display_pct'],
                'pricing_tier_matched'    => $computed['tier_matched'],
                'pricing_status'          => $computed['pricing_status'],
                'pricing_issue_message'   => $computed['pricing_issue'],
                'computed_from'           => $computed['computed_from'],
                'currency'                => 'PHP',
                'import_start_date'       => $normalized['deal_start_date'],
                'final_deal_start_date'   => $normalized['deal_start_date'],
            ],
        ];
    }

    // ── Create import batch from file ─────────────────────────────

    public function createBatch(
        UploadedFile $file,
        string $importedById,
        string $importedByRole
    ): ImportBatch {
        $path    = $file->store('imports/lgu-ids', 'local');
        $parsed  = $this->parseFile($file);
        $headers = $parsed['headers'];
        $rawRows = $parsed['rows'];
        $colMap  = $this->detectColumns($headers);
        $missing = $this->getMissingRequired($colMap);

        if (!empty($missing)) {
            throw new \InvalidArgumentException(
                "Missing required columns: " . implode(', ', $missing) . ". Check your file headers."
            );
        }

        $maxRows = $importedByRole === 'reseller' ? 1000 : 5000;
        if (count($rawRows) > $maxRows) {
            throw new \InvalidArgumentException(
                "Import limit is {$maxRows} rows for your role. Your file has " . count($rawRows) . " rows. Please split the file and re-upload."
            );
        }

        $batch = ImportBatch::create([
            'tenant_id'        => self::TENANT_ID,
            'import_type'      => 'lgu_ids_deals',
            'file_name'        => $file->getClientOriginalName(),
            'file_path'        => $path,
            'imported_by_id'   => $importedById,
            'imported_by_role' => $importedByRole,
            'status'           => 'previewing',
            'total_rows'       => count($rawRows),
        ]);

        $importDate    = now()->toDateString();
        $counts        = [
            'successful'      => 0,
            'failed'          => 0,
            'duplicate'       => 0,
            'unknown_referrer'=> 0,
            'pricing_issue'   => 0,
            'blocked'         => 0,
        ];
        $pendingInvites = []; // email => [name, rows]

        // Resolve uploader email once (not per-row)
        $uploaderEmail = null;
        if ($importedByRole === 'reseller') {
            $uploaderEmail = Reseller::where('id', $importedById)->value('email');
        }

        foreach ($rawRows as $i => $rawRow) {
            $rowNum     = $i + 2; // 1-indexed, row 1 = header
            $normalized = $this->normalizeRow($rawRow, $colMap, $importDate);

            // For resellers, auto-set referrer_email to their own account email.
            // They should never need to type their own email in the spreadsheet.
            if ($importedByRole === 'reseller' && $uploaderEmail) {
                $normalized['referrer_email'] = $uploaderEmail;
            }

            $validation = $this->validateRow($normalized, $rowNum, $importedByRole, $uploaderEmail);

            $rowAction = match ($validation['status']) {
                'ready'            => 'create',
                'duplicate'        => 'review',
                'unknown_referrer' => 'create',
                'unknown_lgu'      => 'review',
                'pricing_issue'    => 'review',
                'blocked'          => 'blocked',
                'failed'           => 'blocked',
                default            => 'review',
            };

            $existingDealId = $validation['existing_deal']['id'] ?? null;

            ImportBatchRow::create([
                'import_batch_id'   => $batch->id,
                'row_number'        => $rowNum,
                'raw_data'          => $rawRow,
                'normalized_data'   => $normalized,
                'computed_data'     => $validation['computed'],
                'validation_status' => $validation['status'],
                'issue_codes'       => $validation['issue_codes'],
                'row_action'        => $rowAction,
                'existing_deal_id'  => $existingDealId,
                'organization_id'   => $validation['organization_id'] ?? null,
            ]);

            // Track counts
            match ($validation['status']) {
                'ready'                    => $counts['successful']++,
                'failed', 'blocked'        => $counts['failed']++,
                'duplicate'                => $counts['duplicate']++,
                'unknown_referrer'         => $counts['unknown_referrer']++,
                'pricing_issue'            => $counts['pricing_issue']++,
                default                    => null,
            };

            // Stage unknown referrers for invite
            if ($validation['status'] === 'unknown_referrer') {
                $email = $normalized['referrer_email'] ?? null;
                $name  = $normalized['referrer_name'] ?? null;
                if ($email) {
                    if (!isset($pendingInvites[$email])) {
                        $pendingInvites[$email] = ['name' => $name, 'rows' => []];
                    }
                    $pendingInvites[$email]['rows'][] = $rowNum;
                }
            }
        }

        // Upsert pending referrer invites + auto-add to Referrers list as invited
        foreach ($pendingInvites as $email => $info) {
            PendingReferrerInvite::updateOrCreate(
                ['tenant_id' => self::TENANT_ID, 'email' => $email],
                [
                    'name'            => $info['name'],
                    'import_batch_id' => $batch->id,
                    'row_ids'         => $info['rows'],
                    'status'          => 'pending_invite',
                ]
            );

            // Create the Reseller record so they appear on the Referrers tab immediately.
            // firstOrCreate prevents duplicates if they were already invited.
            try {
                Reseller::firstOrCreate(
                    ['tenant_id' => self::TENANT_ID, 'email' => strtolower($email)],
                    [
                        'name'        => $info['name'] ?? $email,
                        'status'      => 'invited',
                        'joined_date' => now()->toDateString(),
                    ]
                );
            } catch (\Throwable) {}
        }

        $batch->update([
            'status'                => 'previewed',
            'successful_rows'       => $counts['successful'],
            'failed_rows'           => $counts['failed'],
            'duplicate_rows'        => $counts['duplicate'],
            'unknown_referrer_rows' => $counts['unknown_referrer'],
            'pricing_issue_rows'    => $counts['pricing_issue'],
            'blocked_rows'          => $counts['blocked'],
        ]);

        // Notify tenant admins — wrapped in try-catch since notifications table
        // has a CHECK constraint on category that may not include all values
        try {
            $this->notifications->dispatchToTenantAdmins(
                tenantId:     self::TENANT_ID,
                category:     'import_export',
                priority:     'normal',
                title:        'LGU IDS Import Ready for Review',
                body:         "Your import of {$batch->total_rows} rows is ready. {$counts['successful']} ready, {$counts['duplicate']} duplicates, {$counts['unknown_referrer']} unknown referrers.",
                actionUrl:    "/tenant/" . self::TENANT_ID . "/imports/lgu-ids/{$batch->id}",
                actionLabel:  'Review Import',
                dedupeSuffix: $batch->id,
            );
        } catch (\Throwable) {}

        return $batch;
    }

    // ── Approve a single row ──────────────────────────────────────

    public function approveRow(ImportBatchRow $row, string $action, string $approverId): void
    {
        $row->update([
            'row_action'     => $action,
            'approved_by_id' => $approverId,
            'approved_at'    => now(),
        ]);
    }

    // ── Execute approved import ───────────────────────────────────

    public function executeImport(ImportBatch $batch, string $executorId, string $executorRole): array
    {
        $batch->update(['status' => 'processing', 'started_at' => now()]);

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $failed  = 0;
        $errors  = [];

        // Pre-load all stage rules once — avoids N+1 (one query per row) inside the loop
        $stageRulesMap = [];
        try {
            $stageRulesMap = DB::table('tenant_pipeline_stage_rules')
                ->where('tenant_id', self::TENANT_ID)
                ->pluck('max_days', 'stage')
                ->toArray();
        } catch (\Throwable) {}

        ImportBatchRow::where('import_batch_id', $batch->id)
            ->orderBy('id')
            ->chunkById(100, function ($chunk) use ($batch, $stageRulesMap, &$created, &$updated, &$skipped, &$failed, &$errors) {
        foreach ($chunk as $row) {
            if ($row->row_action === 'blocked') { $skipped++; continue; }
            if ($row->row_action === 'skip')    { $skipped++; continue; }

            $norm     = $row->normalized_data;
            $computed = $row->computed_data;

            try {
                // Duplicate with no approval → skip
                if ($row->validation_status === 'duplicate' && !in_array($row->row_action, ['overwrite', 'merge', 'create'])) {
                    $skipped++;
                    continue;
                }
                // Failed rows → skip
                if ($row->validation_status === 'failed') {
                    $skipped++;
                    continue;
                }
                // Pricing issues with no valid amounts → fail
                if (empty($computed['normalized_deal_amount']) && empty($computed['normalized_base_cost'])) {
                    $row->update(['error_message' => 'Missing deal amount — row skipped.']);
                    $failed++;
                    continue;
                }

                $baseCost      = (float) ($computed['normalized_base_cost'] ?? 0);
                $addedAmount   = (float) ($computed['normalized_added_amount'] ?? 0);
                $dealValue     = $baseCost + $addedAmount;
                // LGU IDS: default deal value ₱4,000,000 when blank/zero
                $importAmountDefaulted = false;
                if ($dealValue == 0) {
                    $dealValue             = 4_000_000.00;
                    $importAmountDefaulted = true;
                }
                $orgId = $row->organization_id;
                $city     = $norm['municipality_or_city'] ?? '';
                $province = $norm['province'] ?? '';

                // Auto-create organization when not found during validation.
                // For LGU IDS: Organization = "Municipality, Province" (LOCKED RULE).
                if (!$orgId && $city && $province) {
                    $orgName = $city . ', ' . $province;
                    $orgId   = DB::table('organizations')
                        ->where('tenant_id', self::TENANT_ID)
                        ->whereRaw('LOWER(name) = ?', [strtolower($orgName)])
                        ->value('id');

                    if (!$orgId) {
                        $orgId = (string) \Illuminate\Support\Str::uuid();
                        DB::table('organizations')->insert([
                            'id'         => $orgId,
                            'tenant_id'  => self::TENANT_ID,
                            'name'       => $orgName,
                            'city'       => $city,
                            'address'    => $province,
                            'type'       => 'government',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                        // Update the batch row so future steps reference the new org
                        $row->update(['organization_id' => $orgId]);
                    }
                }

                $referrerEmail = $norm['referrer_email'] ?? null;
                $reseller      = $referrerEmail
                    ? Reseller::where('tenant_id', self::TENANT_ID)->where('email', $referrerEmail)->first()
                    : null;
                $resellerName  = $reseller?->name ?? ($norm['referrer_name'] ?? $referrerEmail);

                // Build LGU deal name from city + province
                $dealName = $city . ($province ? ', ' . $province : '');

                // Get days_left from LGU IDS stage rules (LOCKED)
                $stage    = $norm['stage'] ?? 'introduction';
                $daysLeft = match($stage) {
                    'introduction'  => 14,
                    'presentation'  => 21,
                    'contract_sent' => 30,
                    'signed'        => 30,
                    default         => 21,
                };
                if (isset($stageRulesMap[$stage])) {
                    $daysLeft = (int) $stageRulesMap[$stage];
                }

                // ── One-deal-per-org check (LOCKED RULE)
                if ($orgId && $row->row_action !== 'overwrite') {
                    $existing = DB::table('leads')
                        ->where('tenant_id', self::TENANT_ID)
                        ->where('organization_id', $orgId)
                        ->whereNotIn('status', ['expired', 'declined'])
                        ->first();
                    if ($existing && $row->row_action !== 'merge') {
                        $skipped++;
                        $row->update(['error_message' => 'LGU already has active deal. Skipped without approval.']);
                        continue;
                    }
                }

                if ($row->row_action === 'overwrite' && $row->existing_deal_id) {
                    $beforeLead = DB::table('leads')->where('id', $row->existing_deal_id)->first();
                    $changes    = ['stage' => $stage, 'reseller_name' => $resellerName, 'base_cost' => $baseCost, 'added_amount' => $addedAmount, 'deal_value' => $dealValue];
                    DB::table('leads')->where('id', $row->existing_deal_id)->update(array_merge($changes, ['updated_at' => now()]));
                    $this->snapshots->recordUpdated(
                        batchId: $batch->id, tenantId: self::TENANT_ID,
                        entityType: 'lead', entityId: $row->existing_deal_id,
                        beforeData: $beforeLead ? (array) $beforeLead : [], afterData: $changes,
                        changedFields: array_keys($changes), operationType: 'overwritten',
                        batchRowId: $row->id, row: $row,
                    );
                    LeadHistory::create(['lead_id' => $row->existing_deal_id, 'tenant_id' => self::TENANT_ID, 'action' => 'Deal updated via LGU IDS import', 'type' => 'import', 'category' => 'import', 'actor_name' => 'Admin (Import)', 'actor_role' => $executorRole, 'new_values' => ['batch_id' => $batch->id, 'file' => $batch->file_name], 'date' => now()->toDateString()]);
                    $row->update(['created_deal_id' => $row->existing_deal_id]);
                    $updated++;
                } elseif ($row->row_action === 'merge' && $row->existing_deal_id) {
                    $existing = Lead::find($row->existing_deal_id);
                    if ($existing) {
                        $beforeLead = $existing->toArray();
                        $updates    = [];
                        if (!$existing->base_cost && $baseCost)       $updates['base_cost']    = $baseCost;
                        if (!$existing->added_amount && $addedAmount) $updates['added_amount'] = $addedAmount;
                        if (!$existing->deal_value && $dealValue)     $updates['deal_value']   = $dealValue;
                        if (!empty($updates)) {
                            $existing->update($updates);
                            $this->snapshots->recordUpdated(
                                batchId: $batch->id, tenantId: self::TENANT_ID,
                                entityType: 'lead', entityId: $existing->id,
                                beforeData: $beforeLead, afterData: $updates,
                                changedFields: array_keys($updates), operationType: 'merged',
                                batchRowId: $row->id, row: $row,
                            );
                        }
                        LeadHistory::create(['lead_id' => $existing->id, 'tenant_id' => self::TENANT_ID, 'action' => 'Deal merged via LGU IDS import', 'type' => 'import', 'category' => 'import', 'actor_name' => 'Admin (Import)', 'actor_role' => $executorRole, 'new_values' => ['batch_id' => $batch->id, 'file' => $batch->file_name], 'date' => now()->toDateString()]);
                    }
                    $row->update(['created_deal_id' => $row->existing_deal_id]);
                    $updated++;
                } else {
                    // Create new deal — CRITICAL PATH: lead creation must not be blocked by secondary operations
                    $newLeadData = [
                        'province'     => $province,
                        'municipality' => $city,
                        'import_batch' => $batch->id,
                        'source'       => $norm['source'] ?? 'import',
                        'notes'        => $norm['notes'] ?? null,
                    ];
                    if ($importAmountDefaulted) {
                        $newLeadData['amount_defaulted']           = true;
                        $newLeadData['amount_confirmation_status'] = 'pending';
                        $newLeadData['amount_default_reason']      = 'LGU IDS default applied during import — no deal amount was provided.';
                    }

                    $newLead = Lead::create([
                        'tenant_id'         => self::TENANT_ID,
                        'name'              => $dealName,
                        'stage'             => $stage,
                        'status'            => 'active',
                        'days_left'         => $daysLeft,
                        'organization_id'   => $orgId,
                        'reseller_name'     => $resellerName,
                        'commission_status' => 'pending',
                        'base_cost'         => $baseCost,
                        'added_amount'      => $addedAmount,
                        'deal_value'        => $dealValue,
                        'data'              => $newLeadData,
                    ]);

                    // Record success immediately — secondary ops below must not roll this back
                    $row->update(['created_deal_id' => $newLead->id]);
                    $created++;

                    // Secondary: audit history (non-critical)
                    try {
                        LeadHistory::create([
                            'lead_id'    => $newLead->id,
                            'tenant_id'  => self::TENANT_ID,
                            'action'     => 'Deal created via LGU IDS import'
                                          . ($importAmountDefaulted ? ' — LGU IDS default amount ₱4,000,000 applied (no amount in import file).' : '.'),
                            'type'       => 'import',
                            'category'   => 'import',
                            'actor_name' => $executorRole === 'reseller' ? ($resellerName ?? 'Referrer') : 'Admin (Import)',
                            'actor_role' => $executorRole,
                            'new_values' => [
                                'deal_name'        => $dealName,
                                'stage'            => $stage,
                                'deal_value'       => $dealValue,
                                'amount_defaulted' => $importAmountDefaulted,
                                'batch_id'         => $batch->id,
                                'file'             => $batch->file_name,
                            ],
                            'date'       => now()->toDateString(),
                        ]);
                    } catch (\Throwable) {}

                    // Secondary: notify referrer (non-critical)
                    if ($reseller) {
                        try {
                            $this->notifications->dispatchToReseller(
                                resellerId:   (string) $reseller->id,
                                tenantId:     self::TENANT_ID,
                                category:     'deal_pipeline',
                                priority:     'normal',
                                title:        'New Deal: ' . $dealName,
                                body:         "A new deal has been assigned to you: {$dealName} in {$stage} stage.",
                                actionUrl:    '/reseller/' . self::TENANT_ID . '/deals',
                                actionLabel:  'View Deal',
                                dedupeSuffix: $newLead->id . ':import_assigned',
                            );
                        } catch (\Throwable) {}
                    }

                    // Secondary: snapshot (non-critical)
                    try {
                        $this->snapshots->recordCreated(
                            batchId: $batch->id, tenantId: self::TENANT_ID,
                            entityType: 'lead', entityId: $newLead->id,
                            entityData: $newLead->toArray(), batchRowId: $row->id, row: $row,
                        );
                    } catch (\Throwable) {}
                }
            } catch (\Throwable $e) {
                $friendly = $this->friendlyError($e->getMessage());
                $row->update([
                    'error_message'    => $friendly,
                    'row_action'       => 'failed',
                    'validation_status'=> 'failed',
                ]);
                $errors[] = "Row {$row->row_number}: {$friendly}";
                $failed++;
            }
        }
        }); // end chunkById

        $status = match (true) {
            $failed > 0 && $created === 0 && $updated === 0 => 'failed',
            $failed > 0                                      => 'completed_with_warnings',
            default                                          => 'completed',
        };

        $batch->update([
            'status'          => $status,
            'successful_rows' => $created,
            'updated_rows'    => $updated,
            'skipped_rows'    => $skipped,
            'failed_rows'     => $failed,
            'completed_at'    => now(),
            'summary_json'    => [
                'created' => $created,
                'updated' => $updated,
                'skipped' => $skipped,
                'failed'  => $failed,
                'errors'  => $errors,
            ],
        ]);

        try { $this->snapshots->markBatchEligible($batch->id); } catch (\Throwable) {}

        // Notify the importer and executor about the result
        $titleMap = [
            'completed'                => 'Import Complete',
            'completed_with_warnings'  => 'Import Complete with Warnings',
            'failed'                   => 'Import Failed',
        ];
        $notifyTitle  = $titleMap[$status] ?? 'Import Complete';
        $notifyBody   = "\"{$batch->file_name}\" — Created: $created, Updated: $updated, Skipped: $skipped" . ($failed > 0 ? ", Failed: $failed." : '.');
        $notifyPrio   = $failed > 0 ? 'high' : 'normal';
        $reportUrl    = '/tenant/' . self::TENANT_ID . '/imports/lgu-ids/' . $batch->id;
        $notifiedIds  = [];

        // Notify the user who originally uploaded the file
        if ($batch->imported_by_role === 'tenant_admin' && $batch->imported_by_id) {
            try {
                $this->notifications->dispatch(
                    category:         'import_export',
                    priority:         $notifyPrio,
                    title:            $notifyTitle,
                    body:             $notifyBody,
                    notifiableType:   'tenant_user',
                    notifiableId:     (string) $batch->imported_by_id,
                    tenantId:         self::TENANT_ID,
                    actionUrl:        $reportUrl,
                    actionLabel:      'View Report',
                    deduplicationKey: 'import:' . $batch->id . ':uploader',
                );
                $notifiedIds[] = $batch->imported_by_id;
            } catch (\Throwable) {}
        }

        // Notify the executor if different from the uploader
        if ($executorId && $executorRole === 'tenant_admin' && !in_array($executorId, $notifiedIds)) {
            try {
                $this->notifications->dispatch(
                    category:         'import_export',
                    priority:         $notifyPrio,
                    title:            $notifyTitle,
                    body:             $notifyBody,
                    notifiableType:   'tenant_user',
                    notifiableId:     (string) $executorId,
                    tenantId:         self::TENANT_ID,
                    actionUrl:        $reportUrl,
                    actionLabel:      'View Report',
                    deduplicationKey: 'import:' . $batch->id . ':executor',
                );
            } catch (\Throwable) {}
        }

        return compact('created', 'updated', 'skipped', 'failed');
    }

    // ── Generate downloadable CSV of failed rows ──────────────────

    public function generateFailedRowsCsv(ImportBatch $batch): string
    {
        $rows = ImportBatchRow::where('import_batch_id', $batch->id)
            ->where(fn($q) => $q
                ->whereIn('validation_status', ['failed', 'blocked'])
                ->orWhereNotNull('error_message')
            )
            ->get();

        $lines   = [];
        $lines[] = implode(',', [
            'Row #', 'Municipality/City', 'Province', 'Deal Amount',
            'Referrer Email', 'Status', 'Issues',
        ]);
        foreach ($rows as $row) {
            $n      = $row->normalized_data;
            $issues = collect($row->issue_codes)->pluck('message')->implode('; ');
            $lines[] = implode(',', array_map(
                fn ($v) => '"' . str_replace('"', '""', (string) $v) . '"',
                [
                    $row->row_number,
                    $n['municipality_or_city'] ?? '',
                    $n['province'] ?? '',
                    $n['deal_amount'] ?? '',
                    $n['referrer_email'] ?? '',
                    $row->validation_status,
                    $issues,
                ]
            ));
        }
        return implode("\n", $lines);
    }

    // ── Generate template CSV ─────────────────────────────────────

    public function generateTemplateCsv(): string
    {
        // Province is first column per LGU IDS standard.
        // Municipality/City + Province combined = Organization (no separate Organization column needed).
        $headers = [
            'Province', 'Municipality or City', 'Deal Amount', 'Referrer Email',
            'Deal Start Date', 'Stage', 'Status',
            'Referrer Name', 'Partner Email', 'Notes', 'Source',
            'Contact Person', 'Contact Email', 'Contact Phone', 'Office / Department',
            'Last Activity Date', 'Next Follow-up Date', 'Supporting Document URL',
            'Tags', 'Internal Reference ID',
        ];
        $sampleRow = [
            'Metro Manila', 'Quezon City', '4000000', 'referrer@email.com',
            date('Y-m-d'), 'introduction', 'active',
            'Juan dela Cruz', 'partner@email.com', 'Notes here', 'referral',
            'Maria Reyes', 'maria@lgu.gov.ph', '+63 9XX XXX XXXX', 'IT Department',
            date('Y-m-d'), '', '', 'tagA,tagB', 'REF-001',
        ];
        $lines   = [];
        $lines[] = implode(',', array_map(fn ($h) => '"' . $h . '"', $headers));
        $lines[] = implode(',', array_map(fn ($v) => '"' . $v . '"', $sampleRow));
        return implode("\n", $lines);
    }

    /**
     * Convert a raw exception message into plain-language user-facing text.
     */
    private function friendlyError(string $message): string
    {
        if (str_contains($message, 'SQLSTATE')) {
            if (str_contains($message, '42703')) {
                return 'System error: a required database column is missing. Please contact support.';
            }
            if (str_contains($message, '23505')) {
                return 'This record already exists and could not be inserted again (duplicate).';
            }
            if (str_contains($message, '23503')) {
                return 'A linked record (such as a referrer or organisation) could not be found.';
            }
            if (str_contains($message, '42P01')) {
                return 'System error: required database table not found. Please contact support.';
            }
            return 'A database error prevented this row from being imported. Please contact support.';
        }
        if (str_contains($message, 'Undefined property') || str_contains($message, 'null')) {
            return 'A required value was missing or invalid in this row.';
        }
        if (strlen($message) > 150) {
            return 'An unexpected error occurred while importing this row. Please check the data and try again.';
        }
        return $message;
    }
}
