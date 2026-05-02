<?php

use App\Database\Migrations\BaseMigration;
use Illuminate\Database\Schema\Blueprint;

return new class extends BaseMigration {

    public function up(): void
    {
        $this->create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('post_title');
            $table->string('post_link');
            $table->string('campaign_type');
            $table->string('campaign_subcategory');
            $table->string('number_of_staff');
            $table->string('campaign_amount');
            $table->longText('description');
            $table->longText('proof');
            $table->string('total_amount')->default('0.00');
            $table->boolean('is_paid')->default(false);
            $table->string('approved')->default('Pending');
            $table->string('status')->default('Offline');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        $this->drop('campaigns');
    }
};
