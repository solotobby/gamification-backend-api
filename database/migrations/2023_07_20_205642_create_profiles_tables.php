<?php

use App\Database\Migrations\BaseMigration;
use Illuminate\Database\Schema\Blueprint;

class CreateProfilesTables extends BaseMigration
{
    public function up()
    {
        $this->create('profiles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('avarta')->nullable();
            $table->boolean('is_welcome')->default(false);
            $table->timestamps();
        });
    }

    public function down()
    {
        $this->drop('profiles');
    }
}
