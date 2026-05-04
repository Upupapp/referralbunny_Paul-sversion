<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\TenantUser;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeds the LGU IDS tenant and its official admin account.
 *
 * Usage:
 *   php artisan db:seed --class=LguIdsTenantSeeder
 *
 * Required environment variable:
 *   LGU_IDS_ADMIN_PASSWORD — the raw password for Paul@lguids.com.ph
 *
 * If LGU_IDS_ADMIN_PASSWORD is not set, the seeder will abort to prevent
 * creating an account with no valid password.
 */
class LguIdsTenantSeeder extends Seeder
{
    public function run(): void
    {
        $rawPassword = env('LGU_IDS_ADMIN_PASSWORD');

        if (empty($rawPassword)) {
            $this->command->error(
                'LGU_IDS_ADMIN_PASSWORD is not set. ' .
                'Add it to your .env file and re-run the seeder.'
            );
            return;
        }

        // ── Ensure LGU IDS tenant exists ──────────────────────
        $tenant = Tenant::updateOrCreate(
            ['id' => 'lgu-ids'],
            [
                'name'               => 'LGU IDS',
                'slug'               => 'lgu-ids',
                'program_name'       => 'LGU IDS Referral Program',
                'industry'           => 'Government Technology / GovTech',
                'status'             => 'active',
                'admin_email'        => 'paul@lguids.com.ph',
                'admin_name'         => 'Paul',
                'preferred_currency' => 'PHP',
                'country'            => 'Philippines',
                'timezone'           => 'Asia/Manila',
                'setup_completed'    => true,
            ]
        );

        $this->command->info("Tenant: {$tenant->name} ({$tenant->id}) — OK");

        // ── Create or update the official admin user ──────────
        $email = 'paul@lguids.com.ph';

        $user = TenantUser::whereRaw('lower(email) = ?', [strtolower($email)])->first();

        if ($user) {
            $user->update(['password' => Hash::make($rawPassword)]);
            $this->command->info("TenantUser: updated password for {$email}");
        } else {
            $user = TenantUser::create([
                'first_name' => 'Paul',
                'last_name'  => '',
                'email'      => strtolower($email),
                'password'   => Hash::make($rawPassword),
                'status'     => 'active',
            ]);
            $this->command->info("TenantUser: created {$email}");
        }

        // ── Create or update membership ───────────────────────
        TenantMembership::updateOrCreate(
            [
                'tenant_id'      => $tenant->id,
                'tenant_user_id' => $user->id,
            ],
            [
                'role'                      => 'owner',
                'status'                    => 'active',
                'joined_by_invitation'      => false,
                'password_review_completed' => true,
                'setup_completed'           => true,
            ]
        );

        $this->command->info("Membership: Paul → LGU IDS (owner) — OK");
        $this->command->newLine();
        $this->command->line("  Login email : {$email}");
        $this->command->line("  Tenant      : {$tenant->name}");
        $this->command->line("  Role        : owner");
    }
}
