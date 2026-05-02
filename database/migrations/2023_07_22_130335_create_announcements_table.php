<?php

use App\Database\Migrations\BaseMigration;
use Illuminate\Database\Schema\Blueprint;

class CreateAnnouncementsTable extends BaseMigration
{
    public function up()
    {
        $this->create('announcements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->longText('content');
            $table->boolean('status')->default(true);
            $table->timestamps();
        });
    }

    public function down()
    {
        $this->drop('announcements');
    }
}
