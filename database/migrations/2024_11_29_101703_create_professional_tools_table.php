<?php

use App\Database\Migrations\BaseMigration;
use Illuminate\Database\Schema\Blueprint;

return new class extends BaseMigration
{
    public function up(): void
    {
        $this->create('professional_tools', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('professional_id');
            $table->unsignedBigInteger('tool_id');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        $this->drop('professional_tools');
    }
};
