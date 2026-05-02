<?php

use App\Database\Migrations\BaseMigration;
use Illuminate\Database\Schema\Blueprint;

return new class extends BaseMigration
{
    public function up(): void
    {
        $this->addColumn('users', 'fcm_token', function (Blueprint $table) {
            $table->string('fcm_token')
                ->nullable()
                ->after('email');
        });
    }

    public function down(): void
    {
        $this->dropColumn('users', 'fcm_token');
    }
};
