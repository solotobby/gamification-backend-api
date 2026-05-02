<?php

use App\Database\Migrations\BaseMigration;
use Illuminate\Database\Schema\Blueprint;

return new class extends BaseMigration
{
    public function up(): void
    {
        $this->create('professional_domains', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('professional_sub_categories_id');
            $table->string('name');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        $this->drop('professional_domains');
    }
};
