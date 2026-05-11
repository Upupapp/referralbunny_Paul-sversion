<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_legal_agreements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->string('type', 50)->default('custom'); // nda, non_compete, confidentiality, custom
            $table->string('title', 200);
            $table->longText('content');
            $table->json('applicable_roles')->nullable(); // null = applies to all roles
            $table->boolean('is_required')->default(true);
            $table->boolean('is_active')->default(true);
            $table->string('version', 50)->nullable();
            $table->date('effective_date')->nullable();
            $table->integer('display_order')->default(0);
            $table->timestamps();
        });

        Schema::create('tenant_legal_agreement_acceptances', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_legal_agreement_id')->index();
            $table->uuid('tenant_id')->index();
            $table->string('user_type', 50); // tenant_user, reseller
            $table->string('user_id', 36)->index();
            $table->string('user_role', 50)->nullable();
            $table->timestamp('accepted_at');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->unique(
                ['tenant_legal_agreement_id', 'user_type', 'user_id'],
                'tla_acc_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_legal_agreement_acceptances');
        Schema::dropIfExists('tenant_legal_agreements');
    }
};
