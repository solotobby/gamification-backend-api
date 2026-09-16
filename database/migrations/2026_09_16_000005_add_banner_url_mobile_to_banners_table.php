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
        if (Schema::hasTable('banners') && !Schema::hasColumn('banners', 'banner_url_mobile')) {
            Schema::table('banners', function (Blueprint $table) {
                $table->string('banner_url_mobile')->nullable()->after('banner_url');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('banners') && Schema::hasColumn('banners', 'banner_url_mobile')) {
            Schema::table('banners', function (Blueprint $table) {
                $table->dropColumn('banner_url_mobile');
            });
        }
    }
};
