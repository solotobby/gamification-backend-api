<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('job_listings', function (Blueprint $table) {
            DB::statement("ALTER TABLE job_listings MODIFY COLUMN tier ENUM('free','premium','sponsored') NOT NULL");
            $table->string('decision_reason')->nullable()->after('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('job_listings', function (Blueprint $table) {
            DB::statement("ALTER TABLE job_listings MODIFY COLUMN tier ENUM('free','premium') NOT NULL");
            $table->dropColumn('decision_reason');
        });
    }
};
