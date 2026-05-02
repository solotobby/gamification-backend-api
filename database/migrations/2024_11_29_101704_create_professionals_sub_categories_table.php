<?php

use App\Database\Migrations\BaseMigration;
use Illuminate\Database\Schema\Blueprint;

return new class extends BaseMigration
{
    public function up(): void
    {
        $this->create('professionals_sub_categories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('professional_category_id');
            $table->string('name');
            $table->string('unique_id');
            $table->string('status')->default('active');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        $this->drop('professionals_sub_categories');
    }
};
