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
        Schema::create('currencies', function (Blueprint $table) {
            $table->id();
            $table->string('code');
            $table->string('country');
            $table->string('upgrade_fee')->nullable();
            $table->string('allow_upload')->nullable();
            $table->string('priotize')->nullable();
            $table->string('referral_commission')->nullable();
            $table->string('min_upgrade_amount')->nullable();
            $table->string('base_rate')->nullable();
            $table->boolean('is_active');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('currencies');
    }
};
