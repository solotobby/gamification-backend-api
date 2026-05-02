<?php

use App\Database\Migrations\BaseMigration;
use Illuminate\Database\Schema\Blueprint;

return new class extends BaseMigration
{
    public function up(): void
    {
        $this->addColumn('currencies', 'freebyz_withdrawal_percent', function (Blueprint $table) {
            $table->string('freebyz_withdrawal_percent')
                ->after('withdrawal_percent')
                ->nullable();
        });

        $this->addColumn('currencies', 'referral_withdrawal_percent', function (Blueprint $table) {
            $table->string('referral_withdrawal_percent')
                ->after('freebyz_withdrawal_percent')
                ->nullable();
        });
    }

    public function down(): void
    {
        $this->dropColumn('currencies', 'referral_withdrawal_percent');
        $this->dropColumn('currencies', 'freebyz_withdrawal_percent');
    }
};
