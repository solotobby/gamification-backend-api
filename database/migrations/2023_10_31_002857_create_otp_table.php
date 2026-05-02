<?php

use App\Database\Migrations\BaseMigration;
use Illuminate\Database\Schema\Blueprint;

class CreateOtpTable extends BaseMigration
{
    public function up()
    {
        $this->create('otp', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('pinId');
            $table->string('phone_number');
            $table->string('otp');
            $table->boolean('is_verified');
            $table->timestamps();
        });
    }

    public function down()
    {
        $this->drop('otp');
    }
}
