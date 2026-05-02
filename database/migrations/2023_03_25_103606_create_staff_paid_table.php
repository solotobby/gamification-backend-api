<?php

use App\Database\Migrations\BaseMigration;
use Illuminate\Database\Schema\Blueprint;

return new class extends BaseMigration {

    public function up(): void
    {
        $this->create('staff_paid', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('staff_payment_id');
            $table->unsignedBigInteger('user_id');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        $this->drop('staff_paid');
    }
};
