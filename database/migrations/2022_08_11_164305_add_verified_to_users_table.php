<?php

use App\Database\Migrations\BaseMigration;
use Illuminate\Database\Schema\Blueprint;

return new class extends BaseMigration {

    public function up(): void
    {
        $this->addColumn('users', 'is_verified', function (Blueprint $table) {
            $table->boolean('is_verified')->default(false);
        });
    }

    public function down(): void
    {
        $this->dropColumn('users', 'is_verified');
    }
};
