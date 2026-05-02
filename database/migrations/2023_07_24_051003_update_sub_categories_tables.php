<?php

use App\Database\Migrations\BaseMigration;
use Illuminate\Database\Schema\Blueprint;

class UpdateSubCategoriesTables extends BaseMigration
{
    public function up()
    {
        $this->table('sub_categories', function (Blueprint $table) {
            if (!$this->columnExists('sub_categories', 'usd')) {
                $table->string('usd')->nullable();
            }
        });
    }

    public function down()
    {
        $this->table('sub_categories', function (Blueprint $table) {
            $this->dropColumn('sub_categories', 'usd');
        });
    }
}
