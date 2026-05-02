<?php

use App\Database\Migrations\BaseMigration;
use Illuminate\Database\Schema\Blueprint;

class AddEndOfAdDateTable extends BaseMigration
{
    public function up()
    {
        $this->table('banners', function (Blueprint $table) {
            if (!$this->columnExists('banners', 'banner_end_date')) {
                $table->string('banner_end_date')->nullable();
            }

            if (!$this->columnExists('banners', 'live_state')) {
                $table->string('live_state')->nullable();
            }
        });
    }

    public function down()
    {
        $this->table('banners', function (Blueprint $table) {
            $this->dropColumn('banners', 'banner_end_date');
            $this->dropColumn('banners', 'live_state');
        });
    }
}
