<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void
    {
        Schema::create('program_referral_links', function (Blueprint $table) {
            $table->string('code', 8)->primary();
            $table->uuid('membership_id')->unique();
            $table->timestamp('created_at');
        });
    }
    public function down(): void { Schema::dropIfExists('program_referral_links'); }
};
