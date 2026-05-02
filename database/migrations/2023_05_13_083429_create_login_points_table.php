<?php

use App\Database\Migrations\BaseMigration;
use Illuminate\Database\Schema\Blueprint;

return new class extends BaseMigration {

    public function up(): void
    {
        $this->create('login_points', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('date')->nullable();
            $table->integer('point');
            $table->boolean('is_redeemed')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        $this->drop('login_points');
    }
};
