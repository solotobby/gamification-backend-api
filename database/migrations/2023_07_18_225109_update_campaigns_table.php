<?php

use App\Database\Migrations\BaseMigration;
use Illuminate\Database\Schema\Blueprint;

class UpdateCampaignsTable extends BaseMigration
{
    public function up()
    {
        $this->table('campaigns', function (Blueprint $table) {
            if (!$this->columnExists('campaigns', 'is_completed')) {
                $table->boolean('is_completed')->default(false);
            }
        });
    }

    public function down()
    {
        $this->table('campaigns', function (Blueprint $table) {
            $this->dropColumn('campaigns', 'is_completed');
        });
    }
}
