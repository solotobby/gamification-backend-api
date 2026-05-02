<?php

use App\Database\Migrations\BaseMigration;
use Illuminate\Database\Schema\Blueprint;

return new class extends BaseMigration {

    public function up(): void
    {
        $this->addColumn('feedback', 'status', function (Blueprint $table) {
            $table->boolean('status')->default(false);
        });
    }

    public function down(): void
    {
        $this->dropColumn('feedback', 'status');
    }
};
