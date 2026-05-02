<?php

use App\Database\Migrations\BaseMigration;
use Illuminate\Database\Schema\Blueprint;

return new class extends BaseMigration {

    public function up(): void
    {
        $this->addColumn('users', 'country', function (Blueprint $table) {
            $table->string('country')->nullable();
        });
    }

    public function down(): void
    {
        $this->dropColumn('users', 'country');
    }
};
