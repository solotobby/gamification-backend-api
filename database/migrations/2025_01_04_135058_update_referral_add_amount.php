<?php

use App\Database\Migrations\BaseMigration;
use Illuminate\Database\Schema\Blueprint;

return new class extends BaseMigration
{
    public function up(): void
    {
        // change column type safely (only if column exists)
        if ($this->columnExists('referral', 'referee_id')) {
            $this->table('referral', function (Blueprint $table) {
                $table->string('referee_id')->change();
            });
        }

        $this->addColumn('referral', 'amount', function (Blueprint $table) {
            $table->string('amount')->nullable();
        });
    }

    public function down(): void
    {
        $this->table('referral', function (Blueprint $table) {
            // revert type only if column exists
            if ($this->columnExists('referral', 'referee_id')) {
                $table->unsignedBigInteger('referee_id')->change();
            }
        });

        $this->dropColumn('referral', 'amount');
    }
};
