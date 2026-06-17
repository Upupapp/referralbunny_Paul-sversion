<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_brand_versions', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->string('logo_url')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('accent_color', 7)->nullable();
            $table->string('sidebar_color', 7)->nullable();
            $table->unsignedTinyInteger('health_score')->default(0);
            // Versions are immutable snapshots — no updated_at
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_brand_versions');
    }
};
