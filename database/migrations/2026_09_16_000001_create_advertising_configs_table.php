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
        Schema::create('advertising_configs', function (Blueprint $table) {
            $table->id();
            $table->string('name')->default('default');
            $table->enum('status', ['ACTIVE', 'INACTIVE'])->default('ACTIVE');
            $table->boolean('web_enabled')->default(true);
            $table->boolean('mobile_app_enabled')->default(true);
            $table->boolean('native_enabled')->default(true);
            $table->boolean('banner_enabled')->default(true);
            $table->boolean('social_bar_enabled')->default(false);
            $table->boolean('interstitial_enabled')->default(false);
            $table->boolean('popunder_enabled')->default(false);
            $table->boolean('smartlink_enabled')->default(false);
            $table->unsignedInteger('max_ads_per_session')->default(10);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('advertising_configs');
    }
};
