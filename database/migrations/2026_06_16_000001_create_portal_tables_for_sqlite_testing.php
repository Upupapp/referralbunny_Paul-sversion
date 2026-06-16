<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the minimum schema for PHPUnit feature tests that exercise the
 * partner and reseller portal controllers under SQLite in-memory (phpunit.xml).
 *
 * These tables exist in production Postgres but have no create-table migration
 * (they predate the project's migration history). Only runs under SQLite —
 * production runs with the real Postgres schema.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'sqlite') return;

        // ── Core lead table ─────────────────────────────────────────────────
        Schema::create('leads', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('tenant_id');
            $table->string('name');
            $table->string('stage')->default('introduction');
            $table->string('status')->default('active');
            $table->string('reseller_name')->nullable();
            $table->decimal('deal_value', 15, 2)->nullable();
            $table->decimal('base_cost', 15, 2)->nullable();
            $table->decimal('added_amount', 15, 2)->nullable();
            $table->string('commission_status')->nullable();
            $table->integer('days_left')->nullable();
            $table->text('data')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });

        // ── Commission splits (needed by Lead::scopeForResellerOrSplit) ────
        Schema::create('commission_splits', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('lead_id');
            $table->string('reseller_name')->nullable();
            $table->decimal('percentage', 8, 4)->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();

            $table->index('lead_id');
        });

        // ── Partner auth (Partner model → partner_users table) ──────────────
        Schema::create('partner_users', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('tenant_id');
            $table->string('email')->unique();
            $table->string('password')->nullable();
            $table->string('status')->default('active');
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('remember_token')->nullable();
            $table->timestamp('setup_completed_at')->nullable();
            $table->timestamps();
        });

        // ── Deal partners (authorizedDealIds — DealPartner model) ───────────
        Schema::create('deal_partners', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('tenant_id');
            $table->string('deal_id');
            $table->string('partner_user_id');
            $table->string('added_by_id')->nullable();
            $table->string('added_by_type')->nullable();
            $table->string('status')->default('active');
            $table->json('permissions')->nullable();
            $table->timestamp('invited_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('removed_at')->nullable();
            $table->timestamps();
        });

        // ── Deal partner splits (authorizedDealIds — secondary source) ──────
        Schema::create('deal_partner_splits', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('tenant_id');
            $table->string('deal_id');
            $table->string('partner_user_id')->nullable();
            $table->string('partner_name')->nullable();
            $table->string('partner_email')->nullable();
            $table->decimal('split_share_value', 10, 2)->nullable();
            $table->string('split_share_type')->nullable();
            $table->string('status')->default('active');
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') return;
        Schema::dropIfExists('deal_partner_splits');
        Schema::dropIfExists('deal_partners');
        Schema::dropIfExists('partner_users');
        Schema::dropIfExists('commission_splits');
        Schema::dropIfExists('leads');
    }
};
