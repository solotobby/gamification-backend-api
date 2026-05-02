<?php

use App\Database\Migrations\BaseMigration;
use Illuminate\Database\Schema\Blueprint;

class CreateRatingsTable extends BaseMigration
{
    public function up()
    {
        $this->create('ratings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('campaign_id');
            $table->unsignedBigInteger('campaign_worker_id');
            $table->integer('rating');
            $table->string('type');
            $table->text('comment')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        $this->drop('ratings');
    }
}
