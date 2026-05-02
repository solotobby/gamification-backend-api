<?php

use App\Database\Migrations\BaseMigration;
use Illuminate\Database\Schema\Blueprint;

class UpdateProfilesTable extends BaseMigration
{
    public function up()
    {
        $this->table('profiles', function (Blueprint $table) {
            if (!$this->columnExists('profiles', 'is_celebrity')) {
                $table->boolean('is_celebrity')->default(false);
            }
        });
    }

    public function down()
    {
        $this->table('profiles', function (Blueprint $table) {
            $this->dropColumn('profiles', 'is_celebrity');
        });
    }
}
