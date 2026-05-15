<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }
        // Production schema had deal_id NOT NULL; direct threads need NULL deal_id
        DB::statement("ALTER TABLE partner_threads ALTER COLUMN deal_id DROP NOT NULL");
        DB::statement("ALTER TABLE partner_messages  ALTER COLUMN deal_id DROP NOT NULL");
    }

    public function down(): void {}
};
