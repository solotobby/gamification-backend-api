<?php

use App\Database\Migrations\BaseMigration;
use Illuminate\Database\Schema\Blueprint;

return new class extends BaseMigration
{
    public function up(): void
    {
        $this->create('portfolio_tools', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('portfolio_id');
            $table->unsignedBigInteger('tool_id');
        });
    }

    public function down(): void
    {
        $this->drop('portfolio_tools');
    }
};
