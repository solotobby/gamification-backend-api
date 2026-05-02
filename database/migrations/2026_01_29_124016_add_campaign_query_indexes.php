<?php

use App\Database\Migrations\BaseMigration;
use Illuminate\Database\Schema\Blueprint;

return new class extends BaseMigration
{
    public function up(): void
    {
        // campaign_workers index
        $this->addIndex('campaign_workers', 'cw_user_campaign_status_idx', function (Blueprint $table) {
            $table->index(
                ['user_id', 'campaign_id', 'status'],
                'cw_user_campaign_status_idx'
            );
        });

        // campaigns indexes
        $this->addIndex('campaigns', 'campaigns_status_completed_idx', function (Blueprint $table) {
            $table->index(
                ['status', 'is_completed'],
                'campaigns_status_completed_idx'
            );
        });

        $this->addIndex('campaigns', 'campaigns_type_idx', function (Blueprint $table) {
            $table->index(
                ['campaign_type'],
                'campaigns_type_idx'
            );
        });

        $this->addIndex('campaigns', 'campaigns_priority_sort_idx', function (Blueprint $table) {
            $table->index(
                ['job_id', 'approved', 'created_at'],
                'campaigns_priority_sort_idx'
            );
        });
    }

    public function down(): void
    {
        $this->dropIndex('campaign_workers', 'cw_user_campaign_status_idx');

        $this->dropIndex('campaigns', 'campaigns_status_completed_idx');
        $this->dropIndex('campaigns', 'campaigns_type_idx');
        $this->dropIndex('campaigns', 'campaigns_priority_sort_idx');
    }
};
