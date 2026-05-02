<?php

use App\Database\Migrations\BaseMigration;
use Illuminate\Database\Schema\Blueprint;

return new class extends BaseMigration {

    public function up(): void
    {
        $this->create('market_place_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('market_place_product_id');
            $table->unsignedBigInteger('user_id');
            $table->string('name');
            $table->string('amount');
            $table->string('email');
            $table->string('ref');
            $table->string('url')->nullable();
            $table->boolean('is_complete')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        $this->drop('market_place_payments');
    }
};
