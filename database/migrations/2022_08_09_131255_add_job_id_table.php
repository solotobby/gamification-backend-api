<?php

use App\Database\Migrations\BaseMigration;
use Illuminate\Database\Schema\Blueprint;

return new class extends BaseMigration {

    public function up(): void
    {
        $this->addColumn('campaigns', 'job_id', function (Blueprint $table) {
            $table->string('job_id')->nullable();
        });
    }

    public function down(): void
    {
        $this->dropColumn('campaigns', 'job_id');
    }
};
