<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Some installations recorded these migrations without retaining the tables.
        // Repair only missing tables; never rebuild or clear existing memberships.
        foreach ([
            'referrer_program_memberships' => '2026_06_17_200003_create_referrer_program_memberships_table.php',
            'partner_program_memberships' => '2026_06_17_200004_create_partner_program_memberships_table.php',
        ] as $table => $migration) {
            if (!Schema::hasTable($table)) {
                (require __DIR__.'/'.$migration)->up();
            }
        }
    }

    public function down(): void
    {
        // These tables belong to the original Programs migrations and may contain
        // live memberships. Rolling back this repair must preserve them.
    }
};
