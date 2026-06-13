<?php

namespace App\Support;

/**
 * Central list of tenants whose pricing, commission, pipeline-stage, and
 * import logic is locked and must never be modified by generic tenant-facing
 * setup tools (e.g. the Referral Program Setup Wizard).
 */
class ProtectedTenants
{
    public const PROTECTED = ['lgu-ids'];

    public static function isProtected(string $tenantId): bool
    {
        return in_array($tenantId, self::PROTECTED, true);
    }

    /**
     * Wizard config sections that must be ignored on publish for protected tenants.
     */
    public static function lockedConfigSteps(): array
    {
        return ['pipeline', 'rewards', 'import'];
    }
}
