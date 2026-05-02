<?php

use App\Database\Migrations\BaseMigration;
use Illuminate\Database\Schema\Blueprint;

class CreateMembershipBadgesTable extends BaseMigration
{
    public function up()
    {
        $this->create('membership_badges', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('amount');
            $table->string('badge');
            $table->string('duration');
            $table->timestamps();
        });
    }

    public function down()
    {
        $this->drop('membership_badges');
    }
}
