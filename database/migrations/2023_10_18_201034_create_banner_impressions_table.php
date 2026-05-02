<?php

use App\Database\Migrations\BaseMigration;
use Illuminate\Database\Schema\Blueprint;

class CreateBannerImpressionsTable extends BaseMigration
{
    public function up()
    {
        $this->create('banner_impressions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('banner_id');
            $table->timestamps();
        });
    }

    public function down()
    {
        $this->drop('banner_impressions');
    }
}
