<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('program_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('tenant_id');
            $table->uuid('program_id');
            $table->string('reseller_id');
            $table->string('sender_type', 16);
            $table->string('sender_id');
            $table->string('sender_name');
            $table->text('body');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'program_id', 'reseller_id'], 'program_message_conversation');
        });
    }
    public function down(): void { Schema::dropIfExists('program_messages'); }
};
