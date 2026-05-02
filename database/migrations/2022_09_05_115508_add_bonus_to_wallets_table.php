<?php

use App\Database\Migrations\BaseMigration;
use Illuminate\Database\Schema\Blueprint;

return new class extends BaseMigration {

    public function up(): void
    {
        $this->addColumn('wallets', 'bonus', function (Blueprint $table) {
            $table->string('bonus')->default('0.00');
        });
    }

    public function down(): void
    {
        $this->dropColumn('wallets', 'bonus');
    }
};
