<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        Schema::create('deal_approval_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('tenant_id');
            $table->string('type', 60);          // deal_stage_move | deal_archive | partner_split_review | referrer_split_review
            $table->string('subject_type', 80)->nullable();
            $table->string('subject_id', 36)->nullable();
            $table->string('deal_id', 36)->nullable()->index();
            $table->string('requested_by_type', 60);  // reseller | tenant_user
            $table->string('requested_by_id', 36);
            $table->string('assigned_to_type', 60)->nullable();
            $table->string('assigned_to_id', 36)->nullable();
            $table->string('status', 20)->default('pending'); // pending|approved|rejected|cancelled|expired
            $table->jsonb('request_payload')->nullable();
            $table->jsonb('missing_requirements')->nullable();
            $table->text('reason')->nullable();
            $table->string('reviewer_type', 60)->nullable();
            $table->string('reviewer_id', 36)->nullable();
            $table->text('reviewer_note')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'deal_id', 'type', 'status']);
            $table->index(['requested_by_type', 'requested_by_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deal_approval_requests');
    }
};
