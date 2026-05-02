<?php

use App\Database\Migrations\BaseMigration;
use Illuminate\Database\Schema\Blueprint;

return new class extends BaseMigration {

    public function up(): void
    {
        $this->create('feedback', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('category');
            $table->longText('message');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        $this->drop('feedback');
    }
};
