<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ensures the tasks and task_activities tables exist.
 * Same pattern as 000004 — migration 000060 may have been marked as run
 * before these tables were created.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('tasks')) {
            Schema::create('tasks', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('tenant_id');
                $table->string('title');
                $table->text('description')->nullable();
                $table->enum('status', ['open','in_progress','waiting','completed','cancelled','archived'])->default('open');
                $table->enum('priority', ['low','medium','high','urgent'])->default('medium');
                $table->string('category')->nullable();
                $table->string('assigned_to_type')->nullable();
                $table->string('assigned_to_id')->nullable();
                $table->string('assigned_by_type')->nullable();
                $table->string('assigned_by_id')->nullable();
                $table->string('created_by_type')->nullable();
                $table->string('created_by_id')->nullable();
                $table->string('taskable_type')->nullable();
                $table->string('taskable_id')->nullable();
                $table->string('source_type')->nullable();
                $table->string('source_id')->nullable();
                $table->string('requestor_name')->nullable();
                $table->string('requestor_email')->nullable();
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
                $table->index(['tenant_id', 'source_type', 'source_id']);
                $table->index(['tenant_id', 'created_at']);
            });
        }

        if (!Schema::hasTable('task_activities')) {
            Schema::create('task_activities', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('tenant_id');
                $table->uuid('task_id');
                $table->string('actor_type')->nullable();
                $table->string('actor_id')->nullable();
                $table->string('actor_name')->nullable();
                $table->string('action_type');
                $table->json('old_values')->nullable();
                $table->json('new_values')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['tenant_id', 'task_id', 'created_at']);
            });
        }

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
                $table->json('attachment_paths')->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->timestamp('failed_at')->nullable();
                $table->text('failure_reason')->nullable();
                $table->string('client_request_id')->nullable()->unique();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'task_id']);
            });
        }
    }

    public function down(): void {}
};
