<?php

use App\Database\Migrations\BaseMigration;
use Illuminate\Database\Schema\Blueprint;

return new class extends BaseMigration
{
    public function up(): void
    {
        $this->create('survey_forms', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('survey_id');
            $table->string('type');
            $table->string('name');
            $table->boolean('required')->default(0);
            $table->string('choices')->nullable();
            $table->string('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        $this->drop('survey_forms');
    }
};
