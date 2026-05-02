<?php

use App\Database\Migrations\BaseMigration;
use Illuminate\Database\Schema\Blueprint;

class CreateBannersTable extends BaseMigration
{
    public function up()
    {
        $this->create('banners', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('banner_id');
            $table->string('banner_url');
            $table->string('external_link');
            $table->string('age_bracket');
            $table->string('ad_placement_point');
            $table->string('adplacement_position');
            $table->string('duration');
            $table->string('country');
            $table->decimal('amount', 10, 2);
            $table->boolean('status')->default(false);
            $table->bigInteger('impression');
            $table->bigInteger('clicks');
            $table->string('currency')->default('NGN');
            $table->timestamps();
        });
    }

    public function down()
    {
        $this->drop('banners');
    }
}
