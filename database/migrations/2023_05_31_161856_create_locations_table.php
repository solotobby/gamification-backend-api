<?php

use App\Database\Migrations\BaseMigration;
use Illuminate\Database\Schema\Blueprint;

return new class extends BaseMigration {

    public function up(): void
    {
        $this->create('locations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('activity')->nullable();
            $table->string('ip')->nullable();
            $table->string('countryName')->nullable();
            $table->string('countryCode')->nullable();
            $table->string('regionName')->nullable();
            $table->string('regionCode')->nullable();
            $table->string('cityName')->nullable();
            $table->string('zipCode')->nullable();
            $table->string('areaCode')->nullable();
            $table->string('timezone')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        $this->drop('locations');
    }
};
