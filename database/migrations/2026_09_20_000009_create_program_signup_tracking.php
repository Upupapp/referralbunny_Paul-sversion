<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::create('program_signup_events',function(Blueprint $t){
   $t->id();$t->string('connection_id',100);$t->char('customer_id',64);$t->string('membership_id',100);$t->uuid('click_id')->nullable();
   $t->timestamp('occurred_at');$t->timestamp('referred_at');$t->timestamp('created_at');
   $t->unique(['connection_id','customer_id']);$t->index(['membership_id','occurred_at']);$t->index('click_id');
  });
  Schema::create('program_signup_sync',function(Blueprint $t){$t->string('connection_id',100)->primary();$t->string('cursor',128)->nullable();$t->timestamp('synced_at')->nullable();$t->string('status')->default('pending');});
 }
 public function down(): void {Schema::dropIfExists('program_signup_sync');Schema::dropIfExists('program_signup_events');}
};
