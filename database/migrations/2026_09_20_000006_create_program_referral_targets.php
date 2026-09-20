<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void { Schema::create('program_referral_targets', function(Blueprint $t) {
  $t->id(); $t->string('tenant_id'); $t->uuid('program_id')->unique(); $t->unsignedInteger('total'); $t->date('starts_on'); $t->date('ends_on'); $t->timestamps();
 }); }
 public function down(): void { Schema::dropIfExists('program_referral_targets'); }
};
