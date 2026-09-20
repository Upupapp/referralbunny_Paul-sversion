<?php
namespace Tests\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

trait QuickProgramSchema
{
    protected function buildQuickProgramSchema(): void
    {
        Schema::create('tenants', function (Blueprint $t) {
            $t->string('id')->primary(); $t->string('name'); $t->string('slug')->nullable();
            $t->string('status')->default('active'); $t->string('timezone')->default('UTC');
            $t->string('preferred_currency')->default('PHP'); $t->timestamps();
        });
        Schema::create('tenant_users', function (Blueprint $t) {
            $t->string('id')->primary(); $t->string('email'); $t->string('password');
            $t->string('status')->default('active'); $t->rememberToken(); $t->timestamps();
        });
        Schema::create('tenant_memberships', function (Blueprint $t) {
            $t->string('id')->primary(); $t->string('tenant_id'); $t->string('tenant_user_id');
            $t->string('role'); $t->string('status')->default('active'); $t->text('permissions_json')->nullable(); $t->timestamps();
        });
        Schema::create('resellers', function (Blueprint $t) {
            $t->uuid('id')->primary(); $t->string('tenant_id'); $t->string('name'); $t->string('email');
            $t->string('status')->default('active'); $t->softDeletes(); $t->timestamps();
        });
        foreach ([
            '2026_06_13_000001_create_referral_program_setup_tables.php',
            '2026_06_17_200001_create_programs_table.php',
            '2026_06_17_200002_create_program_groups_table.php',
            '2026_06_17_200003_create_referrer_program_memberships_table.php',
            '2026_06_17_200005_create_program_configuration_versions_table.php',
            '2026_06_17_200006_create_program_offers_table.php',
            '2026_06_17_200007_create_program_offer_versions_table.php',
            '2026_09_20_000001_create_program_connections.php',
            '2026_09_20_000002_add_website_snippet_detection.php',
            '2026_09_20_000003_add_platform_connection.php',
            '2026_09_20_000004_add_program_operating_mode.php',
        ] as $file) (require database_path('migrations/'.$file))->up();
    }
}
