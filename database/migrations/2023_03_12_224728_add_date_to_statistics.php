<?php

use App\Database\Migrations\BaseMigration;
use Illuminate\Database\Schema\Blueprint;

return new class extends BaseMigration {

    public function up(): void
    {
        $this->addColumn('statistics', 'date', function (Blueprint $table) {
            $table->string('date')->nullable();
        });
    }

    public function down(): void
    {
        $this->dropColumn('statistics', 'date');
    }
};
