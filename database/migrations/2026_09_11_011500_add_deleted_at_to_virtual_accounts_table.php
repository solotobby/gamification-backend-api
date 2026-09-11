<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('virtual_accounts')) {
            Schema::table('virtual_accounts', function (Blueprint $table) {
                if (!Schema::hasColumn('virtual_accounts', 'deleted_at')) {
                    $table->softDeletes()->after('status');
                }
                $table->index(['user_id', 'deleted_at'], 'va_user_deleted_idx');
                $table->index(['channel', 'deleted_at'], 'va_channel_deleted_idx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('virtual_accounts')) {
            Schema::table('virtual_accounts', function (Blueprint $table) {
                $table->dropIndex('va_user_deleted_idx');
                $table->dropIndex('va_channel_deleted_idx');
                if (Schema::hasColumn('virtual_accounts', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
            });
        }
    }
};
