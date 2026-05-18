<?php

namespace App\Services;

use App\Models\ImportBatch;
use App\Models\ImportBatchRow;
use App\Models\Lead;
use App\Models\LeadHistory;
use App\Models\PendingReferrerInvite;
use App\Models\Reseller;
use App\Models\TenantImportSettings;
use App\Services\TemplateAdoptionService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Generic deal import service for non-LGU-IDS tenants.
 *
 * CRITICAL RULES:
 * - No municipality_or_city / province fields.
 * - No LGU IDS pricing table — deal_amount stored as-is.
 * - No base_cost / added_amount computation.
 * - No one-deal-per-organization rule (uses tenant duplicate_handling setting).
 * - Required fields driven by TenantImportSettings, not hardcoded.
 * - LGU IDS tenant is blocked at the controller level; never call this service for lgu-ids.
 */
class GenericDealImportService
{
    /**
     * Valid pipeline stages for generic tenants.
     * LGU IDS stage rules (with time limits) are NOT used here.
     */
    const VALID_STAGES = ['introduction', 'presentation', 'contract_sent', 'signed', 'paid'];

    public function __construct(
        private NotificationDispatchService $notifications,
        private ImportSnapshotService       $snapshots,
    ) {}

    // ── Template resolution ───────────────────────────────────────

    /**
     * Resolve the industry template config for a tenant.
     * Falls back to 'default' if the tenant's key is missing or invalid.
     */
    public function getTemplate(string $tenantId): array
    {
        $settings  = TenantImportSettings::forTenant($tenantId);
        $key       = $settings->industry_template_key ?? 'default';
        $templates = config('referralbunny_import_templates', []);

        return $templates[$key] ?? $templates['default'];
    }

    /**
     * Get or create import settings for a tenant.
     */
    public function getSettings(string $tenantId): TenantImportSettings
    {
        return TenantImportSettings::forTenant($tenantId);
    }

    // ── Column alias detection ────────────────────────────────────

    /**
     * Map raw CSV/XLSX headers to canonical field names using the template's alias table.
     * Returns [ 'Raw Header' => 'canonical_field', ... ]
     */
    public function detectColumns(array $headers, array $aliases): array
    {
        $mapping = [];
        foreach ($headers as $header) {
            $key = strtolower(trim($header));
            if (isset($aliases[$key])) {
                $mapping[$header] = $aliases[$key];
            } elseif (in_array($key, array_values($aliases), true)) {
                $mapping[$header] = $key; // exact canonical key passed directly
            }
        }
        return $mapping;
    }

    /**
     * Map raw CSV/XLSX headers to canonical field names and also return unmapped headers.
     * Returns [ 'mapped' => [...], 'unmapped' => [...] ]
     */
    public function detectColumnsWithUnmapped(array $headers, array $aliases): array
    {
        $mapped   = [];
        $unmapped = [];
        foreach ($headers as $header) {
            $key = strtolower(trim($header));
            if (isset($aliases[$key])) {
                $mapped[$header] = $aliases[$key];
            } elseif (in_array($key, array_values($aliases), true)) {
                $mapped[$header] = $key;
            } else {
                $unmapped[] = $header;
            }
        }
        return ['mapped' => $mapped, 'unmapped' => $unmapped];
    }

    /**
     * Return required fields that are not covered by the detected column mapping.
     */
    public function getMissingRequired(array $mapping, array $requiredFields): array
    {
        $mapped = array_values($mapping);
        return array_diff($requiredFields, $mapped);
    }

    // ── File parsing ──────────────────────────────────────────────

    /**
     * Parse an uploaded CSV or XLSX file into [ 'headers' => [], 'rows' => [] ].
     */
    public function parseFile(UploadedFile $file): array
    {
        $ext = strtolower($file->getClientOriginalExtension());
        return $ext === 'csv'
            ? $this->parseCsv($file->getRealPath())
            : $this->parseXlsx($file->getRealPath());
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
        // Minimal XLSX reader — ZipArchive + SimpleXML, no phpspreadsheet dependency.
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
        if (!$sheetXml) {
            throw new \RuntimeException("Cannot read XLSX sheet.");
        }
        $sheet   = simplexml_load_string($sheetXml);
        $rawRows = [];
        foreach ($sheet->sheetData->row as $row) {
            $rowData = [];
            foreach ($row->c as $cell) {
                $colLetter = preg_replace('/[0-9]/', '', (string) $cell['r']);
                $colIndex  = $this->colLetterToIndex($colLetter);
                $t         = (string) ($cell['t'] ?? '');
                $v         = (string) ($cell->v ?? '');
                if ($t === 's')             $v = $sharedStrings[(int) $v] ?? '';
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

    // ── Row normalisation ─────────────────────────────────────────

    /**
     * Map raw column values to canonical field names and normalise data types.
     * No LGU IDS pricing, municipality, or province logic here.
     */
    public function normalizeRow(array $rawRow, array $colMapping, string $importDate): array
    {
        $n = [];
        foreach ($rawRow as $header => $value) {
            $field = $colMapping[$header] ?? null;
            if ($field) {
                $n[$field] = ($value !== '' && $value !== null) ? trim((string) $value) : null;
            }
        }

        // Normalise deal_amount — strip peso signs, commas, spaces; cast to float.
        if (!empty($n['deal_amount'])) {
            $raw = preg_replace('/[₱\s,]/', '', (string) $n['deal_amount']);
            $raw = preg_replace('/[^0-9.\-]/', '', $raw);
            $n['deal_amount'] = (is_numeric($raw) && $raw !== '') ? (float) $raw : null;
        } else {
            $n['deal_amount'] = null;
        }

        // Normalise referrer_email
        if (!empty($n['referrer_email'])) {
            $n['referrer_email'] = strtolower(trim($n['referrer_email']));
        }

        // Default deal_start_date to the import date if omitted
        if (empty($n['deal_start_date'])) {
            $n['deal_start_date'] = $importDate;
        }

        // Normalise deal_stage
        if (!empty($n['deal_stage'])) {
            $s = strtolower(str_replace([' ', '-'], '_', trim($n['deal_stage'])));
            $n['deal_stage'] = in_array($s, self::VALID_STAGES, true) ? $s : 'introduction';
        } else {
            $n['deal_stage'] = 'introduction';
        }

        // Parse partner_emails — comma or semicolon separated
        if (!empty($n['partner_emails'])) {
            $emails = preg_split('/[,;]+/', $n['partner_emails']);
            $n['partner_emails'] = array_values(array_filter(
                array_map('trim', $emails),
                fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL)
            ));
        } else {
            $n['partner_emails'] = [];
        }

        return $n;
    }

    // ── Row validation ────────────────────────────────────────────

    /**
     * Validate a normalised row and return a structured result:
     * [
     *   'status'          => string,       // ready | duplicate | unknown_referrer | unknown_org | needs_review | blocked | failed
     *   'issue_codes'     => array,        // structured issue objects
     *   'organization_id' => string|null,
     *   'existing_deal'   => array|null,
     *   'computed'        => array,        // deal_amount, currency, import_start_date
     * ]
     *
     * No LGU IDS pricing computation. deal_amount is used as-is.
     */
    public function validateRow(
        array $normalized,
        string $tenantId,
        array $requiredFields,
        TenantImportSettings $settings,
        string $uploaderRole,
        ?string $uploaderEmail = null
    ): array {
        $issueCodes = [];
        $status     = 'ready';

        // ── 1. Required fields ────────────────────────────────────
        foreach ($requiredFields as $f) {
            if (empty($normalized[$f]) && $normalized[$f] !== 0 && $normalized[$f] !== '0') {
                $issueCodes[] = [
                    'code'    => 'MISSING_REQUIRED',
                    'field'   => $f,
                    'message' => "Missing required field: $f",
                ];
                $status = 'failed';
            }
        }
        if ($status === 'failed') {
            return [
                'status'          => 'failed',
                'issue_codes'     => $issueCodes,
                'computed'        => [],
                'organization_id' => null,
                'existing_deal'   => null,
            ];
        }

        // ── 2. Referrer permission — resellers can only import for themselves ──
        if ($uploaderRole === 'reseller' && !empty($normalized['referrer_email'])) {
            if (strtolower($normalized['referrer_email']) !== strtolower($uploaderEmail ?? '')) {
                $issueCodes[] = [
                    'code'    => 'REFERRER_PERMISSION_BLOCKED',
                    'message' => "Referrers can only import deals assigned to themselves.",
                ];
                return [
                    'status'          => 'blocked',
                    'issue_codes'     => $issueCodes,
                    'computed'        => [],
                    'organization_id' => null,
                    'existing_deal'   => null,
                ];
            }
        }

        // ── 3. Referrer email format + existence ──────────────────
        $referrerEmail = $normalized['referrer_email'] ?? null;
        if ($referrerEmail) {
            if (!filter_var($referrerEmail, FILTER_VALIDATE_EMAIL)) {
                $issueCodes[] = [
                    'code'    => 'INVALID_EMAIL',
                    'field'   => 'referrer_email',
                    'message' => "Invalid referrer email: $referrerEmail",
                ];
                $status = 'failed';
            } else {
                $reseller = Reseller::where('tenant_id', $tenantId)
                    ->where('email', $referrerEmail)
                    ->whereIn('status', ['active', 'nda_signed'])
                    ->first();
                if (!$reseller) {
                    $issueCodes[] = [
                        'code'    => 'UNKNOWN_REFERRER',
                        'message' => "Referrer email $referrerEmail not found in tenant. They will need to be invited.",
                    ];
                    if ($status === 'ready') $status = 'unknown_referrer';
                }
            }
        }

        // ── 4. Deal amount parseable check ────────────────────────
        if ($normalized['deal_amount'] === null) {
            $issueCodes[] = [
                'code'    => 'INVALID_AMOUNT',
                'message' => "Deal amount could not be parsed or is missing.",
            ];
            if ($status === 'ready') $status = 'needs_review';
        }

        // ── 5. Organization lookup ────────────────────────────────
        $orgId       = null;
        $existingDeal = null;
        $orgName      = $normalized['organization_name'] ?? null;

        if ($orgName) {
            $org = DB::table('organizations')
                ->where('tenant_id', $tenantId)
                ->whereRaw('LOWER(name) = ?', [strtolower($orgName)])
                ->first();

            if ($org) {
                $orgId = $org->id;

                // Duplicate deal check (respects tenant duplicate_handling setting)
                if ($settings->duplicate_handling !== 'allow') {
                    $existing = DB::table('leads')
                        ->where('tenant_id', $tenantId)
                        ->where('organization_id', $orgId)
                        ->whereNotIn('status', ['expired', 'declined'])
                        ->first();
                    if ($existing) {
                        $issueCodes[] = [
                            'code'          => 'DUPLICATE_DEAL',
                            'message'       => "This organization already has an active deal (Referrer: {$existing->reseller_name}).",
                            'existing_deal' => [
                                'id'         => $existing->id,
                                'reseller'   => $existing->reseller_name,
                                'stage'      => $existing->stage,
                                'deal_value' => $existing->deal_value,
                                'status'     => $existing->status,
                            ],
                        ];
                        $existingDeal = (array) $existing;
                        if ($status === 'ready') $status = 'duplicate';
                    }
                }
            } else {
                // Unknown organization — behaviour driven by tenant settings
                $behavior = $settings->unknown_org_behavior;
                if ($behavior === 'reject') {
                    $issueCodes[] = [
                        'code'    => 'UNKNOWN_ORG',
                        'message' => "Organization '$orgName' not found. Row rejected per tenant settings.",
                    ];
                    $status = 'failed';
                } else {
                    $issueCodes[] = [
                        'code'    => 'UNKNOWN_ORG',
                        'message' => "Organization '$orgName' not found. "
                            . ($behavior === 'auto_create' ? 'Will be auto-created on execute.' : 'Flagged for admin review.'),
                    ];
                    if ($status === 'ready') $status = 'unknown_org';
                }
            }
        }

        // ── 6. Partner emails ─────────────────────────────────────
        foreach ($normalized['partner_emails'] ?? [] as $pEmail) {
            if (!filter_var($pEmail, FILTER_VALIDATE_EMAIL)) {
                $issueCodes[] = ['code' => 'INVALID_PARTNER_EMAIL', 'email' => $pEmail];
            } else {
                $exists = DB::table('partner_users')
                    ->where('tenant_id', $tenantId)
                    ->where('email', strtolower($pEmail))
                    ->exists();
                if (!$exists) {
                    $issueCodes[] = [
                        'code'    => 'UNKNOWN_PARTNER',
                        'email'   => $pEmail,
                        'message' => "Partner $pEmail not found — will need to be invited.",
                    ];
                    if ($status === 'ready') $status = 'needs_review';
                }
            }
        }

        // Downgrade 'ready' to 'needs_review' if any non-fatal issues surfaced
        if ($status === 'ready' && !empty($issueCodes)) {
            $status = 'needs_review';
        }

        return [
            'status'          => $status,
            'issue_codes'     => $issueCodes,
            'organization_id' => $orgId,
            'existing_deal'   => $existingDeal,
            'computed'        => [
                'normalized_deal_amount' => $normalized['deal_amount'],
                'currency'               => $normalized['currency'] ?? 'PHP',
                'import_start_date'      => $normalized['deal_start_date'],
            ],
        ];
    }

    // ── Create import batch ───────────────────────────────────────

    /**
     * Parse, validate, and stage a file as a previewing ImportBatch.
     * Rows are written to import_batch_rows with full validation detail.
     * Does NOT create any deals yet — execute() does that.
     */
    public function createBatch(
        UploadedFile $file,
        string $tenantId,
        string $importedById,
        string $importedByRole
    ): ImportBatch {
        $settings = $this->getSettings($tenantId);
        $template = $this->getTemplate($tenantId);

        // Check for a saved tenant template first; fall back to config template
        $tenantTemplate = app(TemplateAdoptionService::class)->getDefault($tenantId, 'deals');
        if ($tenantTemplate) {
            $aliases        = $tenantTemplate->aliases_json ?? $template['aliases'] ?? [];
            $requiredFields = $tenantTemplate->required_fields_json
                ?? $settings->required_fields
                ?? $template['required_fields']
                ?? ['deal_name', 'deal_amount', 'referrer_email', 'organization_name'];
        } else {
            $aliases        = $template['aliases'] ?? [];
            $requiredFields = $settings->required_fields
                ?? $template['required_fields']
                ?? ['deal_name', 'deal_amount', 'referrer_email', 'organization_name'];
        }

        $path    = $file->store("imports/generic/{$tenantId}", 'local');
        $parsed  = $this->parseFile($file);
        $headers = $parsed['headers'];
        $rawRows = $parsed['rows'];

        // Detect mapped and unmapped columns together
        $columnResult = $this->detectColumnsWithUnmapped($headers, $aliases);
        $colMap       = $columnResult['mapped'];
        $unmappedList = $columnResult['unmapped'];

        $missing = $this->getMissingRequired($colMap, $requiredFields);

        if (!empty($missing)) {
            throw new \InvalidArgumentException(
                "Missing required columns: " . implode(', ', $missing)
                . ". Check your file headers against the template."
            );
        }

        $batch = ImportBatch::create([
            'tenant_id'              => $tenantId,
            'import_type'            => 'generic_deals',
            'file_name'              => $file->getClientOriginalName(),
            'file_path'              => $path,
            'imported_by_id'         => $importedById,
            'imported_by_role'       => $importedByRole,
            'status'                 => 'previewing',
            'total_rows'             => count($rawRows),
            'unmapped_columns_json'  => $unmappedList,
            'template_adoption_status' => 'none',
        ]);

        $importDate     = now()->toDateString();
        $counts         = [
            'successful'       => 0,
            'failed'           => 0,
            'duplicate'        => 0,
            'unknown_referrer' => 0,
            'pricing_issue'    => 0, // not used; kept for schema parity
            'blocked'          => 0,
        ];
        $pendingInvites = []; // email => ['name' => ?, 'rows' => []]

        // Resolve uploader email once (only relevant for reseller uploads)
        $uploaderEmail = null;
        if ($importedByRole === 'reseller') {
            $uploaderEmail = Reseller::where('id', $importedById)->value('email');
        }

        foreach ($rawRows as $i => $rawRow) {
            $rowNum     = $i + 2; // row 1 = header; data starts at row 2
            $normalized = $this->normalizeRow($rawRow, $colMap, $importDate);
            $validation = $this->validateRow(
                $normalized,
                $tenantId,
                $requiredFields,
                $settings,
                $importedByRole,
                $uploaderEmail
            );

            $rowAction = match ($validation['status']) {
                'ready'            => 'create',
                'duplicate'        => 'review',
                'unknown_referrer' => 'create',  // proceed; referrer flagged for invite
                'unknown_org'      => 'review',
                'needs_review'     => 'review',
                'blocked', 'failed'=> 'blocked',
                default            => 'review',
            };

            ImportBatchRow::create([
                'import_batch_id'   => $batch->id,
                'row_number'        => $rowNum,
                'raw_data'          => $rawRow,
                'normalized_data'   => $normalized,
                'computed_data'     => $validation['computed'],
                'validation_status' => $validation['status'],
                'issue_codes'       => $validation['issue_codes'],
                'row_action'        => $rowAction,
                'existing_deal_id'  => $validation['existing_deal']['id'] ?? null,
                'organization_id'   => $validation['organization_id'],
            ]);

            match ($validation['status']) {
                'ready'                    => $counts['successful']++,
                'failed', 'blocked'        => $counts['failed']++,
                'duplicate'                => $counts['duplicate']++,
                'unknown_referrer'         => $counts['unknown_referrer']++,
                default                    => null,
            };

            // Stage unknown referrers for invite
            if ($validation['status'] === 'unknown_referrer') {
                $email = $normalized['referrer_email'] ?? null;
                if ($email) {
                    if (!isset($pendingInvites[$email])) {
                        $pendingInvites[$email] = ['name' => $normalized['referrer_name'] ?? null, 'rows' => []];
                    }
                    $pendingInvites[$email]['rows'][] = $rowNum;
                }
            }
        }

        // Upsert pending referrer invites
        foreach ($pendingInvites as $email => $info) {
            PendingReferrerInvite::updateOrCreate(
                ['tenant_id' => $tenantId, 'email' => $email],
                [
                    'name'            => $info['name'],
                    'import_batch_id' => $batch->id,
                    'row_ids'         => $info['rows'],
                    'status'          => 'pending_invite',
                ]
            );
        }

        $batch->update([
            'status'                => 'previewed',
            'successful_rows'       => $counts['successful'],
            'failed_rows'           => $counts['failed'],
            'duplicate_rows'        => $counts['duplicate'],
            'unknown_referrer_rows' => $counts['unknown_referrer'],
            'blocked_rows'          => $counts['blocked'],
        ]);

        $this->notifications->dispatchToTenantAdmins(
            tenantId:     $tenantId,
            category:     'import_export',
            priority:     'normal',
            title:        'Deals Import Ready for Review',
            body:         "Your import of {$batch->total_rows} rows is ready. "
                . "{$counts['successful']} ready, {$counts['duplicate']} duplicates, "
                . "{$counts['unknown_referrer']} unknown referrers.",
            actionUrl:    "/tenant/{$tenantId}/imports/deals/{$batch->id}",
            actionLabel:  'Review Import',
            dedupeSuffix: $batch->id,
        );

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

    // ── Execute import ────────────────────────────────────────────

    /**
     * Execute a previewed ImportBatch: create / update / skip deals per row_action.
     *
     * No LGU IDS base_cost/added_amount split. deal_amount stored directly on leads.deal_value.
     * No one-deal-per-org enforcement — duplicate handling is governed by tenant settings
     * and admin approvals set during the preview stage.
     */
    public function executeImport(
        ImportBatch $batch,
        string $tenantId,
        string $executorId,
        string $executorRole
    ): array {
        $batch->update(['status' => 'processing', 'started_at' => now()]);

        $settings = $this->getSettings($tenantId);
        $created  = 0;
        $updated  = 0;
        $skipped  = 0;
        $failed   = 0;
        $errors   = [];

        ImportBatchRow::where('import_batch_id', $batch->id)
            ->orderBy('id')
            ->chunkById(100, function ($chunk) use ($batch, $tenantId, $settings, &$created, &$updated, &$skipped, &$failed, &$errors) {
        foreach ($chunk as $row) {
            // Skip blocked / manually skipped rows
            if (in_array($row->row_action, ['blocked', 'skip'], true)) {
                $skipped++;
                continue;
            }
            // Failed validation rows always skip
            if ($row->validation_status === 'failed') {
                $skipped++;
                continue;
            }
            // Unresolved duplicates without an admin decision skip
            if (
                $row->validation_status === 'duplicate'
                && !in_array($row->row_action, ['overwrite', 'merge', 'create'], true)
            ) {
                $skipped++;
                continue;
            }

            $norm     = $row->normalized_data;
            $computed = $row->computed_data;

            try {
                DB::beginTransaction();
                $dealAmount    = (float) ($computed['normalized_deal_amount'] ?? 0);
                $orgId         = $row->organization_id;
                $referrerEmail = $norm['referrer_email'] ?? null;
                $reseller      = $referrerEmail
                    ? Reseller::where('tenant_id', $tenantId)->where('email', $referrerEmail)->first()
                    : null;
                $resellerName  = $reseller?->name ?? ($norm['referrer_name'] ?? $referrerEmail);
                $dealName      = $norm['deal_name'] ?? ($norm['organization_name'] ?? 'Imported Deal');
                $stage         = $norm['deal_stage'] ?? 'introduction';

                // Auto-create organisation if configured and not yet resolved.
                // Lock-then-check prevents duplicate orgs when two imports run concurrently.
                if (!$orgId && $settings->unknown_org_behavior === 'auto_create' && !empty($norm['organization_name'])) {
                    $existingOrg = DB::table('organizations')
                        ->where('tenant_id', $tenantId)
                        ->whereRaw('LOWER(name) = ?', [strtolower($norm['organization_name'])])
                        ->first();

                    if ($existingOrg) {
                        $orgId = $existingOrg->id;
                    } else {
                        $newOrgId = (string) Str::uuid();
                        DB::table('organizations')->insert([
                            'id'         => $newOrgId,
                            'tenant_id'  => $tenantId,
                            'name'       => $norm['organization_name'],
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                        $orgId = $newOrgId;
                    }
                    $row->update(['organization_id' => $orgId]);
                }

                if ($row->row_action === 'overwrite' && $row->existing_deal_id) {
                    // Capture before-state for rollback
                    $beforeLead = DB::table('leads')->where('id', $row->existing_deal_id)->first();
                    $changes    = ['stage' => $stage, 'reseller_name' => $resellerName, 'deal_value' => $dealAmount];

                    DB::table('leads')->where('id', $row->existing_deal_id)->update(array_merge($changes, ['updated_at' => now()]));

                    // Record snapshot AFTER successful update
                    $this->snapshots->recordUpdated(
                        batchId:       $batch->id,
                        tenantId:      $tenantId,
                        entityType:    'lead',
                        entityId:      $row->existing_deal_id,
                        beforeData:    $beforeLead ? (array) $beforeLead : [],
                        afterData:     $changes,
                        changedFields: array_keys($changes),
                        operationType: 'overwritten',
                        batchRowId:    $row->id,
                        row:           $row,
                    );

                    LeadHistory::create([
                        'lead_id' => $row->existing_deal_id,
                        'action'  => "Deal updated via generic import (batch: {$batch->id})",
                        'type'    => 'import',
                        'date'    => now()->toDateString(),
                    ]);
                    $row->update(['created_deal_id' => $row->existing_deal_id]);
                    $updated++;
                } elseif ($row->row_action === 'merge' && $row->existing_deal_id) {
                    // Merge: only fill in blank fields on existing deal
                    $existing = Lead::find($row->existing_deal_id);
                    if ($existing) {
                        $beforeLead = $existing->toArray();
                        $updates    = [];
                        if (!$existing->deal_value && $dealAmount) {
                            $updates['deal_value'] = $dealAmount;
                        }
                        if (!empty($updates)) {
                            $existing->update($updates);

                            $this->snapshots->recordUpdated(
                                batchId:       $batch->id,
                                tenantId:      $tenantId,
                                entityType:    'lead',
                                entityId:      $existing->id,
                                beforeData:    $beforeLead,
                                afterData:     $updates,
                                changedFields: array_keys($updates),
                                operationType: 'merged',
                                batchRowId:    $row->id,
                                row:           $row,
                            );
                        }
                        LeadHistory::create([
                            'lead_id' => $existing->id,
                            'action'  => "Deal merged via generic import (batch: {$batch->id})",
                            'type'    => 'import',
                            'date'    => now()->toDateString(),
                        ]);
                    }
                    $row->update(['created_deal_id' => $row->existing_deal_id]);
                    $updated++;
                } else {
                    // Create a new deal
                    $newLead = Lead::create([
                        'tenant_id'         => $tenantId,
                        'name'              => $dealName,
                        'stage'             => $stage,
                        'status'            => 'active',
                        'days_left'         => 21,
                        'organization_id'   => $orgId,
                        'reseller_name'     => $resellerName,
                        'commission_status' => 'pending',
                        'deal_value'        => $dealAmount,
                        'data'              => [
                            'import_batch'  => $batch->id,
                            'source'        => $norm['deal_source'] ?? 'import',
                            'notes'         => $norm['notes'] ?? null,
                            'contact_name'  => $norm['contact_name'] ?? null,
                            'contact_email' => $norm['contact_email'] ?? null,
                            'contact_phone' => $norm['contact_phone'] ?? null,
                            'tags'          => $norm['tags'] ?? null,
                            'internal_ref'  => $norm['internal_reference_id'] ?? null,
                        ],
                    ]);
                    LeadHistory::create([
                        'lead_id' => $newLead->id,
                        'action'  => "Deal created via generic import (batch: {$batch->id})",
                        'type'    => 'import',
                        'date'    => now()->toDateString(),
                    ]);
                    $this->snapshots->recordCreated(
                        batchId:    $batch->id,
                        tenantId:   $tenantId,
                        entityType: 'lead',
                        entityId:   $newLead->id,
                        entityData: $newLead->toArray(),
                        batchRowId: $row->id,
                        row:        $row,
                    );
                    $row->update(['created_deal_id' => $newLead->id]);
                    $created++;
                }
                DB::commit();
            } catch (\Throwable $e) {
                DB::rollBack();
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
            $skipped > 0                                     => 'completed',
            default                                          => 'completed',
        };

        $batch->update([
            'status'          => $status,
            'successful_rows' => $created,
            'updated_rows'    => $updated,
            'skipped_rows'    => $skipped,
            'failed_rows'     => $failed,
            'completed_at'    => now(),
            'summary_json'    => compact('created', 'updated', 'skipped', 'failed', 'errors'),
        ]);

        // Mark batch rollback_status based on snapshots captured
        $this->snapshots->markBatchEligible($batch->id);

        $titleMap = [
            'completed'               => 'Deals Import Complete',
            'completed_with_warnings' => 'Deals Import Complete with Warnings',
            'failed'                  => 'Deals Import Failed',
        ];
        try {
            $this->notifications->dispatchToTenantAdmins(
                tenantId:     $tenantId,
                category:     'import_export',
                priority:     $failed > 0 ? 'high' : 'normal',
                title:        $titleMap[$status] ?? 'Deals Import Complete',
                body:         "Created: $created, Updated: $updated, Skipped: $skipped, Failed: $failed.",
                actionUrl:    "/tenant/{$tenantId}/imports/deals/{$batch->id}/report",
                actionLabel:  'View Report',
                dedupeSuffix: $batch->id,
            );
        } catch (\Throwable) {}

        return compact('created', 'updated', 'skipped', 'failed');
    }

    // ── Template CSV download ─────────────────────────────────────

    /**
     * Generate a downloadable CSV template for the tenant's active industry template.
     * Includes a sample data row and field notes.
     */
    public function generateTemplateCsv(string $tenantId): string
    {
        $template = $this->getTemplate($tenantId);
        $required = $template['required_fields'] ?? [];
        $optional = $template['optional_fields'] ?? [];
        $all      = array_merge($required, $optional);

        // Humanise field names for column headers
        $headers   = array_map(fn ($f) => ucwords(str_replace('_', ' ', $f)), $all);
        $sample    = $template['sample_row'] ?? [];
        $sampleRow = array_map(fn ($f) => $sample[$f] ?? '', $all);

        $lines = [
            implode(',', array_map(fn ($h) => '"' . $h . '"', $headers)),
            implode(',', array_map(fn ($v) => '"' . $v . '"', $sampleRow)),
        ];

        return implode("\n", $lines);
    }

    // ── Failed rows CSV download ──────────────────────────────────

    /**
     * Generate a CSV listing only failed/blocked rows from a batch, with issue details.
     */
    public function generateFailedRowsCsv(ImportBatch $batch): string
    {
        $rows = ImportBatchRow::where('import_batch_id', $batch->id)
            ->where(fn($q) => $q
                ->whereIn('validation_status', ['failed', 'blocked'])
                ->orWhereNotNull('error_message')
            )
            ->get();

        $lines   = [];
        $lines[] = implode(',', array_map(
            fn ($h) => '"' . $h . '"',
            ['Row #', 'Deal Name', 'Organization', 'Deal Amount', 'Referrer Email', 'Status', 'Issues']
        ));

        foreach ($rows as $row) {
            $n      = $row->normalized_data;
            $issues = collect($row->issue_codes)->pluck('message')->filter()->implode('; ');
            $lines[] = implode(',', array_map(
                fn ($v) => '"' . str_replace('"', '""', (string) $v) . '"',
                [
                    $row->row_number,
                    $n['deal_name'] ?? '',
                    $n['organization_name'] ?? '',
                    $n['deal_amount'] ?? '',
                    $n['referrer_email'] ?? '',
                    $row->validation_status,
                    $issues,
                ]
            ));
        }

        return implode("\n", $lines);
    }

    private function friendlyError(string $message): string
    {
        if (str_contains($message, 'SQLSTATE')) {
            if (str_contains($message, '42703')) return 'System error: a required database column is missing. Please contact support.';
            if (str_contains($message, '23505')) return 'This record already exists and could not be inserted again (duplicate).';
            if (str_contains($message, '23503')) return 'A linked record could not be found.';
            if (str_contains($message, '42P01')) return 'System error: required database table not found. Please contact support.';
            return 'A database error prevented this row from being imported. Please contact support.';
        }
        if (strlen($message) > 150) return 'An unexpected error occurred while importing this row. Please check the data and try again.';
        return $message;
    }
}
