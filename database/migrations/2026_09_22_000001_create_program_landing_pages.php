<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void { Schema::create('program_landing_pages',function(Blueprint $t){$t->uuid('program_id')->primary();$t->string('tenant_id')->index();$t->json('content')->nullable();$t->boolean('published')->default(false);$t->string('updated_by')->nullable();$t->timestamps();}); }
 public function down(): void {Schema::dropIfExists('program_landing_pages');}
};
