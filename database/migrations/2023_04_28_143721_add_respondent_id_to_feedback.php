<?php

use App\Database\Migrations\BaseMigration;
use Illuminate\Database\Schema\Blueprint;

return new class extends BaseMigration {

    public function up(): void
    {
        $this->addColumn('feedback', 'respondent_id', function (Blueprint $table) {
            $table->unsignedBigInteger('respondent_id')->nullable();
        });
    }

    public function down(): void
    {
        $this->dropColumn('feedback', 'respondent_id');
    }
};
