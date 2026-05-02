<?php

use App\Database\Migrations\BaseMigration;
use Illuminate\Database\Schema\Blueprint;

return new class extends BaseMigration {

    public function up(): void
    {
        $this->create('sub_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('amount');
            $table->unsignedBigInteger('category_id');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        $this->drop('sub_categories');
    }
};
