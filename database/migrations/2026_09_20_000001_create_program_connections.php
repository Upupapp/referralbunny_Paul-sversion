<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('program_connections', function (Blueprint $t) {
            $t->uuid('id')->primary(); $t->string('tenant_id'); $t->string('program_id')->unique();
            $t->string('website'); $t->text('secret'); $t->string('status')->default('not_connected');
            $t->timestamp('last_event_at')->nullable(); $t->timestamps();
        });
        Schema::create('program_conversion_events', function (Blueprint $t) {
            $t->uuid('id')->primary(); $t->uuid('connection_id'); $t->string('external_id');
            $t->string('customer_id'); $t->string('invoice_id'); $t->string('referrer_id')->nullable();
            $t->string('offer_version_id')->nullable(); $t->string('payload_hash', 64);
            $t->string('type'); $t->string('currency', 3); $t->bigInteger('amount_minor')->default(0);
            $t->bigInteger('reward_minor')->default(0); $t->string('status');
            $t->timestamp('occurred_at'); $t->timestamp('available_at')->nullable(); $t->timestamps();
            $t->unique(['connection_id', 'external_id']);
            $t->index(['connection_id', 'customer_id']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('program_conversion_events'); Schema::dropIfExists('program_connections');
    }
};
