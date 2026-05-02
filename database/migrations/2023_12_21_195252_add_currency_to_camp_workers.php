<?php

use App\Database\Migrations\BaseMigration;
use Illuminate\Database\Schema\Blueprint;

class AddCurrencyToCampWorkers extends BaseMigration
{
    public function up()
    {
        $this->table('campaign_workers', function (Blueprint $table) {
            if (!$this->columnExists('campaign_workers', 'currency')) {
                $table->string('currency')->nullable();
            }
        });
    }

    public function down()
    {
        $this->table('campaign_workers', function (Blueprint $table) {
            $this->dropColumn('campaign_workers', 'currency');
        });
    }
}
