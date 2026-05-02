<?php

use App\Database\Migrations\BaseMigration;
use Illuminate\Database\Schema\Blueprint;

class AddPhoneVerifyToProfiles extends BaseMigration
{
    public function up()
    {
        $this->table('profiles', function (Blueprint $table) {
            if (!$this->columnExists('profiles', 'phone_verified')) {
                $table->boolean('phone_verified')->default(false);
            }

            if (!$this->columnExists('profiles', 'email_verified')) {
                $table->boolean('email_verified')->default(false);
            }
        });
    }

    public function down()
    {
        $this->table('profiles', function (Blueprint $table) {
            $this->dropColumn('profiles', 'phone_verified');
            $this->dropColumn('profiles', 'email_verified');
        });
    }
}
