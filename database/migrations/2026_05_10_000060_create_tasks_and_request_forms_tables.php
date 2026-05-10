<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Tasks ─────────────────────────────────────────────────────────────
        if (!Schema::hasTable('tasks')) {
            Schema::create('tasks', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('tenant_id');
                $table->string('title');
                $table->text('description')->nullable();
                $table->enum('status', ['open','in_progress','waiting','completed','cancelled','archived'])->default('open');
                $table->enum('priority', ['low','medium','high','urgent'])->default('medium');
                $table->string('category')->nullable(); // request_form, deal, commission, system, other
                // Who is responsible
                $table->string('assigned_to_type')->nullable();
                $table->string('assigned_to_id')->nullable();
                $table->string('assigned_by_type')->nullable();
                $table->string('assigned_by_id')->nullable();
                $table->string('created_by_type')->nullable();
                $table->string('created_by_id')->nullable();
                // Polymorphic subject (the thing the task is about)
                $table->string('taskable_type')->nullable();
                $table->string('taskable_id')->nullable();
                // Source (what generated this task)
                $table->string('source_type')->nullable();
                $table->string('source_id')->nullable();
                // Original requestor info (for completion email response)
                $table->string('requestor_name')->nullable();
                $table->string('requestor_email')->nullable();
                // Timing
                $table->timestamp('due_at')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->string('completed_by_type')->nullable();
                $table->string('completed_by_id')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->json('metadata')->nullable();
                $table->enum('visibility', ['assigned_only','tenant_team','admin_only'])->default('tenant_team');
                $table->timestamps();
                $table->softDeletes();

                $table->index(['tenant_id', 'status', 'priority']);
                $table->index(['tenant_id', 'assigned_to_type', 'assigned_to_id', 'status']);
                $table->index(['tenant_id', 'due_at']);
                $table->index(['tenant_id', 'source_type', 'source_id']);
                $table->index(['tenant_id', 'requestor_email']);
                $table->index(['tenant_id', 'created_at']);
            });
        }

        // ── Task Activities ───────────────────────────────────────────────────
        if (!Schema::hasTable('task_activities')) {
            Schema::create('task_activities', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('tenant_id');
                $table->uuid('task_id');
                $table->string('actor_type')->nullable();
                $table->string('actor_id')->nullable();
                $table->string('actor_name')->nullable();
                $table->string('action_type'); // task_created, status_changed, completed, reassigned, commented, response_sent, response_failed
                $table->json('old_values')->nullable();
                $table->json('new_values')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['tenant_id', 'task_id', 'created_at']);
            });
        }

        // ── Task Completion Responses ─────────────────────────────────────────
        if (!Schema::hasTable('task_completion_responses')) {
            Schema::create('task_completion_responses', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('tenant_id');
                $table->uuid('task_id');
                $table->string('request_form_submission_id')->nullable();
                $table->string('sender_type');
                $table->string('sender_id');
                $table->string('sender_name')->nullable();
                $table->string('recipient_email');
                $table->string('recipient_name')->nullable();
                $table->string('subject');
                $table->text('body');
                $table->enum('status', ['draft','queued','sent','failed'])->default('queued');
                $table->json('attachment_paths')->nullable(); // [{disk, path, filename, size, mime}]
                $table->timestamp('sent_at')->nullable();
                $table->timestamp('failed_at')->nullable();
                $table->text('failure_reason')->nullable();
                $table->string('client_request_id')->nullable()->unique(); // idempotency
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'task_id']);
                $table->index(['tenant_id', 'status']);
                $table->index(['tenant_id', 'recipient_email']);
            });
        }

        // ── Request Forms ─────────────────────────────────────────────────────
        if (!Schema::hasTable('request_forms')) {
            Schema::create('request_forms', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('tenant_id');
                $table->string('created_by_type')->nullable();
                $table->string('created_by_id')->nullable();
                $table->string('title');
                $table->string('slug')->unique();
                $table->string('public_token', 64)->unique();
                $table->text('description')->nullable();
                $table->text('success_message')->nullable();
                $table->enum('status', ['draft','published','unpublished','archived'])->default('draft');
                $table->boolean('is_public')->default(true);
                $table->boolean('allow_multiple_recipients')->default(true);
                $table->integer('max_recipients')->default(5);
                // settings: {require_recipient, send_confirmation_email, notify_on_submit, ...}
                $table->json('settings')->nullable();
                $table->timestamp('published_at')->nullable();
                $table->timestamp('archived_at')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['tenant_id', 'status']);
                $table->index('public_token');
                $table->index('slug');
            });
        }

        // ── Request Form Fields ───────────────────────────────────────────────
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
                $table->json('options')->nullable(); // [{label, value}]
                $table->json('validation_rules')->nullable();
                $table->boolean('is_required')->default(false);
                $table->integer('sort_order')->default(0);
                $table->boolean('is_system_field')->default(false); // name, email, request_to fields
                $table->timestamps();

                $table->index(['tenant_id', 'request_form_id', 'sort_order']);
            });
        }

        // ── Request Form Recipient Options ────────────────────────────────────
        if (!Schema::hasTable('request_form_recipient_options')) {
            Schema::create('request_form_recipient_options', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('tenant_id');
                $table->uuid('request_form_id');
                $table->string('recipient_type'); // tenant_user, external
                $table->string('recipient_id')->nullable(); // TenantUser.id if internal
                $table->string('display_name');
                $table->string('email');
                $table->string('role_snapshot')->nullable(); // admin, manager
                $table->boolean('is_default')->default(false);
                $table->boolean('is_active')->default(true);
                $table->integer('sort_order')->default(0);
                $table->timestamps();

                $table->index(['tenant_id', 'request_form_id', 'is_active']);
            });
        }

        // ── Request Form Submissions ──────────────────────────────────────────
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
                $table->json('payload'); // full submitted field values
                $table->json('selected_recipient_ids'); // array of recipient option IDs
                $table->string('source_ip_hash', 64)->nullable();
                $table->string('user_agent_hash', 64)->nullable();
                $table->enum('status', ['received','tasks_created','partially_assigned','failed','spam_flagged','archived'])->default('received');
                $table->string('duplicate_fingerprint', 64)->nullable();
                $table->timestamp('submitted_at');
                $table->timestamps();

                $table->index(['tenant_id', 'request_form_id', 'submitted_at']);
                $table->index(['tenant_id', 'duplicate_fingerprint']);
                $table->index(['tenant_id', 'submitter_email']);
            });
        }

        // ── Request Form Submission Recipients ────────────────────────────────
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
                $table->index(['tenant_id', 'task_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('request_form_submission_recipients');
        Schema::dropIfExists('request_form_submissions');
        Schema::dropIfExists('request_form_recipient_options');
        Schema::dropIfExists('request_form_fields');
        Schema::dropIfExists('request_forms');
        Schema::dropIfExists('task_completion_responses');
        Schema::dropIfExists('task_activities');
        Schema::dropIfExists('tasks');
    }
};
