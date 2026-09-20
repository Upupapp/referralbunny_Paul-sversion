<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('program_referral_clicks', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->uuid('membership_id');
            $t->char('visitor_hash',64);
            $t->timestamp('created_at');
            $t->index(['membership_id','created_at']);
            $t->index(['membership_id','visitor_hash']);
        });
    }
    public function down(): void { Schema::dropIfExists('program_referral_clicks'); }
};
