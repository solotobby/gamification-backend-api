<?php

use App\Database\Migrations\BaseMigration;
use Illuminate\Database\Schema\Blueprint;

class CreateBannerInterestsTable extends BaseMigration
{
    public function up()
    {
        $this->create('banner_interests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('interest_id');
            $table->unsignedBigInteger('banner_id');
            $table->integer('unit');
            $table->timestamps();
        });
    }

    public function down()
    {
        $this->drop('banner_interests');
    }
}
