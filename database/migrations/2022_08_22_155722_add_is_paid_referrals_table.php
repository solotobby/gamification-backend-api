<?php

use App\Database\Migrations\BaseMigration;
use Illuminate\Database\Schema\Blueprint;

return new class extends BaseMigration {

    public function up(): void
    {
        $this->addColumn('referral', 'is_paid', function (Blueprint $table) {
            $table->boolean('is_paid')->default(false);
        });
    }

    public function down(): void
    {
        $this->dropColumn('referral', 'is_paid');
    }
};
