<?php

use App\Database\Migrations\BaseMigration;
use Illuminate\Database\Schema\Blueprint;

class UpdateWithrawalsTable extends BaseMigration
{
    public function up()
    {
        $this->table('withrawals', function (Blueprint $table) {
            if (!$this->columnExists('withrawals', 'content')) {
                $table->text('content')->nullable();
            }

            if (!$this->columnExists('withrawals', 'paypal_email')) {
                $table->string('paypal_email')->nullable();
            }

            if (!$this->columnExists('withrawals', 'is_usd')) {
                $table->boolean('is_usd')->default(false);
            }
        });
    }

    public function down()
    {
        $this->table('withrawals', function (Blueprint $table) {
            $this->dropColumn('withrawals', 'content');
            $this->dropColumn('withrawals', 'paypal_email');
            $this->dropColumn('withrawals', 'is_usd');
        });
    }
}
