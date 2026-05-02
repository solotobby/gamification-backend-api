<?php

use App\Database\Migrations\BaseMigration;
use Illuminate\Database\Schema\Blueprint;

class CreateDisputedJobsTable extends BaseMigration
{
    public function up()
    {
        $this->create('disputed_jobs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('campaign_worker_id');
            $table->unsignedBigInteger('campaign_id');
            $table->unsignedBigInteger('user_id');
            $table->longText('reason');
            $table->longText('response')->nullable();
            $table->string('url')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        $this->drop('disputed_jobs');
    }
}
