<?php

use App\Database\Migrations\BaseMigration;
use Illuminate\Database\Schema\Blueprint;

return new class extends BaseMigration {

    public function up(): void
    {
        $this->addColumn('users', 'phone', function (Blueprint $table) {
            $table->string('phone')->nullable();
        });
    }

    public function down(): void
    {
        $this->dropColumn('users', 'phone');
    }
};
