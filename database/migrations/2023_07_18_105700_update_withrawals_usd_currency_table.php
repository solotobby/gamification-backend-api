<?php

use App\Database\Migrations\BaseMigration;
use Illuminate\Database\Schema\Blueprint;

class UpdateWithrawalsUsdCurrencyTable extends BaseMigration
{
    public function up()
    {
        $this->table('withrawals', function (Blueprint $table) {
            if (!$this->columnExists('withrawals', 'base_currency')) {
                $table->string('base_currency')->nullable();
            }
        });
    }

    public function down()
    {
        $this->table('withrawals', function (Blueprint $table) {
            $this->dropColumn('withrawals', 'base_currency');
        });
    }
}
