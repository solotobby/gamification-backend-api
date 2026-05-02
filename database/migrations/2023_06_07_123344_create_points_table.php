<?php

use App\Database\Migrations\BaseMigration;
use Illuminate\Database\Schema\Blueprint;

return new class extends BaseMigration {

    public function up(): void
    {
        $this->create('points', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('point');
            $table->boolean('status')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        $this->drop('points');
    }
};
