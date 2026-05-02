<?php

use App\Database\Migrations\BaseMigration;
use Illuminate\Database\Schema\Blueprint;

return new class extends BaseMigration
{
    public function up()
    {
        $this->create('businesses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('category_id');
            $table->string('business_name')->unique();
            $table->string('business_phone')->unique();
            $table->longText('description');
            $table->string('x_link')->nullable();
            $table->string('facebook_link')->nullable();
            $table->string('instagram_link')->nullable();
            $table->string('tiktok_link')->nullable();
            $table->string('pinterest_link')->nullable();
            $table->string('business_link');
            $table->string('visits')->default('0');
            $table->string('status')->default('ACTIVE');
            $table->boolean('is_live')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        $this->drop('businesses');
    }
};
