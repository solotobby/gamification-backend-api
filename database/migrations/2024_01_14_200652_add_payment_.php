<?php

use App\Database\Migrations\BaseMigration;
use Illuminate\Database\Schema\Blueprint;

class AddPayment extends BaseMigration
{
    public function up()
    {
        $this->table('partner_subscriptions', function (Blueprint $table) {
            if (!$this->columnExists('partner_subscriptions', 'is_paid')) {
                $table->boolean('is_paid')->default(false);
            }
        });
    }

    public function down()
    {
        $this->table('partner_subscriptions', function (Blueprint $table) {
            $this->dropColumn('partner_subscriptions', 'is_paid');
        });
    }
}
