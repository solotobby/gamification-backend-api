<?php

use App\Database\Migrations\BaseMigration;
use Illuminate\Database\Schema\Blueprint;

return new class extends BaseMigration
{
    public function up(): void
    {
        $this->addColumn('currencies', 'withdrawal_percent', function (Blueprint $table) {
            $table->string('withdrawal_percent')
                ->after('min_withdrawal_amount')
                ->nullable();
        });
    }

    public function down(): void
    {
        $this->dropColumn('currencies', 'withdrawal_percent');
    }
};
