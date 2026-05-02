<?php

use App\Database\Migrations\BaseMigration;
use Illuminate\Database\Schema\Blueprint;

return new class extends BaseMigration {

    public function up(): void
    {
        $this->create('staff_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('date');
            $table->integer('number_paid');
            $table->string('total_salary_paid');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        $this->drop('staff_payments');
    }
};
