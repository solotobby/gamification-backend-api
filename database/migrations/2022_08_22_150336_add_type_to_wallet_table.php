<?php

use App\Database\Migrations\BaseMigration;
use Illuminate\Database\Schema\Blueprint;

return new class extends BaseMigration {

    public function up(): void
    {
        $this->addColumn('wallets', 'user_type', function (Blueprint $table) {
            $table->string('user_type')->default('regular');
        });
    }

    public function down(): void
    {
        $this->dropColumn('wallets', 'user_type');
    }
};
