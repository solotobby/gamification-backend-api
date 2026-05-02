<?php

use App\Database\Migrations\BaseMigration;
use Illuminate\Database\Schema\Blueprint;

return new class extends BaseMigration {

    public function up(): void
    {
        $this->create('data_bundles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('amount');
            $table->boolean('status')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        $this->drop('data_bundles');
    }
};
