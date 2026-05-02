<?php

use App\Database\Migrations\BaseMigration;
use Illuminate\Database\Schema\Blueprint;

class AddColumnToUsdverifieds extends BaseMigration
{
    public function up()
    {
        $this->table('usdverifieds', function (Blueprint $table) {
            if (!$this->columnExists('usdverifieds', 'is_paid')) {
                $table->boolean('is_paid')->default(false);
            }

            if (!$this->columnExists('usdverifieds', 'amount')) {
                $table->string('amount')->nullable();
            }
        });
    }

    public function down()
    {
        $this->table('usdverifieds', function (Blueprint $table) {
            $this->dropColumn('usdverifieds', 'is_paid');
            $this->dropColumn('usdverifieds', 'amount');
        });
    }
}
