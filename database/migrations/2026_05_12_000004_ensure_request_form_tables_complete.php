<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ensures all request_form_* sub-tables exist.
 * The original migration (000060) uses hasTable() guards, so if it was already
 * marked as "run" but some tables failed silently, they stay missing.
 * This migration re-checks and creates any that are absent.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('request_form_fields')) {
            Schema::create('request_form_fields', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('tenant_id');
                $table->uuid('request_form_id');
                $table->string('label');
                $table->string('field_key');
                $table->enum('field_type', ['text','email','textarea','select','multi_select','checkbox','radio','number','date','hidden'])->default('text');
                $table->string('placeholder')->nullable();
                $table->text('helper_text')->nullable();
                $table->json('options')->nullable();
                $table->json('validation_rules')->nullable();
                $table->boolean('is_required')->default(false);
                $table->integer('sort_order')->default(0);
                $table->boolean('is_system_field')->default(false);
                $table->timestamps();
                $table->index(['tenant_id', 'request_form_id', 'sort_order']);
            });
        }

        if (!Schema::hasTable('request_form_recipient_options')) {
            Schema::create('request_form_recipient_options', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('tenant_id');
                $table->uuid('request_form_id');
                $table->string('recipient_type');
                $table->string('recipient_id')->nullable();
                $table->string('display_name');
                $table->string('email');
                $table->string('role_snapshot')->nullable();
                $table->boolean('is_default')->default(false);
                $table->boolean('is_active')->default(true);
                $table->integer('sort_order')->default(0);
                $table->timestamps();
                $table->index(['tenant_id', 'request_form_id', 'is_active']);
            });
        }

        if (!Schema::hasTable('request_form_submissions')) {
            Schema::create('request_form_submissions', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('tenant_id');
                $table->uuid('request_form_id');
                $table->string('public_submission_uuid', 36)->unique();
                $table->string('submitter_name');
                $table->string('submitter_email');
                $table->string('request_for')->nullable();
                $table->text('notes')->nullable();
                $table->json('payload');
                $table->json('selected_recipient_ids');
                $table->string('source_ip_hash', 64)->nullable();
                $table->string('user_agent_hash', 64)->nullable();
                $table->enum('status', ['received','tasks_created','partially_assigned','failed','spam_flagged','archived'])->default('received');
                $table->string('duplicate_fingerprint', 64)->nullable();
                $table->timestamp('submitted_at');
                $table->timestamps();
                $table->index(['tenant_id', 'request_form_id', 'submitted_at']);
            });
        }

        if (!Schema::hasTable('request_form_submission_recipients')) {
            Schema::create('request_form_submission_recipients', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('tenant_id');
                $table->uuid('request_form_submission_id');
                $table->string('recipient_type');
                $table->string('recipient_id')->nullable();
                $table->string('recipient_email');
                $table->string('recipient_name');
                $table->uuid('task_id')->nullable();
                $table->enum('assignment_status', ['pending','task_created','failed','skipped'])->default('pending');
                $table->timestamps();
                $table->index(['tenant_id', 'request_form_submission_id']);
            });
        }

        // Add slug + public_token to request_forms if they were created without them
        if (Schema::hasTable('request_forms')) {
            Schema::table('request_forms', function (Blueprint $table) {
                if (!Schema::hasColumn('request_forms', 'slug')) {
                    $table->string('slug')->nullable()->after('title');
                    try { $table->unique('slug'); } catch (\Throwable) {}
                }
                if (!Schema::hasColumn('request_forms', 'public_token')) {
                    $table->string('public_token', 64)->nullable()->after('slug');
                    try { $table->unique('public_token'); } catch (\Throwable) {}
                }
                if (!Schema::hasColumn('request_forms', 'published_at')) {
                    $table->timestamp('published_at')->nullable();
                }
                if (!Schema::hasColumn('request_forms', 'archived_at')) {
                    $table->timestamp('archived_at')->nullable();
                }
            });
        }
    }

    public function down(): void {}
};
