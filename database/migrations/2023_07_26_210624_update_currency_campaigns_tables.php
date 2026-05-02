<?php

use App\Database\Migrations\BaseMigration;
use Illuminate\Database\Schema\Blueprint;

class UpdateCurrencyCampaignsTables extends BaseMigration
{
    public function up()
    {
        $this->table('campaigns', function (Blueprint $table) {
            if (!$this->columnExists('campaigns', 'currency')) {
                $table->string('currency')->default('NGN');
            }
        });
    }

    public function down()
    {
        $this->table('campaigns', function (Blueprint $table) {
            $this->dropColumn('campaigns', 'currency');
        });
    }
}
