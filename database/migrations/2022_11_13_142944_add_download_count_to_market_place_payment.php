<?php

use App\Database\Migrations\BaseMigration;
use Illuminate\Database\Schema\Blueprint;

return new class extends BaseMigration {

    public function up(): void
    {
        $this->addColumn('market_place_payments', 'download_count', function (Blueprint $table) {
            $table->integer('download_count')->default(0);
        });
    }

    public function down(): void
    {
        $this->dropColumn('market_place_payments', 'download_count');
    }
};
