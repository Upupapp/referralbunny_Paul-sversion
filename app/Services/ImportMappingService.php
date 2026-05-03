<?php

namespace App\Services;

use App\Models\ImportMappingProfile;

class ImportMappingService
{
    // ── Header aliases ────────────────────────────────────────

    private const ALIASES = [
        'tenant_name'          => ['company_name','organization_name','business_name','client_name','name','org_name','account_name'],
        'primary_admin_email'  => ['admin_email','owner_email','contact_email','primary_email','email','admin_mail'],
        'industry'             => ['sector','business_category','category','business_type','vertical','industry_type'],
        'tenant_identifier'    => ['tenant_id','tenant_slug','tenant','company_id','org_id','client_id'],
        'reseller_name'        => ['referrer_name','agent_name','partner_name','name','full_name','contact_name'],
        'reseller_email'       => ['email','agent_email','partner_email','referrer_email','contact_email'],
        'lead_name'            => ['prospect_name','opportunity_name','contact_name','name','full_name','client_name'],
        'lead_status'          => ['status','stage','lead_stage','pipeline_stage','current_status'],
        'lead_email'           => ['email','prospect_email','contact_email','client_email'],
        'lead_phone'           => ['phone','mobile','contact_phone','prospect_phone','telephone'],
        'deal_value'           => ['value','amount','deal_amount','opportunity_value','contract_value','price'],
        'commission_profile'   => ['commission','commission_type','commission_plan','comm_profile'],
        'field_label'          => ['label','name','field_name','display_name'],
        'field_key'            => ['key','slug','identifier','field_identifier'],
        'field_type'           => ['type','data_type','input_type'],
        'tag_name'             => ['tag','label','name'],
        'applies_to_object'    => ['object','entity','applies_to','object_type'],
        'external_reseller_id' => ['reseller_id','external_id','ext_id','legacy_id'],
        'lead_external_id'     => ['lead_id','external_id','ext_id','legacy_id'],
        'referral_code'        => ['ref_code','referral','code','promo_code'],
        'plan_name'            => ['plan','subscription_plan','billing_plan'],
        'preferred_currency'   => ['currency','billing_currency','account_currency'],
        'tenant_status'        => ['status','account_status','company_status'],
    ];

    // ── Public API ────────────────────────────────────────────

    public function detectMapping(array $fileHeaders, string $objectType): array
    {
        $systemFields = (new ImportTemplateService)->getAllColumns($objectType);
        $mapping      = [];

        foreach ($fileHeaders as $fileHeader) {
            $normalised = $this->normalise($fileHeader);
            $result     = $this->matchField($normalised, $systemFields);
            $mapping[]  = [
                'file_column'   => $fileHeader,
                'system_field'  => $result['field'],
                'confidence'    => $result['confidence'],
                'match_type'    => $result['match_type'],
                'example_value' => null,
                'skip'          => false,
            ];
        }

        return $mapping;
    }

    public function applyMapping(array $row, array $mapping): array
    {
        $mapped = [];
        foreach ($mapping as $m) {
            if ($m['skip'] || !$m['system_field']) continue;
            $mapped[$m['system_field']] = $row[$m['file_column']] ?? null;
        }
        return $mapped;
    }

    public function buildHeaderFingerprint(array $headers): string
    {
        $normalised = array_map([$this, 'normalise'], $headers);
        sort($normalised);
        return md5(implode('|', $normalised));
    }

    public function findSavedProfile(int $userId, string $objectType, string $fingerprint): ?ImportMappingProfile
    {
        return ImportMappingProfile::where('user_id', $userId)
            ->where('object_type', $objectType)
            ->where('header_fingerprint', $fingerprint)
            ->latest()
            ->first();
    }

    public function saveProfile(int $userId, string $objectType, array $mapping, string $profileName, string $fingerprint, ?string $tenantId = null): ImportMappingProfile
    {
        return ImportMappingProfile::create([
            'user_id'            => $userId,
            'tenant_id'          => $tenantId,
            'object_type'        => $objectType,
            'profile_name'       => $profileName,
            'mapping_json'       => $mapping,
            'header_fingerprint' => $fingerprint,
        ]);
    }

    public function validateMappingCompleteness(array $mapping, string $objectType): array
    {
        $required = (new ImportTemplateService)->getRequiredColumns($objectType);
        $mapped   = collect($mapping)->where('skip', false)->pluck('system_field')->filter()->all();
        $missing  = array_diff($required, $mapped);

        return [
            'complete' => empty($missing),
            'missing'  => array_values($missing),
        ];
    }

    // ── Private helpers ───────────────────────────────────────

    private function matchField(string $normalised, array $systemFields): array
    {
        // 1. Exact match
        if (in_array($normalised, $systemFields)) {
            return ['field' => $normalised, 'confidence' => 'high', 'match_type' => 'exact'];
        }

        // 2. Alias match
        foreach (self::ALIASES as $systemField => $aliases) {
            if (in_array($normalised, $aliases) && in_array($systemField, $systemFields)) {
                return ['field' => $systemField, 'confidence' => 'high', 'match_type' => 'alias'];
            }
        }

        // 3. Fuzzy match (levenshtein)
        $best       = null;
        $bestScore  = PHP_INT_MAX;

        foreach ($systemFields as $sf) {
            $dist = levenshtein($normalised, $sf);
            if ($dist < $bestScore && $dist <= 3) {
                $bestScore = $dist;
                $best      = $sf;
            }
        }

        if ($best) {
            $confidence = $bestScore <= 1 ? 'high' : ($bestScore <= 2 ? 'medium' : 'low');
            return ['field' => $best, 'confidence' => $confidence, 'match_type' => 'fuzzy'];
        }

        // 4. Partial / substring match
        foreach ($systemFields as $sf) {
            if (str_contains($sf, $normalised) || str_contains($normalised, $sf)) {
                return ['field' => $sf, 'confidence' => 'medium', 'match_type' => 'partial'];
            }
        }

        return ['field' => null, 'confidence' => 'none', 'match_type' => 'unmatched'];
    }

    private function normalise(string $header): string
    {
        return strtolower(trim(preg_replace('/[\s\-]+/', '_', preg_replace('/[^a-zA-Z0-9\s\-_]/', '', $header))));
    }
}
