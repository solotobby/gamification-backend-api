<?php

use App\Database\Migrations\BaseMigration;
use Illuminate\Database\Schema\Blueprint;

return new class extends BaseMigration
{
    public function up(): void
    {
        $this->addColumn('currencies', 'min_withdrawal_amount', function (Blueprint $table) {
            $table->string('min_withdrawal_amount')
                ->after('min_upgrade_amount')
                ->nullable();
        });
    }

    public function down(): void
    {
        $this->dropColumn('currencies', 'min_withdrawal_amount');
    }
};
