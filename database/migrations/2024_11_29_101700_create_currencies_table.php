<?php

use App\Database\Migrations\BaseMigration;
use Illuminate\Database\Schema\Blueprint;

return new class extends BaseMigration
{
    public function up(): void
    {
        $this->create('currencies', function (Blueprint $table) {
            $table->id();
            $table->string('code');
            $table->string('country');
            $table->string('upgrade_fee')->nullable();
            $table->string('allow_upload')->nullable();
            $table->string('priotize')->nullable();
            $table->string('referral_commission')->nullable();
            $table->string('min_upgrade_amount')->nullable();
            $table->string('base_rate')->nullable();
            $table->boolean('is_active');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        $this->drop('currencies');
    }
};
