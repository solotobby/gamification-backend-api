<?php

use App\Database\Migrations\BaseMigration;
use Illuminate\Database\Schema\Blueprint;

return new class extends BaseMigration {

    public function up(): void
    {
        $this->addColumn('campaign_workers', 'proof_url', function (Blueprint $table) {
            $table->string('proof_url')->nullable();
        });
    }

    public function down(): void
    {
        $this->dropColumn('campaign_workers', 'proof_url');
    }
};
