<?php

use App\Database\Migrations\BaseMigration;
use Illuminate\Database\Schema\Blueprint;

class UpdateConversionRatesTables extends BaseMigration
{
    public function up()
    {
        $this->table('conversion_rates', function (Blueprint $table) {
            if (!$this->columnExists('conversion_rates', 'amount')) {
                $table->string('amount');
            }
        });
    }

    public function down()
    {
        $this->table('conversion_rates', function (Blueprint $table) {
            $this->dropColumn('conversion_rates', 'amount');
        });
    }
}
