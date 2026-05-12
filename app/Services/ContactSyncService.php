<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\Partner;
use App\Models\Reseller;

/**
 * Keeps tenant Contacts in sync with Referrer and Partner accounts.
 *
 * Each synced contact is keyed by [tenant_id + source + internal_reference_id]
 * so updateOrCreate is idempotent — safe to run multiple times.
 */
class ContactSyncService
{
    /**
     * Upsert a Referrer (Reseller) into the tenant contacts table.
     * Returns null if the reseller has no email or is anonymous.
     */
    public function syncReseller(Reseller $reseller): ?Contact
    {
        if (empty(trim($reseller->email ?? '')) || $reseller->is_anonymous) {
            return null;
        }

        return Contact::updateOrCreate(
            [
                'tenant_id'             => $reseller->tenant_id,
                'source'                => 'referrer_account',
                'internal_reference_id' => $reseller->id,
            ],
            [
                'full_name'              => $reseller->name ?? '',
                'nickname'               => $reseller->nickname,
                'email'                  => strtolower(trim($reseller->email)),
                'phone'                  => $reseller->phone,
                'job_title'              => $reseller->job_title,
                'department'             => $reseller->department,
                'company_or_organization'=> $reseller->organization,
                'contact_type'           => 'referrer',
                'status'                 => in_array($reseller->status, ['active', 'pending']) ? 'active' : 'inactive',
                'linked_user_id'         => $reseller->id,
                'visibility_scope'       => 'tenant',
            ]
        );
    }

    /**
     * Upsert a Partner into the tenant contacts table.
     * Returns null if the partner has no email.
     */
    public function syncPartner(Partner $partner): ?Contact
    {
        if (empty(trim($partner->email ?? ''))) {
            return null;
        }

        $fullName = trim("{$partner->first_name} {$partner->last_name}") ?: $partner->email;

        return Contact::updateOrCreate(
            [
                'tenant_id'             => $partner->tenant_id,
                'source'                => 'partner_account',
                'internal_reference_id' => $partner->id,
            ],
            [
                'first_name'             => $partner->first_name,
                'last_name'              => $partner->last_name,
                'full_name'              => $fullName,
                'nickname'               => $partner->nickname,
                'email'                  => strtolower(trim($partner->email)),
                'phone'                  => $partner->phone_number,
                'contact_type'           => 'partner',
                'status'                 => $partner->status === 'active' ? 'active' : 'inactive',
                'linked_user_id'         => $partner->id,
                'visibility_scope'       => 'tenant',
            ]
        );
    }
}
