<?php

use App\Database\Migrations\BaseMigration;
use Illuminate\Database\Schema\Blueprint;

class AddPendingCompletedToCampaigns extends BaseMigration
{
    public function up()
    {
        $this->table('campaigns', function (Blueprint $table) {
            if (!$this->columnExists('campaigns', 'pending_count')) {
                $table->bigInteger('pending_count');
            }

            if (!$this->columnExists('campaigns', 'completed_count')) {
                $table->bigInteger('completed_count');
            }

            if (!$this->columnExists('campaigns', 'impressions')) {
                $table->bigInteger('impressions');
            }
        });
    }

    public function down()
    {
        $this->table('campaigns', function (Blueprint $table) {
            $this->dropColumn('campaigns', 'pending_count');
            $this->dropColumn('campaigns', 'completed_count');
            $this->dropColumn('campaigns', 'impressions');
        });
    }
}
