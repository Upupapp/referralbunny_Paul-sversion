<?php

namespace App\Services;

use App\Models\TenantCustomField;
use App\Models\TenantImportTemplate;

class ColumnDetectionService
{
    /**
     * Compare uploaded headers against known fields and return unmapped columns.
     * Returns array of:
     * [
     *   'header'      => 'Raw Header Name',
     *   'snake_key'   => 'snake_case_key',
     *   'suggestion'  => null or 'existing_field_key',
     *   'data_type'   => 'text' (detected suggestion),
     *   'sample_vals' => [...],
     *   'status'      => 'unmapped'|'alias_match'|'fuzzy_match',
     * ]
     */
    public function detectUnmapped(
        array  $uploadedHeaders,
        array  $knownAliases,     // lower-cased alias → canonical_field
        array  $knownFields,      // array of canonical field names
        string $tenantId,
        string $destinationType
    ): array {
        $customFields = TenantCustomField::where('tenant_id', $tenantId)
            ->where('destination_type', $destinationType)
            ->pluck('field_key')
            ->toArray();

        $allKnown = array_merge(
            $knownFields,
            array_values($knownAliases),
            $customFields
        );
        $allKnown = array_unique($allKnown);

        $unmapped = [];
        foreach ($uploadedHeaders as $header) {
            $key = strtolower(trim($header));
            // Check exact alias match
            if (isset($knownAliases[$key])) {
                continue;
            }
            // Check if header IS already a canonical field
            if (in_array($key, array_map('strtolower', $allKnown))) {
                continue;
            }
            // Check custom fields via snake_case conversion
            $snakeKey = $this->toSnakeCase($key);
            if (in_array($snakeKey, $allKnown)) {
                continue;
            }

            // Fuzzy: check for partial matches
            $suggestion = $this->findFuzzyMatch($key, $allKnown);

            $unmapped[] = [
                'header'      => $header,
                'snake_key'   => $snakeKey,
                'suggestion'  => $suggestion,
                'data_type'   => 'text', // default; will be refined by detectDataType()
                'status'      => $suggestion ? 'fuzzy_match' : 'unmapped',
                'sample_vals' => [],
            ];
        }

        return $unmapped;
    }

    /**
     * Detect likely data type from sample values.
     */
    public function detectDataType(array $sampleValues): string
    {
        $sampleValues = array_filter($sampleValues, fn ($v) => $v !== null && $v !== '');
        if (empty($sampleValues)) {
            return 'text';
        }

        $boolWords = ['true', 'false', 'yes', 'no', '1', '0', 'y', 'n'];
        $allBool   = true;
        $allNumber = true;
        $allDate   = true;
        $allEmail  = true;
        $allUrl    = true;
        $allPhone  = true;

        foreach (array_slice($sampleValues, 0, 20) as $val) {
            $v = strtolower(trim((string) $val));
            if (!in_array($v, $boolWords))                        $allBool   = false;
            if (!is_numeric(preg_replace('/[₱,\s%]/', '', $v)))   $allNumber = false;
            if (!strtotime($v))                                    $allDate   = false;
            if (!filter_var($val, FILTER_VALIDATE_EMAIL))          $allEmail  = false;
            if (!filter_var($val, FILTER_VALIDATE_URL))            $allUrl    = false;
            if (!preg_match('/^[\+\d\s\(\)\-\.]{7,20}$/', $val))  $allPhone  = false;
        }

        if ($allBool)   return 'boolean';
        if ($allEmail)  return 'email';
        if ($allUrl)    return 'url';
        if ($allPhone)  return 'phone';
        if ($allDate)   return 'date';
        if ($allNumber) {
            $raw = implode(' ', $sampleValues);
            if (str_contains($raw, '%'))  return 'percentage';
            if (str_contains($raw, '₱')) return 'currency';
            return 'number';
        }

        // Long text check
        $avgLen = array_sum(array_map('strlen', $sampleValues)) / count($sampleValues);
        if ($avgLen > 100) return 'long_text';

        return 'text';
    }

    /**
     * Generate a tenant-safe snake_case field key, checking for conflicts.
     */
    public function generateFieldKey(
        string $label,
        string $tenantId,
        string $destinationType
    ): string {
        $key = $this->toSnakeCase($label);

        // Check for conflicts
        $exists = TenantCustomField::where('tenant_id', $tenantId)
            ->where('destination_type', $destinationType)
            ->where('field_key', $key)
            ->exists();

        if (!$exists) {
            return $key;
        }

        // Try appending a number to resolve conflicts
        for ($i = 2; $i <= 10; $i++) {
            $candidate = $key . '_' . $i;
            if (!TenantCustomField::where('tenant_id', $tenantId)
                ->where('destination_type', $destinationType)
                ->where('field_key', $candidate)
                ->exists()) {
                return $candidate;
            }
        }

        return $key . '_' . time();
    }

    // ── Private helpers ───────────────────────────────────────────

    private function toSnakeCase(string $label): string
    {
        $s = strtolower($label);
        $s = preg_replace('/[^a-z0-9\s_]/', '', $s);
        $s = preg_replace('/[\s\-]+/', '_', trim($s));
        $s = preg_replace('/_{2,}/', '_', $s);
        return trim($s, '_') ?: 'custom_field';
    }

    private function findFuzzyMatch(string $key, array $knownFields): ?string
    {
        foreach ($knownFields as $field) {
            $fieldLow = strtolower($field);
            // Contains check
            if (str_contains($fieldLow, $key) || str_contains($key, $fieldLow)) {
                return $field;
            }
            // Levenshtein distance ≤ 2 for non-trivial strings
            if (strlen($key) > 3 && levenshtein($key, $fieldLow) <= 2) {
                return $field;
            }
        }
        return null;
    }
}
