<?php

use App\Database\Migrations\BaseMigration;
use Illuminate\Database\Schema\Blueprint;

class UpdateUserTable extends BaseMigration
{
    public function up()
    {
        $this->table('users', function (Blueprint $table) {
            if (!$this->columnExists('users', 'is_blacklisted')) {
                $table->boolean('is_blacklisted')->default(false);
            }

            if (!$this->columnExists('users', 'is_wallet_transfered')) {
                $table->boolean('is_wallet_transfered')->default(false);
            }
        });
    }

    public function down()
    {
        $this->table('users', function (Blueprint $table) {
            $this->dropColumn('users', 'is_blacklisted');
            $this->dropColumn('users', 'is_wallet_transfered');
        });
    }
}
