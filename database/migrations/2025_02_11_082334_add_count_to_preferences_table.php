<?php

use App\Database\Migrations\BaseMigration;
use Illuminate\Database\Schema\Blueprint;

return new class extends BaseMigration
{
    public function up(): void
    {
        $this->addColumn('preferences', 'count', function (Blueprint $table) {
            $table->integer('count')
                ->after('name')
                ->default(1);
        });
    }

    public function down(): void
    {
        $this->dropColumn('preferences', 'count');
    }
};
