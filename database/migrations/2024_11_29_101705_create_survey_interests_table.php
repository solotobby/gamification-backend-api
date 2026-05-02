<?php

use App\Database\Migrations\BaseMigration;
use Illuminate\Database\Schema\Blueprint;

return new class extends BaseMigration
{
    public function up(): void
    {
        $this->create('survey_interests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('survey_id');
            $table->unsignedBigInteger('interest_id');
            $table->string('unit');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        $this->drop('survey_interests');
    }
};
