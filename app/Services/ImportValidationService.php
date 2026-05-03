<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\Reseller;
use App\Models\Tenant;

class ImportValidationService
{
    public function __construct(private ImportTemplateService $templates) {}

    public function validateRow(array $mappedRow, string $objectType, int $rowNumber): array
    {
        $errors   = [];
        $warnings = [];

        $required = $this->templates->getRequiredColumns($objectType);
        $accepted = $this->templates->getAcceptedValues($objectType);

        // Required field check
        foreach ($required as $field) {
            $val = $mappedRow[$field] ?? null;
            if ($val === null || $val === '') {
                $errors[] = $this->err($rowNumber, $field, 'missing_required_value', "Field '{$field}' is required.", "Provide a value for '{$field}'.");
            }
        }

        // Type-specific validation
        foreach ($mappedRow as $field => $value) {
            if ($value === null || $value === '') continue;

            // Email fields
            if (str_contains($field, 'email') && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $errors[] = $this->err($rowNumber, $field, 'invalid_email', "'{$value}' is not a valid email address.", 'Correct the email format (e.g. user@example.com).');
            }

            // Numeric fields
            if (in_array($field, ['deal_value', 'trial_days', 'display_order', 'percentage_rate', 'fixed_amount']) && !is_numeric($value)) {
                $errors[] = $this->err($rowNumber, $field, 'invalid_number', "'{$value}' must be a numeric value.", 'Remove any currency symbols or text from numeric fields.');
            }

            // Date fields
            if (str_contains($field, '_date') || str_contains($field, '_at')) {
                if (!$this->isValidDate($value)) {
                    $warnings[] = $this->err($rowNumber, $field, 'invalid_date', "'{$value}' may not be a valid date.", 'Use format YYYY-MM-DD (e.g. 2026-01-15).', 'warning');
                }
            }

            // Accepted values
            if (isset($accepted[$field]) && !in_array(strtolower($value), array_map('strtolower', $accepted[$field]))) {
                $acceptedStr = implode(', ', $accepted[$field]);
                $errors[] = $this->err($rowNumber, $field, 'invalid_enum', "'{$value}' is not an accepted value for '{$field}'.", "Accepted values: {$acceptedStr}.");
            }
        }

        // Object-specific cross-field validation
        match ($objectType) {
            'lead'       => $this->validateLeadRow($mappedRow, $rowNumber, $errors, $warnings),
            'reseller'   => $this->validateResellerRow($mappedRow, $rowNumber, $errors, $warnings),
            'custom_field'=> $this->validateCustomFieldRow($mappedRow, $rowNumber, $errors, $warnings),
            default      => null,
        };

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    public function checkDuplicateInDb(array $mappedRow, string $objectType): ?array
    {
        return match ($objectType) {
            'tenant'   => $this->findExistingTenant($mappedRow),
            'reseller' => $this->findExistingReseller($mappedRow),
            'lead'     => $this->findExistingLead($mappedRow),
            default    => null,
        };
    }

    public function checkDuplicateInFile(array $rows, int $currentIndex, string $objectType): bool
    {
        $current = $rows[$currentIndex] ?? [];
        $key     = $this->getUniqueKey($current, $objectType);
        if (!$key) return false;

        foreach ($rows as $i => $row) {
            if ($i === $currentIndex) continue;
            if ($this->getUniqueKey($row, $objectType) === $key) return true;
        }

        return false;
    }

    public function calculateRiskLevel(array $summary): string
    {
        $overwrites     = $summary['records_to_update']    ?? 0;
        $totalRows      = $summary['total_rows']           ?? 0;
        $hasBillingFields = $summary['has_billing_fields'] ?? false;
        $hasSensitiveFields = $summary['has_sensitive_fields'] ?? false;

        if ($hasBillingFields || $overwrites > 2000 || $hasSensitiveFields) return 'critical';
        if ($overwrites > 500 || $totalRows > 5000) return 'high';
        if ($overwrites > 50 || $totalRows > 500)  return 'medium';
        return 'low';
    }

    public function requiresApproval(string $riskLevel): bool
    {
        return in_array($riskLevel, ['high', 'critical']);
    }

    public function calculateDataQualityScore(array $validationResult): int
    {
        $totalRows   = $validationResult['total_rows']   ?? 1;
        $errorRows   = $validationResult['error_rows']   ?? 0;
        $warningRows = $validationResult['warning_rows'] ?? 0;
        $duplicates  = $validationResult['duplicates']   ?? 0;

        $errorPenalty   = ($errorRows   / $totalRows) * 50;
        $warningPenalty = ($warningRows / $totalRows) * 20;
        $dupPenalty     = min(($duplicates / $totalRows) * 30, 30);

        return max(0, (int) round(100 - $errorPenalty - $warningPenalty - $dupPenalty));
    }

    // ── Private helpers ───────────────────────────────────────

    private function validateLeadRow(array $row, int $rowNumber, array &$errors, array &$warnings): void
    {
        // At least one contact identifier
        $hasContact = !empty($row['lead_email']) || !empty($row['lead_phone']) || !empty($row['lead_external_id']);
        if (!$hasContact) {
            $warnings[] = $this->err($rowNumber, 'lead_email', 'missing_contact_identifier',
                'No contact identifier found (lead_email, lead_phone, or lead_external_id).',
                'Add at least one contact identifier to enable duplicate detection.', 'warning');
        }
    }

    private function validateResellerRow(array $row, int $rowNumber, array &$errors, array &$warnings): void
    {
        if (empty($row['reseller_email']) && empty($row['reseller_phone'])) {
            $warnings[] = $this->err($rowNumber, 'reseller_email', 'missing_contact_identifier',
                'Neither reseller_email nor reseller_phone was provided.',
                'Add email or phone for duplicate detection and invitation.', 'warning');
        }
    }

    private function validateCustomFieldRow(array $row, int $rowNumber, array &$errors, array &$warnings): void
    {
        if (isset($row['field_type']) && in_array($row['field_type'], ['dropdown', 'multi_select'])) {
            if (empty($row['options'])) {
                $errors[] = $this->err($rowNumber, 'options',
                    'missing_required_value', "Options are required for field_type '{$row['field_type']}'.",
                    'Provide comma-separated options (e.g. Option A,Option B,Option C).');
            }
        }
    }

    private function findExistingTenant(array $row): ?array
    {
        $tenantId = $row['tenant_id'] ?? $row['external_tenant_id'] ?? null;
        $email    = $row['primary_admin_email'] ?? null;
        $name     = $row['tenant_name'] ?? null;

        $query = Tenant::query();
        if ($email) $query->where('admin_email', $email);
        elseif ($name) $query->where('name', $name);
        else return null;

        $existing = $query->first();
        return $existing ? $existing->toArray() : null;
    }

    private function findExistingReseller(array $row): ?array
    {
        $email = $row['reseller_email'] ?? null;
        if (!$email) return null;

        $existing = Reseller::where('email', $email)->first();
        return $existing ? $existing->toArray() : null;
    }

    private function findExistingLead(array $row): ?array
    {
        $email = $row['lead_email'] ?? null;
        if (!$email) return null;

        $existing = Lead::where('data->email', $email)->first();
        return $existing ? $existing->toArray() : null;
    }

    private function getUniqueKey(array $row, string $objectType): ?string
    {
        return match ($objectType) {
            'tenant'   => $row['primary_admin_email'] ?? $row['tenant_name'] ?? null,
            'reseller' => $row['reseller_email'] ?? null,
            'lead'     => $row['lead_email'] ?? $row['lead_phone'] ?? null,
            default    => null,
        };
    }

    private function isValidDate(string $value): bool
    {
        foreach (['Y-m-d', 'm/d/Y', 'd/m/Y', 'Y-m-d H:i:s'] as $format) {
            $d = \DateTime::createFromFormat($format, $value);
            if ($d && $d->format($format) === $value) return true;
        }
        return false;
    }

    private function err(int $row, string $col, string $type, string $msg, string $fix = '', string $severity = 'blocking'): array
    {
        return compact('row', 'col', 'type', 'msg', 'fix', 'severity');
    }
}
