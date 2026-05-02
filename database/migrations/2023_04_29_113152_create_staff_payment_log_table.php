<?php

use App\Database\Migrations\BaseMigration;
use Illuminate\Database\Schema\Blueprint;

return new class extends BaseMigration {

    public function up(): void
    {
        $this->create('staff_payment_log', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('staff_id');
            $table->string('date');
            $table->string('amount');
            $table->string('payment_type');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        $this->drop('staff_payment_log');
    }
};
