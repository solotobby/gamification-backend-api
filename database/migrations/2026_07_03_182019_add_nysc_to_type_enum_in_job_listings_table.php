<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE job_listings MODIFY COLUMN type ENUM('fulltime','parttime','contract','internship','gig','nysc') NOT NULL");
    }

    public function down(): void
    {
        // Reverts to the enum without 'nysc'
        DB::statement("ALTER TABLE job_listings MODIFY COLUMN type ENUM('fulltime','parttime','contract','internship','gig') NOT NULL");
    }
};
