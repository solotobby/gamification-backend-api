<?php

use App\Database\Migrations\BaseMigration;
use Illuminate\Database\Schema\Blueprint;

class AddDepusteFields extends BaseMigration
{
    public function up()
    {
        $this->table('campaign_workers', function (Blueprint $table) {
            if (!$this->columnExists('campaign_workers', 'is_dispute')) {
                $table->boolean('is_dispute')->default(false);
            }

            if (!$this->columnExists('campaign_workers', 'is_dispute_resolved')) {
                $table->boolean('is_dispute_resolved')->default(false);
            }
        });
    }

    public function down()
    {
        $this->table('campaign_workers', function (Blueprint $table) {
            $this->dropColumn('campaign_workers', 'is_dispute');
            $this->dropColumn('campaign_workers', 'is_dispute_resolved');
        });
    }
}
