<?php

use App\Database\Migrations\BaseMigration;
use Illuminate\Database\Schema\Blueprint;

class UpdateProfilesInfo extends BaseMigration
{
    public function up()
    {
        $this->table('profiles', function (Blueprint $table) {
            if (!$this->columnExists('profiles', 'country')) {
                $table->string('country')->nullable();
            }

            if (!$this->columnExists('profiles', 'country_code')) {
                $table->string('country_code')->nullable();
            }

            if (!$this->columnExists('profiles', 'currency')) {
                $table->string('currency')->nullable();
            }

            if (!$this->columnExists('profiles', 'currency_code')) {
                $table->string('currency_code')->nullable();
            }
        });
    }

    public function down()
    {
        $this->table('profiles', function (Blueprint $table) {
            $this->dropColumn('profiles', 'country');
            $this->dropColumn('profiles', 'country_code');
            $this->dropColumn('profiles', 'currency');
            $this->dropColumn('profiles', 'currency_code');
        });
    }
}
