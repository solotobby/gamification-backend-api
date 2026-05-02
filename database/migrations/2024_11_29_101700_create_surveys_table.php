<?php

use App\Database\Migrations\BaseMigration;
use Illuminate\Database\Schema\Blueprint;

return new class extends BaseMigration
{
    public function up(): void
    {
        $this->create('surveys', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('category_id');
            $table->unsignedBigInteger('sub_category_id');
            $table->string('survey_code');
            $table->string('title');
            $table->longText('description')->nullable();
            $table->string('banner')->nullable();
            $table->string('amount');
            $table->string('total_amount');
            $table->string('currency');
            $table->bigInteger('number_of_response');
            $table->bigInteger('number_of_response_submitted');
            $table->string('status')->default('Pending');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        $this->drop('surveys');
    }
};
