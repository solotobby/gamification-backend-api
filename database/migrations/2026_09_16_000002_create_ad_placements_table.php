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
        Schema::create('ad_placements', function (Blueprint $table) {
            $table->id();
            $table->string('placement_key')->unique();
            $table->string('page_type'); // JOBS_LIST, JOB_DETAIL, TASKS_LIST, TASK_DETAIL, BLOG_LIST, BLOG_ARTICLE, TALENT_MARKETPLACE, etc.
            $table->string('ad_format'); // NATIVE_BANNER, STANDARD_BANNER, SOCIAL_BAR, INTERSTITIAL, POPUNDER, SMARTLINK
            $table->enum('device', ['ALL', 'DESKTOP', 'MOBILE'])->default('ALL');
            $table->enum('platform', ['ALL', 'WEB', 'ANDROID_APP', 'IOS_APP'])->default('ALL');
            $table->string('position'); // TOP, BOTTOM, AFTER_JOB_5, AFTER_REQUIREMENTS, AFTER_INTRO, BETWEEN_CONTENT, END_CONTENT
            $table->unsignedInteger('display_interval')->nullable(); // e.g. 5 for every 5th item
            $table->unsignedInteger('frequency_cap')->nullable()->default(1); // max ads on this placement per page load
            $table->boolean('enabled')->default(true);
            $table->integer('priority')->default(0);
            $table->text('code')->nullable(); // optional placement-specific snippet override
            $table->timestamps();

            $table->index(['page_type', 'enabled']);
            $table->index(['platform', 'device']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ad_placements');
    }
};
