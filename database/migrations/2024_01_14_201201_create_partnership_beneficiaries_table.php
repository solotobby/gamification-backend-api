<?php

use App\Database\Migrations\BaseMigration;
use Illuminate\Database\Schema\Blueprint;

class CreatePartnershipBeneficiariesTable extends BaseMigration
{
    public function up()
    {
        $this->create('partnership_beneficiaries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('partnership_subscriptions_id');
            $table->string('firstName');
            $table->string('lastName');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('dateOfBirth');
            $table->string('gender');
            $table->timestamps();
        });
    }

    public function down()
    {
        $this->drop('partnership_beneficiaries');
    }
}
