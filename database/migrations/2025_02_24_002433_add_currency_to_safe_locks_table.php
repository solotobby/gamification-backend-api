<?php

use App\Database\Migrations\BaseMigration;
use Illuminate\Database\Schema\Blueprint;

return new class extends BaseMigration
{
    public function up(): void
    {
        $this->addColumn('safe_locks', 'currency', function (Blueprint $table) {
            $table->string('currency')
                ->default('NGN')
                ->after('total_payment');
        });
    }

    public function down(): void
    {
        $this->dropColumn('safe_locks', 'currency');
    }
};
