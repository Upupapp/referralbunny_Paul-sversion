<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('program_signup_account_links', function(Blueprint $t) {
            $t->string('connection_id',100); $t->char('signup_customer_id',64);
            $t->string('payment_customer_id',120); $t->string('membership_id',100); $t->timestamp('created_at');
            $t->unique(['connection_id','payment_customer_id'],'signup_account_payment_unique');
            $t->index(['connection_id','signup_customer_id'],'signup_account_lookup');
        });
    }
    public function down(): void { Schema::dropIfExists('program_signup_account_links'); }
};
