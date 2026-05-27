<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deal_approval_requests', function (Blueprint $table) {
            $table->text('clarification_message')->nullable()->after('reviewer_note');
            $table->timestamp('clarification_due_at')->nullable()->after('clarification_message');
            $table->text('visible_response')->nullable()->after('clarification_due_at');
        });
    }

    public function down(): void
    {
        Schema::table('deal_approval_requests', function (Blueprint $table) {
            $table->dropColumn(['clarification_message', 'clarification_due_at', 'visible_response']);
        });
    }
};
