<?php

use App\Database\Migrations\BaseMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends BaseMigration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->addIndex('currencies', 'currencies_code_idx', function (Blueprint $table) {
            $table->index(['code', 'is_active'], 'currencies_code_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->dropIndex('currencies', 'currencies_code_idx');
    }
};
