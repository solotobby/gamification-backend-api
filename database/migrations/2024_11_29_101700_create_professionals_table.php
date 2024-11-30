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
        Schema::create('professionals', function (Blueprint $table) {
            $table->id();
            $table->string('_link');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('professional_category_id');
            $table->unsignedBigInteger('professional_sub_category_id');
            $table->unsignedBigInteger('professional_domain_id');
            $table->string('full_name');
            $table->string('employment_status');
            $table->text('main_production_domain')->nullable();
            $table->string('title');
            $table->longText('work_experience');
            $table->string('communication_mode');
            $table->bigInteger('avg_rating')->default(0);
            $table->text('website_link')->nullable();
            $table->text('fb_link')->nullable();
            $table->text('tiktok_link')->nullable();
            $table->text('x_link')->nullable();
            $table->text('linkedin_link')->nullable();
            $table->text('instagram_link')->nullable();
            $table->text('geo')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('professionals');
    }
};
