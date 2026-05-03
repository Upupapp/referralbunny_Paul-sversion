<?php

namespace App\Services;

use Illuminate\Http\Response;

class ImportTemplateService
{
    // ── Schema definitions per object type ────────────────────

    public const SCHEMAS = [
        'tenant' => [
            'label'    => 'Tenants',
            'required' => ['tenant_name', 'industry', 'primary_admin_email'],
            'optional' => [
                'tenant_slug', 'legal_name', 'website', 'primary_admin_first_name',
                'primary_admin_last_name', 'primary_admin_phone', 'billing_email',
                'phone_number', 'country', 'province_state', 'city', 'address',
                'preferred_currency', 'plan_name', 'subscription_status', 'trial_days',
                'tenant_status', 'tags', 'notes', 'source',
                'custom_field_1', 'custom_field_2', 'custom_field_3',
            ],
            'accepted_values' => [
                'tenant_status'       => ['active', 'trial', 'inactive'],
                'subscription_status' => ['trial', 'active', 'inactive'],
                'preferred_currency'  => ['PHP', 'USD', 'SGD', 'EUR'],
            ],
            'sample' => [
                'tenant_name'         => 'Sunrise Realty Group',
                'industry'            => 'Real Estate',
                'primary_admin_email' => 'admin@sunriserealty.com',
                'tenant_status'       => 'trial',
                'preferred_currency'  => 'PHP',
            ],
        ],
        'reseller' => [
            'label'    => 'Resellers',
            'required' => ['tenant_identifier', 'reseller_name', 'reseller_email'],
            'optional' => [
                'reseller_phone', 'reseller_type', 'reseller_status', 'referral_code',
                'commission_profile', 'assigned_region', 'tags', 'notes',
                'invite_reseller', 'external_reseller_id',
            ],
            'accepted_values' => [
                'reseller_status' => ['invited', 'active', 'nda_signed', 'inactive'],
                'invite_reseller' => ['yes', 'no'],
            ],
            'sample' => [
                'tenant_identifier' => 'sunrise-realty',
                'reseller_name'     => 'Maria Santos',
                'reseller_email'    => 'maria@email.com',
                'reseller_status'   => 'active',
            ],
        ],
        'lead' => [
            'label'    => 'Leads',
            'required' => ['tenant_identifier', 'lead_name', 'lead_status'],
            'optional' => [
                'lead_email', 'lead_phone', 'lead_external_id', 'company_name',
                'contact_person', 'source', 'referred_by_reseller_email',
                'deal_value', 'currency', 'expected_close_date', 'notes', 'tags',
                'custom_field_1', 'custom_field_2', 'custom_field_3',
            ],
            'accepted_values' => [
                'lead_status' => ['active', 'expiring', 'expired', 'declined', 'reassigned'],
                'currency'    => ['PHP', 'USD', 'SGD', 'EUR'],
            ],
            'sample' => [
                'tenant_identifier' => 'sunrise-realty',
                'lead_name'         => 'Juan Dela Cruz',
                'lead_status'       => 'active',
                'deal_value'        => '2500000',
                'currency'          => 'PHP',
            ],
        ],
        'custom_field' => [
            'label'    => 'Custom Fields',
            'required' => ['tenant_identifier', 'field_label', 'field_key', 'field_type'],
            'optional' => [
                'required', 'options', 'default_value', 'visible_to_admin',
                'visible_to_reseller', 'display_order', 'applies_to_object', 'help_text',
            ],
            'accepted_values' => [
                'field_type'       => ['text', 'number', 'date', 'dropdown', 'multi_select', 'email', 'phone', 'currency', 'boolean', 'long_text'],
                'applies_to_object'=> ['lead', 'reseller', 'tenant'],
                'required'         => ['yes', 'no'],
                'visible_to_admin' => ['yes', 'no'],
            ],
            'sample' => [
                'tenant_identifier' => 'sunrise-realty',
                'field_label'       => 'Property Type',
                'field_key'         => 'property_type',
                'field_type'        => 'dropdown',
                'options'           => 'Residential,Commercial,Industrial',
                'applies_to_object' => 'lead',
            ],
        ],
        'tag' => [
            'label'    => 'Tags',
            'required' => ['tenant_identifier', 'tag_name', 'applies_to_object'],
            'optional' => ['color', 'description', 'status'],
            'accepted_values' => [
                'applies_to_object' => ['lead', 'reseller', 'tenant'],
                'status'            => ['active', 'inactive'],
            ],
            'sample' => [
                'tenant_identifier' => 'sunrise-realty',
                'tag_name'          => 'Hot Lead',
                'applies_to_object' => 'lead',
                'color'             => '#EF4444',
            ],
        ],
    ];

    // ── Public methods ────────────────────────────────────────

    public function getSchema(string $objectType): array
    {
        return self::SCHEMAS[$objectType] ?? [];
    }

    public function getSupportedTypes(): array
    {
        return array_map(fn($k, $v) => ['type' => $k, 'label' => $v['label']], array_keys(self::SCHEMAS), self::SCHEMAS);
    }

    public function generateCsvContent(string $objectType, bool $withSample = false): string
    {
        $schema = self::SCHEMAS[$objectType] ?? null;
        if (!$schema) return '';

        $headers = array_merge($schema['required'], $schema['optional']);

        $lines = [];
        $lines[] = implode(',', array_map(fn($h) => '"' . $h . '"', $headers));

        if ($withSample && !empty($schema['sample'])) {
            $row = array_map(fn($h) => '"' . ($schema['sample'][$h] ?? '') . '"', $headers);
            $lines[] = implode(',', $row);
        }

        return implode("\n", $lines);
    }

    public function csvDownloadResponse(string $objectType, bool $withSample = false): \Symfony\Component\HttpFoundation\Response
    {
        $content  = $this->generateCsvContent($objectType, $withSample);
        $filename = 'referral_bunny_' . $objectType . '_import' . ($withSample ? '_sample' : '') . '_v1.0.csv';

        return response($content, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    public function getRequiredColumns(string $objectType): array
    {
        return self::SCHEMAS[$objectType]['required'] ?? [];
    }

    public function getOptionalColumns(string $objectType): array
    {
        return self::SCHEMAS[$objectType]['optional'] ?? [];
    }

    public function getAcceptedValues(string $objectType): array
    {
        return self::SCHEMAS[$objectType]['accepted_values'] ?? [];
    }

    public function getAllColumns(string $objectType): array
    {
        $schema = self::SCHEMAS[$objectType] ?? [];
        return array_merge($schema['required'] ?? [], $schema['optional'] ?? []);
    }
}
