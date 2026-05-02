<?php

use App\Database\Migrations\BaseMigration;
use Illuminate\Database\Schema\Blueprint;

class UpdateUsersTable extends BaseMigration
{
    public function up()
    {
        $this->table('users', function (Blueprint $table) {
            if (!$this->columnExists('users', 'base_currency')) {
                $table->string('base_currency')->nullable();
            }
        });
    }

    public function down()
    {
        $this->table('users', function (Blueprint $table) {
            $this->dropColumn('users', 'base_currency');
        });
    }
}
