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
            $table->string('hire_worker_points_amount', 10, 2)->default(0.00)->after('banner_clicks_amount');
            $table->string('job_points_amount', 10, 2)->default(0.00)->after('hire_worker_points_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('currencies', function (Blueprint $table) {
            $table->dropColumn('hire_worker_points_amount');
            $table->dropColumn('job_points_amount');
        });
    }
};
