<?php

use App\Database\Migrations\BaseMigration;
use Illuminate\Database\Schema\Blueprint;

return new class extends BaseMigration {

    public function up(): void
    {
        $this->addColumn('users', 'referral_code', function (Blueprint $table) {
            $table->string('referral_code')->nullable();
        });
    }

    public function down(): void
    {
        $this->dropColumn('users', 'referral_code');
    }
};
