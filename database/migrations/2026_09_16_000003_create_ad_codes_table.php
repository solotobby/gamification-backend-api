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
        Schema::create('ad_codes', function (Blueprint $table) {
            $table->id();
            $table->string('provider')->default('ADSTERRA');
            $table->string('ad_format'); // NATIVE_BANNER, BANNER_DESKTOP, BANNER_MOBILE, APP_CONFIG, SOCIAL_BAR, INTERSTITIAL, POPUNDER, SMARTLINK
            $table->enum('device', ['ALL', 'DESKTOP', 'MOBILE'])->default('ALL');
            $table->longText('code')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['provider', 'ad_format', 'device']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ad_codes');
    }
};
