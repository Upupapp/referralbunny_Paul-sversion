<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resource_folders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('tenant_id');
            $table->string('name', 255);
            $table->uuid('parent_id')->nullable();   // null = root level
            $table->string('created_by_type', 60)->nullable();
            $table->uuid('created_by_id')->nullable();
            $table->string('created_by_name', 150)->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index(['tenant_id', 'parent_id']);
        });

        Schema::create('resource_files', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('tenant_id');
            $table->uuid('folder_id')->nullable();   // null = root level
            $table->string('name', 255);             // display name (user editable)
            $table->string('disk', 20)->default('local');
            $table->string('path', 1000);            // storage path
            $table->unsignedBigInteger('size')->default(0);
            $table->string('mime_type', 200)->nullable();
            $table->string('file_type_group', 30)->nullable(); // image|document|spreadsheet|pdf|other
            $table->string('uploaded_by_type', 60)->nullable();
            $table->uuid('uploaded_by_id')->nullable();
            $table->string('uploaded_by_name', 150)->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index(['tenant_id', 'folder_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resource_files');
        Schema::dropIfExists('resource_folders');
    }
};
