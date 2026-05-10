<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deal_note_attachments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('tenant_id');
            $table->uuid('deal_comment_id');
            $table->string('uploaded_by_id');
            $table->string('uploaded_by_role')->default('tenant_admin');
            $table->string('disk')->default('local');
            $table->text('path');
            $table->string('original_filename');
            $table->string('stored_filename');
            $table->string('mime_type');
            $table->unsignedInteger('file_size');
            $table->string('file_type_group')->default('document'); // image|document|spreadsheet|other
            $table->timestamps();

            $table->foreign('deal_comment_id')
                  ->references('id')->on('deal_comments')
                  ->onDelete('cascade');

            $table->index('deal_comment_id');
            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deal_note_attachments');
    }
};
