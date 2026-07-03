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
        Schema::table('job_listings', function (Blueprint $table) {
            $table->string('application_link')->nullable()->after('company_website');
            $table->boolean('user_posted')->default(false)->after('posted_by');

        });
          Schema::table('currencies', function (Blueprint $table) {
            $table->string('job_listing_amount', 10, 2)->default(0.00)->after('hire_worker_points_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('job_listings', function (Blueprint $table) {
            $table->dropColumn('application_link');
            $table->dropColumn('user_posted');
        });
        
        Schema::table('currencies', function (Blueprint $table) {
            $table->dropColumn('job_listing_amount');
        });
    }
};
