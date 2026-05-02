<?php

use App\Database\Migrations\BaseMigration;
use Illuminate\Database\Schema\Blueprint;

return new class extends BaseMigration {

    public function up(): void
    {
        $this->create('payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('reference');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('campaign_id');
            $table->decimal('amount');
            $table->string('status');
            $table->string('channel');
            $table->string('type');
            $table->string('currency');
            $table->text('description');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        $this->drop('payment_transactions');
    }
};
