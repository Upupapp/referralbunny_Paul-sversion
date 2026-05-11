<?php

namespace App\Console\Commands;

use App\Models\RequestFormRecipientOption;
use App\Models\Reseller;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\TenantUser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AddOdetteToLguIdsCommand extends Command
{
    protected $signature   = 'lguids:add-odette {--form-id= : UUID of the LGU IDS Request Form to add as recipient}';
    protected $description = 'Add Odette Belza (odette@lguids.com.ph) as LGU IDS manager, auto-referrer, and request form recipient';

    public function handle(): int
    {
        $email     = 'odette@lguids.com.ph';
        $firstName = 'Odette';
        $lastName  = 'Belza';

        // ── Resolve LGU IDS tenant ────────────────────────────────────────
        $tenant = Tenant::where('slug', 'lgu-ids')
            ->orWhere('name', 'like', '%LGU IDS%')
            ->first();

        if (! $tenant) {
            $this->error('LGU IDS tenant not found. Check slug or name.');
            return 1;
        }

        $this->info("Tenant: {$tenant->name} ({$tenant->id})");

        // ── Create or find TenantUser ─────────────────────────────────────
        $user = TenantUser::whereRaw('lower(email) = ?', [strtolower($email)])->first();

        if (! $user) {
            $user = TenantUser::create([
                'id'         => (string) Str::uuid(),
                'first_name' => $firstName,
                'last_name'  => $lastName,
                'email'      => strtolower($email),
                'password'   => Hash::make(Str::random(32)), // must set own password via invite
                'status'     => 'active',
            ]);
            $this->info("Created TenantUser: {$user->id}");
        } else {
            $this->info("Found existing TenantUser: {$user->id}");
        }

        // ── Ensure manager membership in LGU IDS ─────────────────────────
        $membership = TenantMembership::where('tenant_id', $tenant->id)
            ->where('tenant_user_id', $user->id)
            ->first();

        if (! $membership) {
            TenantMembership::create([
                'tenant_id'                 => $tenant->id,
                'tenant_user_id'            => $user->id,
                'role'                      => 'manager',
                'status'                    => 'active',
                'joined_by_invitation'      => false,
                'joined_at'                 => now(),
                'password_review_completed' => true,
                'setup_completed'           => true,
            ]);
            $this->info('Created manager membership.');
        } elseif ($membership->status !== 'active') {
            $membership->update(['status' => 'active', 'role' => 'manager']);
            $this->info('Reactivated and set to manager.');
        } else {
            $this->info("Membership exists: role={$membership->role}");
        }

        // ── Add as Reseller (auto-referrer for managers) ──────────────────
        $reseller = Reseller::where('tenant_id', $tenant->id)
            ->whereRaw('lower(email) = ?', [strtolower($email)])
            ->first();

        if (! $reseller) {
            Reseller::create([
                'tenant_id'             => $tenant->id,
                'name'                  => trim("{$firstName} {$lastName}"),
                'email'                 => strtolower($email),
                'status'                => 'active',
                'joined_date'           => now()->toDateString(),
                'linked_tenant_user_id' => $user->id,
            ]);
            $this->info('Created Reseller (auto-referrer) record.');
        } else {
            if ($reseller->status !== 'active') {
                $reseller->update(['status' => 'active', 'joined_date' => now()->toDateString()]);
                $this->info('Reactivated existing Reseller record.');
            } else {
                $this->info('Reseller record already exists and is active.');
            }
        }

        // ── Add as RequestFormRecipientOption ─────────────────────────────
        $formId = $this->option('form-id');

        if ($formId) {
            $form = DB::table('request_forms')
                ->where('id', $formId)
                ->where('tenant_id', $tenant->id)
                ->first();

            if (! $form) {
                $this->warn("Form {$formId} not found for this tenant — skipping recipient option.");
            } else {
                $exists = RequestFormRecipientOption::where('request_form_id', $formId)
                    ->where('recipient_id', $user->id)
                    ->exists();

                if (! $exists) {
                    RequestFormRecipientOption::create([
                        'tenant_id'       => $tenant->id,
                        'request_form_id' => $formId,
                        'recipient_type'  => 'tenant_user',
                        'recipient_id'    => $user->id,
                        'display_name'    => trim("{$firstName} {$lastName}"),
                        'email'           => strtolower($email),
                        'role_snapshot'   => 'manager',
                        'sort_order'      => 10,
                    ]);
                    $this->info("Added as recipient option on form: {$form->title}");
                } else {
                    $this->info("Already a recipient option on that form.");
                }
            }
        } else {
            $this->warn('No --form-id provided. To also add as form recipient, run with --form-id=<uuid>.');
        }

        $this->newLine();
        $this->line('✓ Done. Odette Belza is now:');
        $this->line('  · LGU IDS Tenant Manager (active membership)');
        $this->line('  · Auto-referrer (active Reseller record)');
        if ($formId) {
            $this->line('  · Possible assignee on the specified Request Form');
        }

        return 0;
    }
}
