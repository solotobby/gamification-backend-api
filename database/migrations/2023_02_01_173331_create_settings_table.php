<?php

use App\Database\Migrations\BaseMigration;
use Illuminate\Database\Schema\Blueprint;

return new class extends BaseMigration {

    public function up(): void
    {
        $this->create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('status')->default(true);
            $table->string('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        $this->drop('settings');
    }
};
