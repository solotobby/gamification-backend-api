<?php

use App\Database\Migrations\BaseMigration;
use Illuminate\Database\Schema\Blueprint;

class CreateUserInterestTable extends BaseMigration
{
    public function up()
    {
        $this->create('user_interest', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('preference_id');
            $table->timestamps();
        });
    }

    public function down()
    {
        $this->drop('user_interest');
    }
}
