<?php

use App\Database\Migrations\BaseMigration;
use Illuminate\Database\Schema\Blueprint;

return new class extends BaseMigration
{
    public function up(): void
    {
        $this->addColumn('users', 'email_preference', function (Blueprint $table) {
        $table->boolean('is_business')->default(false);
        $table->boolean('email_preference')
                ->default(true);
        });
    }

    public function down(): void
    {
        $this->dropColumn('users', 'email_preference');
        $this->dropColumn('users', 'is_business');
    }
};
