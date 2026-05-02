<?php

use App\Database\Migrations\BaseMigration;
use Illuminate\Database\Schema\Blueprint;

return new class extends BaseMigration {

    public function up(): void
    {
        $this->addColumn('campaigns', 'extension_references', function (Blueprint $table) {
            $table->string('extension_references')->nullable();
        });
    }

    public function down(): void
    {
        $this->dropColumn('campaigns', 'extension_references');
    }
};
