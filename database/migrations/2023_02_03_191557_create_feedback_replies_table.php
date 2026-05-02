<?php

use App\Database\Migrations\BaseMigration;
use Illuminate\Database\Schema\Blueprint;

return new class extends BaseMigration {

    public function up(): void
    {
        $this->create('feedback_replies', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('feedback_id');
            $table->longText('message');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        $this->drop('feedback_replies');
    }
};
