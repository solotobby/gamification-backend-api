<?php

use App\Database\Migrations\BaseMigration;
use Illuminate\Database\Schema\Blueprint;

class UpdateWalletsUsdCurrencyTable extends BaseMigration
{
    public function up()
    {
        $this->table('wallets', function (Blueprint $table) {
            if (!$this->columnExists('wallets', 'base_currency')) {
                $table->string('base_currency')->nullable();
            }
        });
    }

    public function down()
    {
        $this->table('wallets', function (Blueprint $table) {
            $this->dropColumn('wallets', 'base_currency');
        });
    }
}
