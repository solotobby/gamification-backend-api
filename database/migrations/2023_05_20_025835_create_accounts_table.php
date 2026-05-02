<?php

use App\Database\Migrations\BaseMigration;
use Illuminate\Database\Schema\Blueprint;

return new class extends BaseMigration {

    public function up(): void
    {
        $this->create('accounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('name');
            $table->string('amount');
            $table->string('type');
            $table->text('description');
            $table->date('date');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        $this->drop('accounts');
    }
};
