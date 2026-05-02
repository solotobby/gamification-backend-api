<?php

use App\Database\Migrations\BaseMigration;
use Illuminate\Database\Schema\Blueprint;

return new class extends BaseMigration
{
    public function up(): void
    {
        $this->addColumn('feedback_replies', 'is_image', function (Blueprint $table) {
            $table->boolean('is_image')
                ->default(false)
                ->after('message');
        });

        $this->addColumn('feedback_replies', 'image_url', function (Blueprint $table) {
            $table->string('image_url')
                ->nullable()
                ->after('is_image');
        });
    }

    public function down(): void
    {
        $this->dropColumn('feedback_replies', 'image_url');
        $this->dropColumn('feedback_replies', 'is_image');
    }
};
