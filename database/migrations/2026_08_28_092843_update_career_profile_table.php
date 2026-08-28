<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('career_profiles', function (Blueprint $table) {
            $table->unsignedInteger('views_count')->default(0)->after('talent_score');
            $table->index('is_public');
        });
    }

    public function down(): void
    {
        Schema::table('career_profiles', function (Blueprint $table) {
            $table->dropIndex(['is_public']);
            $table->dropColumn('views_count');
        });
    }
};
