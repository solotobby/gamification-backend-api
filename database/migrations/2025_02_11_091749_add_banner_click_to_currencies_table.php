<?php

use App\Database\Migrations\BaseMigration;
use Illuminate\Database\Schema\Blueprint;

return new class extends BaseMigration
{
    public function up(): void
    {
        $this->addColumn('currencies', 'banner_clicks_amount', function (Blueprint $table) {
            $table->string('banner_clicks_amount')
                ->after('base_rate')
                ->default('2');
        });
    }

    public function down(): void
    {
        $this->dropColumn('currencies', 'banner_clicks_amount');
    }
};
