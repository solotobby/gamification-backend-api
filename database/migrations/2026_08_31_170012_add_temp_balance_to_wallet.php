<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wallets', function (Blueprint $table) {
            $table->decimal('temp_balance', 15, 2)->nullable()->after('bonus');
            $table->timestamp('temp_balance_calculated_at')->nullable()->after('temp_balance');
        });

        // Without this, the reconciliation query below scans all 1.5M+ rows
        // per user instead of doing an indexed lookup — the difference
        // between seconds and hours at this scale.
        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->index(['user_id', 'status', 'currency'], 'pt_user_status_currency_idx');
        });
    }

    public function down(): void
    {
        Schema::table('wallets', function (Blueprint $table) {
            $table->dropColumn(['temp_balance', 'temp_balance_calculated_at']);
        });

        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->dropIndex('pt_user_status_currency_idx');
        });
    }
};