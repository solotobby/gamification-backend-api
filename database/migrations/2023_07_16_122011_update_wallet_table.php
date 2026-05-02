<?php

use App\Database\Migrations\BaseMigration;
use Illuminate\Database\Schema\Blueprint;

class UpdateWalletTable extends BaseMigration
{
    public function up()
    {
        $this->table('wallets', function (Blueprint $table) {
            if (!$this->columnExists('wallets', 'usd_balance')) {
                $table->decimal('usd_balance')->default('0.00');
            }
        });
    }

    public function down()
    {
        $this->table('wallets', function (Blueprint $table) {
            $this->dropColumn('wallets', 'usd_balance');
        });
    }
}
