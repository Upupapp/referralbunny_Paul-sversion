<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Normalize legacy 'tenant_user' value to the canonical 'tenant_admin'
        DB::statement("UPDATE notifications SET notifiable_type = 'tenant_admin' WHERE notifiable_type = 'tenant_user'");
        DB::statement('ALTER TABLE notifications DROP CONSTRAINT IF EXISTS notifications_notifiable_type_check');
        DB::statement("ALTER TABLE notifications ADD CONSTRAINT notifications_notifiable_type_check CHECK (notifiable_type IN ('super_admin','tenant_admin','reseller','partner'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE notifications DROP CONSTRAINT IF EXISTS notifications_notifiable_type_check');
        DB::statement("ALTER TABLE notifications ADD CONSTRAINT notifications_notifiable_type_check CHECK (notifiable_type IN ('super_admin','tenant_admin','reseller'))");
    }
};
