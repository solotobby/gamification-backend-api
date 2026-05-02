<?php

use App\Database\Migrations\BaseMigration;
use Illuminate\Database\Schema\Blueprint;

return new class extends BaseMigration
{
    public function up(): void
    {
        $this->addColumn('users', 'auth_device', function (Blueprint $table) {
            $table->string('auth_device')->default('web');
        });

        $this->addColumn('users', 'streak_redeemed', function (Blueprint $table) {
            $table->boolean('streak_redeemed')->default(false);
        });
    }

    public function down(): void
    {
        $this->dropColumn('users', 'streak_redeemed');
        $this->dropColumn('users', 'auth_device');
    }
};
