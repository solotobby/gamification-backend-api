<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('currencies', function (Blueprint $table) {
            $table->string('freebyz_withdrawal_percent')->after('withdrawal_percent')->nullable();
            $table->string('referral_withdrawal_percent')->after('freebyz_withdrawal_percent')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('currencies', function (Blueprint $table) {
            $table->dropColumn('freebyz_withdrawal_percent');
            $table->dropColumn('referral_withdrawal_percent');
        });
    }
};
