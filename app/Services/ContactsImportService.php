<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\ImportBatch;
use App\Models\ImportBatchRow;
use App\Models\PendingPartnerInvite;
use App\Models\PendingReferrerInvite;
use App\Models\Reseller;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Contacts Import Service.
 *
 * ARCHITECTURAL RULES:
 * - Referrer contacts are PRIVATE to the Referrer who owns them.
 * - The same email can exist under multiple Referrers as SEPARATE records.
 * - NO global unique email constraint on contacts.
 * - Duplicate detection is OWNER-SCOPED (only within same Referrer's contacts).
 * - Partners CANNOT import contacts.
 * - Contact is NOT automatically a user.
 */
class ContactsImportService
{
    const VALID_CONTACT_TYPES = [
        'general_contact', 'organization_contact', 'deal_contact',
        'admin_candidate', 'staff_candidate', 'referrer_candidate', 'partner_candidate',
        'vendor_contact', 'client_contact', 'government_contact', 'other',
    ];

    const VALID_INTENDED_ROLES = [
        'none', 'tenant_admin', 'tenant_staff', 'referrer', 'partner',
        'organization_contact', 'deal_contact',
    ];

    const VALID_VISIBILITY_SCOPES = [
        'tenant', 'team', 'owner_only', 'referrer_private',
        'deal_associated', 'organization_associated',
    ];

    const VALID_CONSENT_STATUSES = [
        'unknown', 'consented', 'not_consented', 'do_not_contact',
    ];

    /**
     * Column aliases → canonical field name.
     * Keys are lowercased header values from uploaded files.
     */
    const COLUMN_ALIASES = [
        // Email
        'email'                     => 'email',
        'email address'             => 'email',
        'e-mail'                    => 'email',
        'e-mail address'            => 'email',
        // Phone
        'phone'                     => 'phone_number',
        'phone number'              => 'phone_number',
        'phone_number'              => 'phone_number',
        'mobile'                    => 'phone_number',
        'mobile number'             => 'phone_number',
        'contact number'            => 'phone_number',
        'telephone'                 => 'phone_number',
        // Names
        'first name'                => 'first_name',
        'first_name'                => 'first_name',
        'given name'                => 'first_name',
        'last name'                 => 'last_name',
        'last_name'                 => 'last_name',
        'surname'                   => 'last_name',
        'family name'               => 'last_name',
        'full name'                 => 'full_name',
        'full_name'                 => 'full_name',
        'name'                      => 'full_name',
        'contact name'              => 'full_name',
        'nickname'                  => 'nickname',
        // Professional
        'job title'                 => 'job_title',
        'job_title'                 => 'job_title',
        'title'                     => 'job_title',
        'position'                  => 'job_title',
        'role'                      => 'job_title',
        'department'                => 'department',
        'team'                      => 'department',
        'company'                   => 'company_or_organization',
        'company or organization'   => 'company_or_organization',
        'company_or_organization'   => 'company_or_organization',
        'organization'              => 'company_or_organization',
        'organization name'         => 'company_or_organization',
        'lgu'                       => 'company_or_organization',
        // Location
        'address'                   => 'address',
        'city'                      => 'city_or_municipality',
        'municipality'              => 'city_or_municipality',
        'city or municipality'      => 'city_or_municipality',
        'city_or_municipality'      => 'city_or_municipality',
        'province'                  => 'province',
        'region'                    => 'region',
        'country'                   => 'country',
        'timezone'                  => 'timezone',
        'language'                  => 'language',
        // Notes / tags
        'notes'                     => 'notes',
        'note'                      => 'notes',
        'tags'                      => 'tags',
        'tag'                       => 'tags',
        // ReferralBunny relationship
        'contact type'              => 'contact_type',
        'contact_type'              => 'contact_type',
        'type'                      => 'contact_type',
        'intended role'             => 'intended_role',
        'intended_role'             => 'intended_role',
        'visibility scope'          => 'visibility_scope',
        'visibility_scope'          => 'visibility_scope',
        'visibility'                => 'visibility_scope',
        'source'                    => 'source',
        'deal source'               => 'source',
        'deal name'                 => 'deal_name',
        'deal_name'                 => 'deal_name',
        'deal identifier'           => 'deal_identifier',
        'deal_identifier'           => 'deal_identifier',
        'deal reference'            => 'deal_reference',
        'deal_reference'            => 'deal_reference',
        'organization identifier'   => 'organization_identifier',
        'organization_identifier'   => 'organization_identifier',
        // Referrer association
        'referrer email'            => 'associated_referrer_email',
        'referrer_email'            => 'associated_referrer_email',
        'associated referrer email' => 'associated_referrer_email',
        'associated_referrer_email' => 'associated_referrer_email',
        'reseller email'            => 'associated_referrer_email',
        // Partner association
        'partner email'             => 'associated_partner_email',
        'partner emails'            => 'associated_partner_email',
        'associated_partner_email'  => 'associated_partner_email',
        // Internal ref
        'internal reference id'     => 'internal_reference_id',
        'internal_reference_id'     => 'internal_reference_id',
        'ref id'                    => 'internal_reference_id',
        'reference id'              => 'internal_reference_id',
        // Invite flags
        'invite as admin'           => 'invite_as_admin',
        'invite_as_admin'           => 'invite_as_admin',
        'invite as referrer'        => 'invite_as_referrer',
        'invite_as_referrer'        => 'invite_as_referrer',
        'invite as partner'         => 'invite_as_partner',
        'invite_as_partner'         => 'invite_as_partner',
        // Consent
        'consent status'            => 'consent_status',
        'consent_status'            => 'consent_status',
        'consent source'            => 'consent_source',
        'do not contact'            => 'do_not_contact',
        'do_not_contact'            => 'do_not_contact',
        'dnc'                       => 'do_not_contact',
        'communication preference'  => 'communication_preference',
        'communication_preference'  => 'communication_preference',
        // Alternate contact
        'alternate email'           => 'alternate_email',
        'alternate_email'           => 'alternate_email',
        'alternate phone'           => 'alternate_phone',
        'alternate_phone'           => 'alternate_phone',
    ];

    const REQUIRED_COLUMNS = []; // No single required column — need at least email OR phone_number

    public function __construct(
        private NotificationDispatchService $notifications,
        private ImportSnapshotService       $snapshots,
    ) {}

    // ── Column detection ──────────────────────────────────────────

    /**
     * Map raw CSV/XLSX headers to canonical field names using the alias table.
     * Returns [ 'Raw Header' => 'canonical_field', ... ]
     */
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

    /**
     * Returns true if the column mapping has neither email nor phone_number mapped.
     * At least one identifier is required for meaningful contacts.
     */
    public function hasMissingContactIdentifier(array $mapping): bool
    {
        $mapped = array_values($mapping);
        return !in_array('email', $mapped) && !in_array('phone_number', $mapped);
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
        if (($h = fopen($path, 'r')) === false) {
            throw new \RuntimeException("Cannot open CSV file.");
        }
        $headers = null;
        while (($row = fgetcsv($h, 0, ',')) !== false) {
            if (!$headers) {
                $headers = $row;
                continue;
            }
            $combined = [];
            foreach ($headers as $i => $hdr) {
                $combined[$hdr] = $row[$i] ?? null;
            }
            $rows[] = $combined;
        }
        fclose($h);
        return ['headers' => $headers ?? [], 'rows' => $rows];
    }

    private function parseXlsx(string $path): array
    {
        // Minimal XLSX reader — ZipArchive + SimpleXML, no phpspreadsheet dependency.
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            throw new \RuntimeException("Cannot open XLSX file.");
        }
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
                $colIndex = $this->colLetterToIndex(preg_replace('/[0-9]/', '', (string) $cell['r']));
                $t        = (string) ($cell['t'] ?? '');
                $v        = (string) ($cell->v ?? '');
                if ($t === 's')             $v = $sharedStrings[(int) $v] ?? '';
                elseif ($t === 'inlineStr') $v = (string) ($cell->is->t ?? '');
                $rowData[$colIndex] = $v;
            }
            $rawRows[] = $rowData;
        }
        if (empty($rawRows)) return ['headers' => [], 'rows' => []];
        $headers = $rawRows[0];
        $result  = [];
        foreach (array_slice($rawRows, 1) as $rawRow) {
            $combined = [];
            foreach ($headers as $i => $hdr) {
                $combined[$hdr] = $rawRow[$i] ?? null;
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
     */
    public function normalizeRow(array $rawRow, array $colMapping): array
    {
        $n = [];
        foreach ($rawRow as $header => $value) {
            $field = $colMapping[$header] ?? null;
            if ($field) {
                $n[$field] = ($value !== '' && $value !== null) ? trim((string) $value) : null;
            }
        }

        // Normalize email addresses
        if (!empty($n['email'])) {
            $n['email'] = strtolower(trim($n['email']));
        }
        if (!empty($n['alternate_email'])) {
            $n['alternate_email'] = strtolower(trim($n['alternate_email']));
        }

        // Normalize boolean flags
        foreach (['invite_as_admin', 'invite_as_referrer', 'invite_as_partner', 'do_not_contact'] as $flag) {
            if (isset($n[$flag])) {
                $v        = strtolower(trim((string) $n[$flag]));
                $n[$flag] = in_array($v, ['true', 'yes', '1', 'y']);
            } else {
                $n[$flag] = false;
            }
        }

        // Normalize contact_type
        if (!empty($n['contact_type'])) {
            $ct               = strtolower(str_replace([' ', '-'], '_', trim($n['contact_type'])));
            $n['contact_type'] = in_array($ct, self::VALID_CONTACT_TYPES) ? $ct : 'general_contact';
        } else {
            $n['contact_type'] = 'general_contact';
        }

        // Normalize intended_role
        if (!empty($n['intended_role'])) {
            $ir               = strtolower(str_replace([' ', '-'], '_', trim($n['intended_role'])));
            $n['intended_role'] = in_array($ir, self::VALID_INTENDED_ROLES) ? $ir : null;
        }

        // Normalize visibility_scope
        if (!empty($n['visibility_scope'])) {
            $vs                 = strtolower(str_replace([' ', '-'], '_', trim($n['visibility_scope'])));
            $n['visibility_scope'] = in_array($vs, self::VALID_VISIBILITY_SCOPES) ? $vs : null;
        }

        // Parse tags: comma-separated string → array
        if (!empty($n['tags'])) {
            $n['tags'] = array_values(array_filter(array_map('trim', explode(',', $n['tags']))));
        } else {
            $n['tags'] = [];
        }

        // Derive full_name from first+last if not provided
        if (empty($n['full_name']) && (!empty($n['first_name']) || !empty($n['last_name']))) {
            $n['full_name'] = trim(($n['first_name'] ?? '') . ' ' . ($n['last_name'] ?? ''));
        }

        return $n;
    }

    // ── Row validation ────────────────────────────────────────────

    /**
     * Validate a normalised row and return a structured result.
     *
     * Key rules enforced here:
     * - Duplicate detection is OWNER-SCOPED (per Referrer or per tenant-admin pool).
     * - Same email under different Referrers = informational flag, NOT blocking.
     * - Partners are blocked entirely.
     * - Referrers cannot access deals not assigned to them.
     */
    public function validateRow(
        array   $normalized,
        string  $tenantId,
        string  $uploaderRole,
        ?string $uploaderResellerId = null,
        ?string $uploaderEmail      = null
    ): array {
        $issueCodes = [];
        $status     = 'ready';

        // ── 1. Must have at least email or phone ──────────────────
        $hasEmail = !empty($normalized['email']);
        $hasPhone = !empty($normalized['phone_number']);

        if (!$hasEmail && !$hasPhone) {
            $issueCodes[] = [
                'code'    => 'MISSING_IDENTIFIER',
                'message' => 'Row must have at least one of: email, phone number.',
            ];
            return [
                'status'              => 'failed',
                'issue_codes'         => $issueCodes,
                'computed'            => [],
                'organization_id'     => null,
                'existing_contact_id' => null,
                'existing_deal_id'    => null,
            ];
        }

        // ── 2. Email format ───────────────────────────────────────
        if ($hasEmail && !filter_var($normalized['email'], FILTER_VALIDATE_EMAIL)) {
            $issueCodes[] = [
                'code'    => 'INVALID_EMAIL',
                'message' => "Invalid email: {$normalized['email']}",
            ];
            $status = 'failed';
        }

        // ── 3. Do not contact flag ────────────────────────────────
        if ($normalized['do_not_contact'] ?? false) {
            $issueCodes[] = [
                'code'    => 'DO_NOT_CONTACT',
                'message' => 'Contact is marked as Do Not Contact. Will be imported but invites will not be sent.',
            ];
            if ($status === 'ready') $status = 'do_not_contact';
        }

        // ── 4. Partners are blocked entirely ─────────────────────
        if ($uploaderRole === 'partner') {
            $issueCodes[] = [
                'code'    => 'PARTNER_BLOCKED',
                'message' => 'Partners cannot import contacts.',
            ];
            return [
                'status'              => 'blocked',
                'issue_codes'         => $issueCodes,
                'computed'            => [],
                'organization_id'     => null,
                'existing_contact_id' => null,
                'existing_deal_id'    => null,
            ];
        }

        // ── 5. Referrer-specific checks ───────────────────────────
        $existingDealId = null;

        if ($uploaderRole === 'reseller') {
            // Referrers can only access deals assigned to them
            $dealRef = $normalized['deal_name'] ?? $normalized['deal_identifier'] ?? $normalized['deal_reference'] ?? null;
            if ($dealRef) {
                $deal = DB::table('leads')
                    ->where('tenant_id', $tenantId)
                    ->where(function ($q) use ($dealRef) {
                        $q->where('name', $dealRef)->orWhere('id', $dealRef);
                    })
                    ->first();

                if ($deal) {
                    // Verify deal is assigned to the uploading referrer
                    if ($deal->reseller_name && !str_contains(
                        strtolower($deal->reseller_name),
                        strtolower($uploaderEmail ?? '')
                    )) {
                        $issueCodes[] = [
                            'code'    => 'DEAL_ACCESS_BLOCKED',
                            'message' => 'Referrers can only import contacts for deals assigned to them.',
                        ];
                        return [
                            'status'              => 'blocked',
                            'issue_codes'         => $issueCodes,
                            'computed'            => [],
                            'organization_id'     => null,
                            'existing_contact_id' => null,
                            'existing_deal_id'    => null,
                        ];
                    }
                    $existingDealId = $deal->id;
                } else {
                    $issueCodes[] = [
                        'code'    => 'UNKNOWN_DEAL',
                        'message' => "Deal '$dealRef' not found or not assigned to you.",
                    ];
                    if ($status === 'ready') $status = 'unknown_deal';
                }
            }

            // Referrers cannot stage admin/staff invitations
            if ($normalized['invite_as_admin'] ?? false) {
                $issueCodes[] = [
                    'code'    => 'INVITE_BLOCKED',
                    'message' => 'Referrers cannot stage admin invitations.',
                ];
                $normalized['invite_as_admin'] = false;
                if ($status === 'ready') $status = 'needs_review';
            }
        }

        // ── 6. Organization lookup ────────────────────────────────
        $orgId   = null;
        $orgName = $normalized['organization_identifier'] ?? $normalized['company_or_organization'] ?? null;

        if ($orgName) {
            $org = DB::table('organizations')
                ->where('tenant_id', $tenantId)
                ->whereRaw('LOWER(name) = ?', [strtolower($orgName)])
                ->first();

            if ($org) {
                $orgId = $org->id;
            } else {
                $issueCodes[] = [
                    'code'    => 'UNKNOWN_ORG',
                    'message' => "Organization '$orgName' not found. Contact will be imported without organization link.",
                ];
                if ($status === 'ready') $status = 'unknown_org';
            }
        }

        // ── 7. Deal lookup (admin path — not already resolved above) ──
        if (!$existingDealId && $uploaderRole !== 'reseller') {
            $dealRef = $normalized['deal_name'] ?? $normalized['deal_identifier'] ?? $normalized['deal_reference'] ?? null;
            if ($dealRef) {
                $deal = DB::table('leads')
                    ->where('tenant_id', $tenantId)
                    ->where(function ($q) use ($dealRef) {
                        $q->where('name', $dealRef)->orWhere('id', $dealRef);
                    })
                    ->first();

                if ($deal) {
                    $existingDealId = $deal->id;
                } else {
                    $issueCodes[] = [
                        'code'    => 'UNKNOWN_DEAL',
                        'message' => "Deal '$dealRef' not found.",
                    ];
                    if ($status === 'ready') $status = 'unknown_deal';
                }
            }
        }

        // ── 8. Owner-scoped duplicate detection ───────────────────
        // CRITICAL: each Referrer's contacts are private and independent.
        // The same email can exist under multiple Referrers as separate records.
        $existingContactId = null;

        if ($hasEmail || $hasPhone) {
            // Check within the same owner's contact pool
            $dupQuery = DB::table('contacts')->where('tenant_id', $tenantId);

            if ($uploaderRole === 'reseller') {
                // Referrer: only check within this Referrer's owned contacts
                $dupQuery->where('owner_referrer_id', $uploaderResellerId);
            } else {
                // Tenant admin: check within the tenant-wide non-referrer-owned pool
                $dupQuery->whereNull('owner_referrer_id');
            }

            if ($hasEmail) {
                $existing = (clone $dupQuery)->where('email', $normalized['email'])->first();
            } else {
                $existing = null;
            }

            if ($existing) {
                $existingContactId = $existing->id;
                $issueCodes[]      = [
                    'code'             => 'DUPLICATE_SAME_OWNER',
                    'message'          => 'A contact with this email already exists in your contact list.',
                    'existing_contact' => [
                        'id'    => $existing->id,
                        'name'  => trim(($existing->first_name ?? '') . ' ' . ($existing->last_name ?? '')),
                        'email' => $existing->email,
                    ],
                ];
                if ($status === 'ready') $status = 'duplicate';
            }

            // Informational: same email exists under a different Referrer (non-blocking)
            // Only relevant when admin is importing (tenant-admin can see cross-referrer info)
            if ($hasEmail && $uploaderRole !== 'reseller') {
                $otherReferrerCount = DB::table('contacts')
                    ->where('tenant_id', $tenantId)
                    ->where('email', $normalized['email'])
                    ->whereNotNull('owner_referrer_id')
                    ->count();

                if ($otherReferrerCount > 0) {
                    $issueCodes[] = [
                        'code'    => 'SAME_EMAIL_OTHER_REFERRER',
                        'message' => "This email exists under $otherReferrerCount other Referrer(s). Those contacts remain separate and private.",
                        'count'   => $otherReferrerCount,
                    ];
                    if ($status === 'ready') $status = 'same_email_other_referrer';
                }
            }

            // Secondary duplicate: phone (only if email duplicate not already found)
            if ($hasPhone && !$existing) {
                $dupPhone = DB::table('contacts')
                    ->where('tenant_id', $tenantId)
                    ->when(
                        $uploaderRole === 'reseller',
                        fn ($q) => $q->where('owner_referrer_id', $uploaderResellerId)
                    )
                    ->when(
                        $uploaderRole !== 'reseller',
                        fn ($q) => $q->whereNull('owner_referrer_id')
                    )
                    ->where('phone', $normalized['phone_number'])
                    ->first();

                if ($dupPhone) {
                    $existingContactId = $dupPhone->id;
                    $issueCodes[]      = [
                        'code'             => 'POSSIBLE_DUPLICATE_PHONE',
                        'message'          => 'A contact with this phone number already exists.',
                        'existing_contact' => [
                            'id'    => $dupPhone->id,
                            'name'  => trim(($dupPhone->first_name ?? '') . ' ' . ($dupPhone->last_name ?? '')),
                            'phone' => $dupPhone->phone,
                        ],
                    ];
                    if ($status === 'ready') $status = 'possible_duplicate';
                }
            }

            // Check if email belongs to an existing user (informational)
            if ($hasEmail) {
                $existingUser = DB::table('tenant_users')
                    ->where('email', $normalized['email'])
                    ->first()
                    ?? DB::table('resellers')
                        ->where('tenant_id', $tenantId)
                        ->where('email', $normalized['email'])
                        ->first();

                if ($existingUser) {
                    $issueCodes[] = [
                        'code'    => 'EXISTING_USER',
                        'message' => 'This email already belongs to an existing user account.',
                    ];
                    if ($status === 'ready') $status = 'existing_user';
                }
            }
        }

        // ── 9. Partner staging validation ─────────────────────────
        if ($normalized['invite_as_partner'] ?? false) {
            if ($uploaderRole === 'reseller' && !$existingDealId) {
                $issueCodes[] = [
                    'code'    => 'PARTNER_INVITE_NO_DEAL',
                    'message' => 'Partner staging requires a valid deal association.',
                ];
                $normalized['invite_as_partner'] = false;
                if ($status === 'ready') $status = 'needs_review';
            }
        }

        // Downgrade 'ready' to 'needs_review' if any non-fatal issues surfaced
        if ($status === 'ready' && !empty($issueCodes)) {
            $status = 'needs_review';
        }

        return [
            'status'              => $status,
            'issue_codes'         => $issueCodes,
            'organization_id'     => $orgId,
            'existing_contact_id' => $existingContactId,
            'existing_deal_id'    => $existingDealId,
            'computed'            => [
                'display_name'  => trim(($normalized['full_name'] ?? '') ?: (($normalized['first_name'] ?? '') . ' ' . ($normalized['last_name'] ?? ''))),
                'has_email'     => $hasEmail,
                'has_phone'     => $hasPhone,
                'contact_type'  => $normalized['contact_type'] ?? 'general_contact',
                'intended_role' => $normalized['intended_role'] ?? null,
            ],
        ];
    }

    // ── Create import batch ───────────────────────────────────────

    /**
     * Parse, validate, and stage a file as a previewing ImportBatch.
     * Rows are written to import_batch_rows with full validation detail.
     * Does NOT create any contacts yet — executeImport() does that.
     *
     * Row limits: Referrers max 1,000 rows; Admins max 10,000 rows.
     */
    public function createBatch(
        UploadedFile $file,
        string       $tenantId,
        string       $importedById,
        string       $importedByRole,
        ?string      $importedByResellerId = null
    ): ImportBatch {
        if ($importedByRole === 'partner') {
            throw new \InvalidArgumentException("Partners cannot import contacts.");
        }

        $rowLimit = $importedByRole === 'reseller' ? 1000 : 10000;

        $path    = $file->store("imports/contacts/{$tenantId}", 'local');
        $parsed  = $this->parseFile($file);
        $headers = $parsed['headers'];
        $rawRows = $parsed['rows'];
        $colMap  = $this->detectColumns($headers);

        if ($this->hasMissingContactIdentifier($colMap)) {
            throw new \InvalidArgumentException(
                "File must contain at least an 'Email' or 'Phone Number' column."
            );
        }

        if (count($rawRows) > $rowLimit) {
            throw new \InvalidArgumentException(
                "Import exceeds the {$rowLimit}-row limit for your role."
            );
        }

        // Resolve the uploader's email once (used for Referrer deal-access checks)
        $uploaderEmail = null;
        if ($importedByRole === 'reseller') {
            $uploaderEmail = Reseller::where('id', $importedById)->value('email');
        }

        $batch = ImportBatch::create([
            'tenant_id'        => $tenantId,
            'import_type'      => 'contacts',
            'file_name'        => $file->getClientOriginalName(),
            'file_path'        => $path,
            'imported_by_id'   => $importedById,
            'imported_by_role' => $importedByRole,
            'status'           => 'previewing',
            'total_rows'       => count($rawRows),
        ]);

        $counts = [
            'successful'           => 0,
            'failed'               => 0,
            'duplicate'            => 0,
            'possible_duplicate'   => 0,
            'same_email_diff_ref'  => 0,
            'unknown_deal'         => 0,
            'unknown_organization' => 0,
            'blocked'              => 0,
        ];

        $pendingReferrerInvites = []; // email => ['name' => ?, 'rows' => []]
        $pendingPartnerInvites  = []; // list of [ email, name, deal_id, added_by_referrer_id ]

        foreach ($rawRows as $i => $rawRow) {
            $rowNum     = $i + 2; // row 1 = header; data starts at row 2
            $normalized = $this->normalizeRow($rawRow, $colMap);
            $validation = $this->validateRow(
                normalized:           $normalized,
                tenantId:             $tenantId,
                uploaderRole:         $importedByRole,
                uploaderResellerId:   $importedByResellerId,
                uploaderEmail:        $uploaderEmail,
            );

            $rowAction = match ($validation['status']) {
                'ready', 'do_not_contact', 'existing_user',
                'same_email_other_referrer', 'unknown_org', 'unknown_deal' => 'create',
                'duplicate', 'possible_duplicate', 'needs_review'          => 'review',
                'blocked', 'failed'                                         => 'blocked',
                default                                                     => 'review',
            };

            ImportBatchRow::create([
                'import_batch_id'     => $batch->id,
                'row_number'          => $rowNum,
                'raw_data'            => $rawRow,
                'normalized_data'     => $normalized,
                'computed_data'       => $validation['computed'],
                'validation_status'   => $validation['status'],
                'issue_codes'         => $validation['issue_codes'],
                'row_action'          => $rowAction,
                'existing_contact_id' => $validation['existing_contact_id'],
                'organization_id'     => $validation['organization_id'],
            ]);

            // Tally counts
            match ($validation['status']) {
                'ready', 'do_not_contact', 'existing_user' => $counts['successful']++,
                'failed', 'blocked'                         => $counts['failed']++,
                'duplicate'                                 => $counts['duplicate']++,
                'possible_duplicate'                        => $counts['possible_duplicate']++,
                'same_email_other_referrer'                 => $counts['same_email_diff_ref']++,
                'unknown_deal'                              => $counts['unknown_deal']++,
                'unknown_org'                               => $counts['unknown_organization']++,
                default                                     => null,
            };

            // Stage referrer invite candidates
            if (($normalized['invite_as_referrer'] ?? false) && !empty($normalized['email'])) {
                $email = $normalized['email'];
                if (!isset($pendingReferrerInvites[$email])) {
                    $pendingReferrerInvites[$email] = ['name' => $normalized['full_name'] ?? null, 'rows' => []];
                }
                $pendingReferrerInvites[$email]['rows'][] = $rowNum;
            }

            // Stage partner invite candidates
            if (($normalized['invite_as_partner'] ?? false) && !empty($normalized['email'])) {
                $pendingPartnerInvites[] = [
                    'email'                => $normalized['email'],
                    'name'                 => $normalized['full_name'] ?? null,
                    'deal_id'              => $validation['existing_deal_id'],
                    'added_by_referrer_id' => $importedByResellerId,
                ];
            }
        }

        // Upsert pending referrer invites (for rows flagged invite_as_referrer)
        foreach ($pendingReferrerInvites as $email => $info) {
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

        // Create pending partner invites (for rows flagged invite_as_partner)
        foreach ($pendingPartnerInvites as $pi) {
            DB::table('pending_partner_invites')->insert([
                'id'                   => (string) Str::uuid(),
                'tenant_id'            => $tenantId,
                'import_batch_id'      => $batch->id,
                'email'                => $pi['email'],
                'name'                 => $pi['name'],
                'deal_id'              => $pi['deal_id'],
                'added_by_referrer_id' => $pi['added_by_referrer_id'],
                'status'               => 'pending_invite',
                'created_at'           => now(),
                'updated_at'           => now(),
            ]);
        }

        $batch->update([
            'status'                             => 'previewed',
            'successful_rows'                    => $counts['successful'],
            'failed_rows'                        => $counts['failed'],
            'duplicate_rows'                     => $counts['duplicate'],
            'possible_duplicate_rows'            => $counts['possible_duplicate'],
            'same_email_different_referrer_rows' => $counts['same_email_diff_ref'],
            'unknown_deal_rows'                  => $counts['unknown_deal'],
            'unknown_organization_rows'          => $counts['unknown_organization'],
            'blocked_rows'                       => $counts['blocked'],
        ]);

        // Notify tenant admins — best-effort, must never crash the upload
        try {
            $body = "Your contact import of {$batch->total_rows} rows is ready. "
                . "{$counts['successful']} ready, {$counts['duplicate']} duplicates, "
                . "{$counts['same_email_diff_ref']} emails across multiple Referrers.";

            $this->notifications->dispatchToTenantAdmins(
                tenantId:     $tenantId,
                category:     'import_preview_ready',
                priority:     'normal',
                title:        'Contacts Import Ready for Review',
                body:         $body,
                actionUrl:    "/tenant/{$tenantId}/imports/contacts/{$batch->id}",
                actionLabel:  'Review Import',
                dedupeSuffix: $batch->id,
            );
        } catch (\Throwable) {}

        // Also notify the uploading Referrer (if applicable) — best-effort
        if ($importedByRole === 'reseller' && $importedByResellerId) {
            try {
                $this->notifications->dispatchToReseller(
                    resellerId:   $importedByResellerId,
                    tenantId:     $tenantId,
                    category:     'import_preview_ready',
                    priority:     'normal',
                    title:        'Contact Import Ready',
                    body:         "Your contact import is ready for review. {$counts['successful']} ready, {$counts['duplicate']} duplicates.",
                    actionUrl:    "/reseller/{$tenantId}/contacts/imports/{$batch->id}",
                    actionLabel:  'Review',
                    dedupeSuffix: $batch->id,
                );
            } catch (\Throwable) {}
        }

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
     * Execute a previewed ImportBatch: create / update / skip contacts per row_action.
     *
     * Referrer-owned contacts get visibility_scope = 'referrer_private' by default.
     * Tenant-admin contacts get visibility_scope = 'tenant' by default.
     */
    public function executeImport(
        ImportBatch $batch,
        string      $tenantId,
        string      $executorId,
        string      $executorRole,
        ?string     $executorResellerId = null
    ): array {
        $batch->update(['status' => 'processing', 'started_at' => now()]);

        $rows    = ImportBatchRow::where('import_batch_id', $batch->id)->get();
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $failed  = 0;

        // Default visibility: Referrer contacts are private; admin contacts are tenant-wide
        $defaultVisibility = $executorRole === 'reseller' ? 'referrer_private' : 'tenant';

        foreach ($rows as $row) {
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
            // Unresolved duplicates without an admin decision are skipped
            if (
                in_array($row->validation_status, ['duplicate', 'possible_duplicate'], true)
                && !in_array($row->row_action, ['overwrite', 'merge', 'create'], true)
            ) {
                $skipped++;
                continue;
            }

            $norm = $row->normalized_data;

            try {
                $contactData = [
                    'tenant_id'               => $tenantId,
                    'first_name'              => $norm['first_name'] ?? null,
                    'last_name'               => $norm['last_name'] ?? null,
                    'full_name'               => $norm['full_name'] ?? null,
                    'nickname'                => $norm['nickname'] ?? null,
                    'email'                   => $norm['email'] ?? null,
                    'phone'                   => $norm['phone_number'] ?? null,
                    'alternate_email'         => $norm['alternate_email'] ?? null,
                    'alternate_phone'         => $norm['alternate_phone'] ?? null,
                    'job_title'               => $norm['job_title'] ?? null,
                    'department'              => $norm['department'] ?? null,
                    'company_or_organization' => $norm['company_or_organization'] ?? null,
                    'notes'                   => $norm['notes'] ?? null,
                    'tags'                    => json_encode($norm['tags'] ?? []),
                    'contact_type'            => $norm['contact_type'] ?? 'general_contact',
                    'intended_role'           => $norm['intended_role'] ?? null,
                    'visibility_scope'        => $norm['visibility_scope'] ?? $defaultVisibility,
                    'source'                  => $norm['source'] ?? 'import',
                    'internal_reference_id'   => $norm['internal_reference_id'] ?? null,
                    'status'                  => 'active',
                    'organization_id'         => $row->organization_id,
                    'owner_user_id'           => $executorRole !== 'reseller' ? $executorId : null,
                    'owner_role'              => $executorRole,
                    'owner_referrer_id'       => $executorResellerId,
                    'imported_from_batch_id'  => $batch->id,
                    'created_by_user_id'      => $executorId,
                    'do_not_contact'          => $norm['do_not_contact'] ?? false,
                    'consent_status'          => $norm['consent_status'] ?? null,
                    'data'                    => [],
                ];

                if ($row->row_action === 'overwrite' && $row->existing_contact_id) {
                    $beforeContact = DB::table('contacts')->where('id', $row->existing_contact_id)->first();
                    DB::table('contacts')->where('id', $row->existing_contact_id)
                        ->update(array_merge($contactData, ['updated_at' => now(), 'updated_by_user_id' => $executorId]));
                    $this->snapshots->recordUpdated(
                        batchId: $batch->id, tenantId: $tenantId,
                        entityType: 'contact', entityId: $row->existing_contact_id,
                        beforeData: $beforeContact ? (array) $beforeContact : [], afterData: $contactData,
                        changedFields: array_keys($contactData), operationType: 'overwritten',
                        batchRowId: $row->id, row: $row,
                    );
                    $row->update(['created_contact_id' => $row->existing_contact_id]);
                    $updated++;
                } elseif ($row->row_action === 'merge' && $row->existing_contact_id) {
                    $existing = DB::table('contacts')->where('id', $row->existing_contact_id)->first();
                    if ($existing) {
                        $mergeData = array_filter($contactData, fn ($v) => $v !== null);
                        unset($mergeData['tenant_id'], $mergeData['owner_user_id'], $mergeData['owner_referrer_id']);
                        DB::table('contacts')->where('id', $row->existing_contact_id)
                            ->update(array_merge($mergeData, ['updated_at' => now()]));
                        $this->snapshots->recordUpdated(
                            batchId: $batch->id, tenantId: $tenantId,
                            entityType: 'contact', entityId: $row->existing_contact_id,
                            beforeData: (array) $existing, afterData: $mergeData,
                            changedFields: array_keys($mergeData), operationType: 'merged',
                            batchRowId: $row->id, row: $row,
                        );
                    }
                    $row->update(['created_contact_id' => $row->existing_contact_id]);
                    $updated++;
                } else {
                    // Create new contact
                    $contactId = (string) Str::uuid();
                    DB::table('contacts')->insert(array_merge($contactData, [
                        'id'         => $contactId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]));
                    $this->snapshots->recordCreated(
                        batchId: $batch->id, tenantId: $tenantId,
                        entityType: 'contact', entityId: $contactId,
                        entityData: array_merge($contactData, ['id' => $contactId]),
                        batchRowId: $row->id, row: $row,
                    );

                    // Associate to deal if a deal was resolved during validation
                    $dealId = $row->existing_deal_id ?? null;
                    if ($dealId) {
                        DB::table('deal_contacts')->insertOrIgnore([
                            'id'         => (string) Str::uuid(),
                            'tenant_id'  => $tenantId,
                            'deal_id'    => $dealId,
                            'contact_id' => $contactId,
                            'role'       => $norm['intended_role'] ?? 'contact',
                            'created_at' => now(),
                        ]);
                    }

                    $row->update(['created_contact_id' => $contactId]);
                    $created++;
                }
            } catch (\Throwable $e) {
                $row->update(['error_message' => $e->getMessage()]);
                $failed++;
            }
        }

        $status = match (true) {
            $failed > 0 && $created === 0 && $updated === 0 => 'failed',
            $failed > 0 || $skipped > 0                     => 'completed_with_warnings',
            default                                          => 'completed',
        };

        $batch->update([
            'status'          => $status,
            'successful_rows' => $created,
            'updated_rows'    => $updated,
            'skipped_rows'    => $skipped,
            'failed_rows'     => $failed,
            'completed_at'    => now(),
            'summary_json'    => compact('created', 'updated', 'skipped', 'failed'),
        ]);

        $this->snapshots->markBatchEligible($batch->id);

        try {
            $this->notifications->dispatchToTenantAdmins(
                tenantId:     $tenantId,
                category:     'import_completed',
                priority:     $failed > 0 ? 'high' : 'normal',
                title:        $status === 'completed' ? 'Contacts Import Complete' : 'Contacts Import Complete with Warnings',
                body:         "Created: $created, Updated: $updated, Skipped: $skipped, Failed: $failed.",
                actionUrl:    "/tenant/{$tenantId}/imports/contacts/{$batch->id}/report",
                actionLabel:  'View Report',
                dedupeSuffix: $batch->id,
            );
        } catch (\Throwable) {}

        if ($executorRole === 'reseller' && $executorResellerId) {
            try {
                $this->notifications->dispatchToReseller(
                    resellerId:   $executorResellerId,
                    tenantId:     $tenantId,
                    category:     'import_completed',
                    priority:     'normal',
                    title:        'Contact Import Complete',
                    body:         "Created: $created, Updated: $updated, Skipped: $skipped.",
                    actionUrl:    "/reseller/{$tenantId}/contacts/imports/{$batch->id}/report",
                    actionLabel:  'View Report',
                    dedupeSuffix: $batch->id,
                );
            } catch (\Throwable) {}
        }

        return compact('created', 'updated', 'skipped', 'failed');
    }

    // ── Template CSV download ─────────────────────────────────────

    /**
     * Generate a downloadable CSV template with a sample row and field notes.
     */
    public function generateTemplateCsv(): string
    {
        $headers = [
            'First Name', 'Last Name', 'Full Name', 'Nickname',
            'Email', 'Phone Number', 'Alternate Email', 'Alternate Phone',
            'Job Title', 'Department', 'Company / Organization',
            'Notes', 'Tags',
            'Contact Type', 'Intended Role', 'Visibility Scope', 'Source',
            'Deal Name', 'Deal Reference', 'Organization Identifier',
            'Invite as Referrer', 'Invite as Partner',
            'Consent Status', 'Do Not Contact',
        ];
        $sampleRow = [
            'Maria', 'Santos', 'Maria Santos', 'Mars',
            'maria@example.com', '+63 9XX XXX XXXX', '', '',
            'IT Director', 'IT Department', 'Acme Corp',
            'Met at conference 2025', 'government,ict',
            'general_contact', 'none', 'tenant', 'referral',
            '', '', '',
            'false', 'false',
            'consented', 'false',
        ];

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
            ->whereIn('validation_status', ['failed', 'blocked'])
            ->get();

        $lines   = [];
        $lines[] = implode(',', array_map(
            fn ($h) => '"' . $h . '"',
            ['Row #', 'Name', 'Email', 'Phone', 'Contact Type', 'Status', 'Issues']
        ));

        foreach ($rows as $row) {
            $n      = $row->normalized_data;
            $issues = collect($row->issue_codes)->pluck('message')->filter()->implode('; ');
            $lines[] = implode(',', array_map(
                fn ($v) => '"' . str_replace('"', '""', (string) $v) . '"',
                [
                    $row->row_number,
                    $n['full_name'] ?? trim(($n['first_name'] ?? '') . ' ' . ($n['last_name'] ?? '')),
                    $n['email'] ?? '',
                    $n['phone_number'] ?? '',
                    $n['contact_type'] ?? '',
                    $row->validation_status,
                    $issues,
                ]
            ));
        }

        return implode("\n", $lines);
    }
}
