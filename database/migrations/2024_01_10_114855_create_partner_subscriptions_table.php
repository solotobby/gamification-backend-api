<?php

use App\Database\Migrations\BaseMigration;
use Illuminate\Database\Schema\Blueprint;

class CreatePartnerSubscriptionsTable extends BaseMigration
{
    public function up()
    {
        $this->create('partner_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('plan_code');
            $table->string('subscription_code');
            $table->string('amount');
            $table->string('commission');
            $table->string('affiliate_commission')->nullable();
            $table->unsignedBigInteger('affiliate_referral_id')->nullable();
            $table->string('payment_plan')->nullable();
            $table->string('numberOfSubscribers')->nullable();
            $table->string('nextPayment')->nullable();
            $table->string('product')->nullable();
            $table->string('partner');
            $table->boolean('settlement_status')->default(false);
            $table->timestamps();
        });
    }

    public function down()
    {
        $this->drop('partner_subscriptions');
    }
}
