<?php

use App\Database\Migrations\BaseMigration;
use Illuminate\Database\Schema\Blueprint;

return new class extends BaseMigration
{
    public function up(): void
    {
        $this->addColumn('categories', 'url', function (Blueprint $table) {
            $table->string('url')
                ->nullable()
                ->after('name');
        });
    }

    public function down(): void
    {
        $this->dropColumn('categories', 'url');
    }
};
