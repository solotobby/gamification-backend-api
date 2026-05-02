<?php

use App\Database\Migrations\BaseMigration;
use Illuminate\Database\Schema\Blueprint;

return new class extends BaseMigration {

    public function up(): void
    {
        $this->addColumn('staff', 'recipient_code', function (Blueprint $table) {
            $table->string('recipient_code')->nullable();
        });
    }

    public function down(): void
    {
        $this->dropColumn('staff', 'recipient_code');
    }
};
