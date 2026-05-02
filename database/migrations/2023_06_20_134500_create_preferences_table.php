<?php

use App\Database\Migrations\BaseMigration;
use Illuminate\Database\Schema\Blueprint;

class CreatePreferencesTable extends BaseMigration
{
    public function up()
    {
        $this->create('preferences', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
    }

    public function down()
    {
        $this->drop('preferences');
    }
}
