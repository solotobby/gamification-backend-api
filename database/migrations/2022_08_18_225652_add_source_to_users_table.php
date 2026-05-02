<?php

use App\Database\Migrations\BaseMigration;
use Illuminate\Database\Schema\Blueprint;

return new class extends BaseMigration {

    public function up(): void
    {
        $this->addColumn('users', 'source', function (Blueprint $table) {
            $table->string('source')->nullable();
        });
    }

    public function down(): void
    {
        $this->dropColumn('users', 'source');
    }
};
