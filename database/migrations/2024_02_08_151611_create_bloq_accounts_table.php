<?php

use App\Database\Migrations\BaseMigration;
use Illuminate\Database\Schema\Blueprint;

class CreateBloqAccountsTable extends BaseMigration
{
    public function up()
    {
        $this->create('bloq_accounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('customer_id');
            $table->string('account_id');
            $table->string('customer_name');
            $table->string('balance');
            $table->string('account_number');
            $table->string('bank_name');
            $table->string('currency');
            $table->string('provider');
            $table->timestamps();
        });
    }

    public function down()
    {
        $this->drop('bloq_accounts');
    }
}
