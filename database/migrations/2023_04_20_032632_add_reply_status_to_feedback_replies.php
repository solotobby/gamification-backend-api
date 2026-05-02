<?php

use App\Database\Migrations\BaseMigration;
use Illuminate\Database\Schema\Blueprint;

return new class extends BaseMigration {

    public function up(): void
    {
        $this->addColumn('feedback_replies', 'status', function (Blueprint $table) {
            $table->boolean('status')->default(true);
        });
    }

    public function down(): void
    {
        $this->dropColumn('feedback_replies', 'status');
    }
};
