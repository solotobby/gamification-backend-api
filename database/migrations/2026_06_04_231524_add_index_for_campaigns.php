<?php

use App\Database\Migrations\BaseMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends BaseMigration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->addIndex('campaigns', 'campaigns_user_status_idx', function (Blueprint $table) {
            $table->index(
                ['user_id', 'status'],
                'campaigns_user_status_idx'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->dropIndex('campaigns', 'campaigns_user_status_idx');
    }
};
