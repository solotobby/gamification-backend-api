<?php

use App\Database\Migrations\BaseMigration;
use Illuminate\Database\Schema\Blueprint;

return new class extends BaseMigration
{
    public function up(): void
    {
        $this->addColumn('feedback_replies', 'text_message', function (Blueprint $table) {
            $table->text('text_message')
                ->nullable()
                ->after('message');
        });
    }

    public function down(): void
    {
        $this->dropColumn('feedback_replies', 'text_message');
    }
};
