<?php

use App\Database\Migrations\BaseMigration;
use Illuminate\Database\Schema\Blueprint;

class CreateConversionRatesTable extends BaseMigration
{
    public function up()
    {
        $this->create('conversion_rates', function (Blueprint $table) {
            $table->id();
            $table->string('from');
            $table->string('to');
            $table->decimal('rate');
            $table->boolean('status')->default(true);
            $table->timestamps();
        });
    }

    public function down()
    {
        $this->drop('conversion_rates');
    }
}
