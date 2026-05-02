<?php

use App\Database\Migrations\BaseMigration;
use Illuminate\Database\Schema\Blueprint;

return new class extends BaseMigration {

    public function up(): void
    {
        $this->addColumn('payment_transactions', 'user_type', function (Blueprint $table) {
            $table->string('user_type')->default('regular');
        });

        $this->addColumn('payment_transactions', 'tx_type', function (Blueprint $table) {
            $table->string('tx_type')->default('Credit');
        });
    }

    public function down(): void
    {
        $this->dropColumn('payment_transactions', 'user_type');
        $this->dropColumn('payment_transactions', 'tx_type');
    }
};
