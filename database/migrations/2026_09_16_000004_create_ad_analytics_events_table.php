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
        Schema::create('ad_analytics_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_name'); // ad_requested, ad_loaded, ad_failed, ad_rendered, ad_viewed, ad_clicked
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('anonymous_session_id')->nullable()->index();
            $table->string('page_type');
            $table->string('page_url')->nullable();
            $table->string('ad_provider')->default('ADSTERRA');
            $table->string('ad_format');
            $table->string('placement_key');
            $table->string('device_type')->nullable(); // desktop, mobile, tablet
            $table->string('platform')->nullable(); // web, android, ios
            $table->string('country')->nullable();
            $table->string('ip_address')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();

            $table->index(['event_name', 'created_at']);
            $table->index(['placement_key', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ad_analytics_events');
    }
};
