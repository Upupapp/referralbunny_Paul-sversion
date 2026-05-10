<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deal_note_mentions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('tenant_id');
            $table->uuid('deal_comment_id');
            // tenant_admin | referrer | partner | contact
            $table->string('mentionable_type');
            $table->string('mentionable_id');
            $table->string('display_name_snapshot');
            $table->timestamps();

            $table->foreign('deal_comment_id')
                  ->references('id')->on('deal_comments')
                  ->onDelete('cascade');

            $table->index('deal_comment_id');
            $table->index(['mentionable_type', 'mentionable_id']);
            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deal_note_mentions');
    }
};
