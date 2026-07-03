<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('conversion_rates', function (Blueprint $table) {
            $table->decimal('rate', 20, 9)->change();
        });
    }

    public function down()
    {
        Schema::table('conversion_rates', function (Blueprint $table) {
            $table->decimal('rate')->change();
        });
    }
};
