<?php

use App\Database\Migrations\BaseMigration;
use Illuminate\Database\Schema\Blueprint;

return new class extends BaseMigration {

    public function up(): void
    {
        $this->create('market_place_products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('name');
            $table->string('amount');
            $table->string('commission');
            $table->string('total_payment');
            $table->string('commission_payment');
            $table->string('banner');
            $table->string('product');
            $table->string('type')->default('Electronics');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        $this->drop('market_place_products');
    }
};
