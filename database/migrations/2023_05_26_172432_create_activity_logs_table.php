<?php

use App\Database\Migrations\BaseMigration;
use Illuminate\Database\Schema\Blueprint;

return new class extends BaseMigration {

    public function up(): void
    {
        $this->create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('activity_type');
            $table->text('description');
            $table->string('user_type');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        $this->drop('activity_logs');
    }
};
