<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateWithrawalsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('Withrawals', function (Blueprint $table) {
            $table->text('content')->nullable();
            $table->string('paypal_email')->nullable();
            $table->boolean('is_usd')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('Withrawals', function (Blueprint $table) {
            $table->dropColumn(['content', 'paypal_email', 'is_usd']);
        });
    }
}
