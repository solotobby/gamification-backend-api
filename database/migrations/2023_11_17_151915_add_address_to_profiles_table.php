<?php

use App\Database\Migrations\BaseMigration;
use Illuminate\Database\Schema\Blueprint;

class AddAddressToProfilesTable extends BaseMigration
{
    public function up()
    {
        $this->table('profiles', function (Blueprint $table) {
            if (!$this->columnExists('profiles', 'address')) {
                $table->string('address')->nullable();
            }

            if (!$this->columnExists('profiles', 'is_xmas')) {
                $table->boolean('is_xmas')->default(false);
            }
        });
    }

    public function down()
    {
        $this->table('profiles', function (Blueprint $table) {
            $this->dropColumn('profiles', 'address');
            $this->dropColumn('profiles', 'is_xmas');
        });
    }
}
