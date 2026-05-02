<?php

use App\Database\Migrations\BaseMigration;
use Illuminate\Database\Schema\Blueprint;

return new class extends BaseMigration
{
    public function up(): void
    {
        $this->addColumn('campaigns', 'allow_upload', function (Blueprint $table) {
            $table->boolean('allow_upload')->default(false);
        });
    }

    public function down(): void
    {
        $this->dropColumn('campaigns', 'allow_upload');
    }
};
