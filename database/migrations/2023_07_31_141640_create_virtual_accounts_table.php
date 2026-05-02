<?php

use App\Database\Migrations\BaseMigration;
use Illuminate\Database\Schema\Blueprint;

class CreateVirtualAccountsTable extends BaseMigration
{
    public function up()
    {
        $this->create('virtual_accounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('channel');
            $table->string('customer_id')->nullable();
            $table->string('customer_intgration')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('account_name')->nullable();
            $table->string('account_number')->nullable();
            $table->string('currency')->nullable();
            $table->string('status')->default(true);
            $table->timestamps();
        });
    }

    public function down()
    {
        $this->drop('virtual_accounts');
    }
}
