<?php

use App\Database\Migrations\BaseMigration;
use Illuminate\Database\Schema\Blueprint;

class AddBannerInfo extends BaseMigration
{
    public function up()
    {
        $this->table('banners', function (Blueprint $table) {
            if (!$this->columnExists('banners', 'budget')) {
                $table->string('budget')->nullable();
            }

            if (!$this->columnExists('banners', 'click_count')) {
                $table->string('click_count')->nullable();
            }

            if (!$this->columnExists('banners', 'impression_count')) {
                $table->string('impression_count')->nullable();
            }
        });
    }

    public function down()
    {
        $this->table('banners', function (Blueprint $table) {
            $this->dropColumn('banners', 'budget');
            $this->dropColumn('banners', 'click_count');
            $this->dropColumn('banners', 'impression_count');
        });
    }
}
