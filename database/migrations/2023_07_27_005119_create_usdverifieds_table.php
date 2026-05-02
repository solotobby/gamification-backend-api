<?php

use App\Database\Migrations\BaseMigration;
use Illuminate\Database\Schema\Blueprint;

class CreateUsdverifiedsTable extends BaseMigration
{
    public function up()
    {
        $this->create('usdverifieds', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('referral_id')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        $this->drop('usdverifieds');
    }
}
