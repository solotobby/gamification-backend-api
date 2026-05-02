<?php

use App\Database\Migrations\BaseMigration;
use Illuminate\Database\Schema\Blueprint;

return new class extends BaseMigration
{
    public function up(): void
    {
        $this->create('partnership_subscribers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('partnership_subscription_id');
            $table->string('subscriptionCode');
            $table->string('firstName');
            $table->string('lastName');
            $table->string('email');
            $table->string('phone');
            $table->string('status');
            $table->string('amount');
            $table->string('product');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        $this->drop('partnership_subscribers');
    }
};
