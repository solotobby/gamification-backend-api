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
    Schema::table('career_profiles', function (Blueprint $table) {
        $table->decimal('price_min', 12, 2)->nullable()->after('professional_level');
        $table->decimal('price_max', 12, 2)->nullable()->after('price_min');
        $table->string('price_currency', 3)->nullable()->after('price_max');
    });
}

public function down(): void
{
    Schema::table('career_profiles', function (Blueprint $table) {
        $table->dropColumn(['price_min', 'price_max', 'price_currency']);
    });
}
};
