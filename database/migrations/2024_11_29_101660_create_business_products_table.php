<?php

use App\Database\Migrations\BaseMigration;
use Illuminate\Database\Schema\Blueprint;

return new class extends BaseMigration
{
    public function up()
    {
        $this->create('business_products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id');
            $table->string('unique')->nullable();
            $table->string('pid')->nullable();
            $table->string('name');
            $table->string('price');
            $table->longText('description');
            $table->string('img');
            $table->string('visits')->default('0');
            $table->boolean('is_live')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        $this->drop('business_products');
    }
};
