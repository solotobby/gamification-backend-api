<?php

use App\Database\Migrations\BaseMigration;
use Illuminate\Database\Schema\Blueprint;

class AddGenderAgeToUsersTable extends BaseMigration
{
    public function up()
    {
        $this->table('users', function (Blueprint $table) {
            if (!$this->columnExists('users', 'age_range')) {
                $table->string('age_range')->nullable();
            }

            if (!$this->columnExists('users', 'gender')) {
                $table->string('gender')->nullable();
            }
        });
    }

    public function down()
    {
        $this->table('users', function (Blueprint $table) {
            $this->dropColumn('users', 'age_range');
            $this->dropColumn('users', 'gender');
        });
    }
}
