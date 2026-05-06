<?php

namespace App\Services;

use App\Models\ImportBatch;
use App\Models\TenantImportTemplate;
use App\Models\TenantUser;
use Illuminate\Support\Facades\Hash;

class TemplateAdoptionService
{
    const LGU_IDS_TENANT = 'lgu-ids';

    /**
     * Block LGU IDS from template adoption — their template is permanently locked.
     */
    public function isLocked(string $tenantId): bool
    {
        return $tenantId === self::LGU_IDS_TENANT;
    }

    /**
     * Verify the user's password for double authentication.
     * Returns true on success, false on failure.
     */
    public function verifyDoubleAuth(TenantUser $user, string $password): bool
    {
        return Hash::check($password, $user->password);
    }

    /**
     * Create a new tenant import template version from an ImportBatch.
     * Archives the previous default template for the same tenant + destType.
     *
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException  if called for lgu-ids
     */
    public function createVersion(
        string      $tenantId,
        string      $destType,
        ImportBatch $batch,
        array       $fields,
        array       $requiredFields,
        array       $aliases,
        array       $sampleHeaders,
        string      $templateName,
        TenantUser  $createdBy,
        TenantUser  $approvedBy
    ): TenantImportTemplate {
        abort_if(
            $this->isLocked($tenantId),
            403,
            'LGU IDS uses a locked import template that cannot be modified.'
        );

        // Archive previous default for this tenant + destType
        TenantImportTemplate::archivePreviousDefault($tenantId, $destType);

        // Determine next version number
        $lastVersion = TenantImportTemplate::where('tenant_id', $tenantId)
            ->where('destination_type', $destType)
            ->max('version_number') ?? 0;

        return TenantImportTemplate::create([
            'tenant_id'                    => $tenantId,
            'destination_type'             => $destType,
            'template_name'                => $templateName,
            'template_key'                 => 'tenant_' . $tenantId . '_' . $destType . '_v' . ($lastVersion + 1),
            'is_default'                   => true,
            'is_locked'                    => false,
            'fields_json'                  => $fields,
            'required_fields_json'         => $requiredFields,
            'aliases_json'                 => $aliases,
            'sample_headers_json'          => $sampleHeaders,
            'created_from_import_batch_id' => $batch->id,
            'created_by_user_id'           => $createdBy->id,
            'approved_by_user_id'          => $approvedBy->id,
            'double_authenticated_at'      => now(),
            'version_number'               => $lastVersion + 1,
            'status'                       => 'active',
        ]);
    }

    /**
     * Get the active default template for a tenant + destType combo.
     * Returns null if none — caller should fall back to config.
     */
    public function getDefault(string $tenantId, string $destType): ?TenantImportTemplate
    {
        return TenantImportTemplate::where('tenant_id', $tenantId)
            ->where('destination_type', $destType)
            ->where('is_default', true)
            ->where('status', 'active')
            ->first();
    }

    /**
     * Check if the acting user has permission to adopt (save) templates.
     * Owners and admins always can; managers need an explicit permission flag.
     */
    public function canAdopt(string $role, array $permissions = []): bool
    {
        if (in_array($role, ['owner', 'admin'])) {
            return true;
        }
        if ($role === 'manager') {
            return !empty($permissions['manage_import_templates']);
        }
        return false;
    }
}
