<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class MergedDatabaseMigration extends Migration
{
    public function up()
    {
        // Spin-related tables
        Schema::create('spin_scores', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('score');
            $table->string('prize');
            $table->boolean('is_paid')->default(false);
            $table->boolean('is_high_prize')->default(false);
            $table->timestamps();
        });

        Schema::create('spin_trackers', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->integer('total_spins')->default(0);
            $table->integer('total_payout')->default(0);
            $table->timestamps();
        });

        Schema::create('spin_params', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('total_spins_allowed');
            $table->bigInteger('total_payouts_allowed');
            $table->timestamps();
        });

        // Tasks and Posts
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('title');
            $table->longText('description');
            $table->string('status')->default('TO-DO');
            $table->timestamps();
        });

        Schema::create('post_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('username')->nullable();
            $table->unsignedBigInteger('category_id');
            $table->string('category_name')->nullable();
            $table->string('slug');
            $table->string('title');
            $table->longText('body');
            $table->timestamps();
        });

        Schema::create('post_views', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('post_id');
            $table->string('ip_address');
            $table->string('visited_at');
            $table->timestamps();
        });

        // Professional Jobs and Skills
        Schema::create('professional_jobs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('professional_category_id');
            $table->unsignedBigInteger('professional_sub_category_id');
            $table->string('title');
            $table->string('slug');
            $table->longText('description');
            $table->string('views')->default('0');
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('professional_category_id')->references('id')->on('professionals_categories')->cascadeOnDelete();
            $table->foreign('professional_sub_category_id')->references('id')->on('professionals_sub_categories')->cascadeOnDelete();
        });

        Schema::create('skills', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('point')->nullable();
            $table->boolean('isActive')->default(true);
            $table->timestamps();
        });

        Schema::create('skill_assets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('skill_id');
            $table->string('title');
            $table->text('description');
            $table->string('profeciency_level');
            $table->string('year_experience');
            $table->string('location');
            $table->string('availability');
            $table->string('max_price')->default(0.0);
            $table->string('min_price')->default(0.0);
            $table->string('status')->default('active');
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('skill_id')->references('id')->on('skills')->cascadeOnDelete();
        });

        Schema::create('skill_user', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('skill_asset_id');
            $table->unsignedBigInteger('user_id');
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('skill_asset_id')->references('id')->on('skill_assets')->cascadeOnDelete();
        });

        // Export Jobs and Webhooks
        Schema::create('export_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('status')->default('pending');
            $table->string('email');
            $table->text('file_path')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('webhooks', function (Blueprint $table) {
            $table->id();
            $table->string('provider')->nullable();
            $table->string('event')->nullable();
            $table->json('payload')->nullable();
            $table->string('status')->default('pending');
            $table->text('message')->nullable();
            $table->timestamps();
        });

        // Password Resets
        if (!Schema::hasTable('password_resets')) {
            Schema::create('password_resets', function (Blueprint $table) {
                $table->id();
                $table->string('email')->index();
                $table->string('token');
                $table->timestamp('created_at')->nullable();
            });
        }

        // Mass Email Campaigns
        Schema::create('mass_email_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('channel')->default('email');
            $table->string('subject')->nullable();
            $table->text('message')->nullable();
            $table->text('sms_message')->nullable();
            $table->string('audience_type');
            $table->integer('days_filter')->nullable();
            $table->string('country_filter')->nullable();
            $table->integer('total_recipients')->default(0);
            $table->integer('sms_recipients')->default(0);
            $table->integer('delivered')->default(0);
            $table->integer('sms_delivered')->default(0);
            $table->integer('opened')->default(0);
            $table->integer('clicks')->default(0);
            $table->integer('bounced')->default(0);
            $table->integer('failed')->default(0);
            $table->integer('sms_failed')->default(0);
            $table->foreignId('sent_by')->constrained('users');
            $table->timestamps();
        });

        Schema::create('mass_email_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('mass_email_campaigns')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->string('channel')->default('email');
            $table->enum('status', ['pending', 'sent', 'delivered', 'opened', 'bounced', 'failed'])->default('pending');
            $table->string('message_id')->nullable()->unique();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('clicked_at')->nullable();
            $table->timestamp('bounced_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['campaign_id', 'status']);
            $table->index('message_id');
        });

        // Job Listings
        Schema::create('job_listings', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description');
            $table->text('requirements')->nullable();
            $table->text('responsibilities')->nullable();
            $table->text('benefits')->nullable();
            $table->enum('type', ['fulltime', 'parttime', 'contract', 'internship', 'gig']);
            $table->enum('tier', ['free', 'premium'])->default('free');
            $table->string('location');
            $table->boolean('remote_allowed')->default(false);
            $table->decimal('salary_min', 10, 2)->nullable();
            $table->decimal('salary_max', 10, 2)->nullable();
            $table->string('currency', 3)->default('NGN');
            $table->string('company_name');
            $table->string('company_logo')->nullable();
            $table->text('company_description')->nullable();
            $table->string('company_website')->nullable();
            $table->integer('views_count')->default(0);
            $table->integer('applications_count')->default(0);
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('posted_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'expires_at']);
            $table->index('type');
            $table->index('tier');
        });

        Schema::create('job_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_listing_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->text('cover_letter')->nullable();
            $table->string('resume_path')->nullable();
            $table->enum('status', ['pending', 'reviewing', 'shortlisted', 'rejected', 'accepted'])->default('pending');
            $table->timestamps();

            $table->unique(['job_listing_id', 'user_id']);
        });

        // Modify existing tables
        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->string('balance')->after('amount')->nullable();
            $table->index(['user_id', 'status', 'type', 'created_at'], 'idx_user_status_type_created');
            $table->index(['user_id', 'tx_type', 'type'], 'idx_payment_user_tx_type');
            $table->index(['user_id', 'created_at'], 'idx_payment_user_created');
            $table->index(['tx_type', 'type', 'created_at'], 'idx_payment_tx_type_created');
            $table->index('type', 'idx_payment_type');
            $table->index(['user_id', 'tx_type', 'user_type', 'status', 'created_at'], 'idx_payment_trans_filter');
        });

        Schema::table('wallets', function (Blueprint $table) {

            $table->string('base_currency_balance')->default('0.00');
            $table->boolean('base_currency_set')->default(false);
            $table->string('point')->default('0.0');
            $table->index('base_currency', 'idx_wallets_currency');
        });


        Schema::table('profiles', function (Blueprint $table) {
            $table->string('pathway')->nullable();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->date('verified_at')->nullable();
            $table->date('email_verification_attempted_at')->nullable();
            $table->boolean('is_business')->default(false);
            $table->string('username')->nullable();
            $table->string('api_key', 80)->unique()->nullable();

            // Indexes
            $table->index(['role', 'is_verified', 'verified_at'], 'users_role_verified_index');
            $table->index(['role', 'email_verified_at'], 'users_role_email_verified_index');
            $table->index(['role', 'created_at'], 'users_role_created_index');
            $table->index(['role', 'country'], 'users_role_country_index');
            $table->index('email', 'users_email_index');
            $table->index('phone', 'users_phone_index');
            $table->index(['is_verified', 'verified_at'], 'idx_verified_verifiedat');
            $table->index('created_at', 'idx_users_created');
            $table->index(['created_at', 'is_verified'], 'idx_users_created_verified');
            $table->index(['created_at', 'source'], 'idx_users_created_source');
            $table->index(['is_verified', 'created_at', 'source'], 'idx_users_verified_created_source');
            $table->index('source', 'idx_users_source');
            $table->index('country', 'idx_users_country');
            $table->index('age_range', 'idx_users_age_range');
            $table->index('role');
            $table->index('is_verified');
        });

        Schema::table('activity_logs', function (Blueprint $table) {
            $table->index(['created_at', 'activity_type'], 'idx_activity_created_type');
            $table->index('activity_type', 'idx_activity_type');
        });

        Schema::table('statistics', function (Blueprint $table) {
            $table->index(['created_at', 'type'], 'idx_stats_created_type');
            $table->index(['date', 'type'], 'idx_stats_date_type');
        });

        Schema::table('campaigns', function (Blueprint $table) {
            $table->integer('approval_time')->default(24);
            $table->timestamp('flagged_at')->nullable();
            $table->text('flagged_reason')->nullable();
            $table->boolean('flagging_resolved')->default(false);
            $table->string('expected_result_image')->nullable();
            $table->index('status');
            $table->index('created_at');
        });

        Schema::table('campaign_workers', function (Blueprint $table) {
            $table->timestamp('denied_at')->nullable();
            $table->boolean('slot_released')->default(false);
            $table->index('status');
            $table->index('created_at');
        });

        if (Schema::hasTable('login_points')) {
            Schema::table('login_points', function (Blueprint $table) {
                $table->index('is_redeemed');
                $table->index('created_at');
            });
        }
    }

    public function down()
    {
        // Drop created tables
        Schema::dropIfExists('job_applications');
        Schema::dropIfExists('job_listings');
        Schema::dropIfExists('mass_email_logs');
        Schema::dropIfExists('mass_email_campaigns');
        Schema::dropIfExists('password_resets');
        Schema::dropIfExists('webhooks');
        Schema::dropIfExists('export_jobs');
        Schema::dropIfExists('skill_user');
        Schema::dropIfExists('skill_assets');
        Schema::dropIfExists('skills');
        Schema::dropIfExists('professional_jobs');
        Schema::dropIfExists('post_views');
        Schema::dropIfExists('posts');
        Schema::dropIfExists('post_categories');
        Schema::dropIfExists('tasks');
        Schema::dropIfExists('spin_params');
        Schema::dropIfExists('spin_trackers');
        Schema::dropIfExists('spin_scores');

        // Revert table modifications
        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->dropColumn('balance');
            $table->dropIndex('idx_user_status_type_created');
            $table->dropIndex('idx_payment_user_tx_type');
            $table->dropIndex('idx_payment_user_created');
            $table->dropIndex('idx_payment_tx_type_created');
            $table->dropIndex('idx_payment_type');
            $table->dropIndex('idx_payment_trans_filter');
        });

        Schema::table('wallets', function (Blueprint $table) {
            $table->dropColumn(['base_currency_balance', 'base_currency_set', 'point']);
            $table->dropIndex('idx_wallets_currency');
        });

        Schema::table('profiles', function (Blueprint $table) {
            $table->dropColumn('pathway');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['verified_at', 'email_verification_attempted_at', 'is_business', 'username', 'api_key']);
            $table->dropIndex('users_role_verified_index');
            $table->dropIndex('users_role_email_verified_index');
            $table->dropIndex('users_role_created_index');
            $table->dropIndex('users_role_country_index');
            $table->dropIndex('users_email_index');
            $table->dropIndex('users_phone_index');
            $table->dropIndex('idx_verified_verifiedat');
            $table->dropIndex('idx_users_created');
            $table->dropIndex('idx_users_created_verified');
            $table->dropIndex('idx_users_created_source');
            $table->dropIndex('idx_users_verified_created_source');
            $table->dropIndex('idx_users_source');
            $table->dropIndex('idx_users_country');
            $table->dropIndex('idx_users_age_range');
            $table->dropIndex(['role']);
            $table->dropIndex(['is_verified']);
        });

        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropIndex('idx_activity_created_type');
            $table->dropIndex('idx_activity_type');
        });

        Schema::table('statistics', function (Blueprint $table) {
            $table->dropIndex('idx_stats_created_type');
            $table->dropIndex('idx_stats_date_type');
        });

        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropColumn(['approval_time', 'flagged_at', 'flagged_reason', 'flagging_resolved', 'expected_result_image']);
            $table->dropIndex(['status']);
            $table->dropIndex(['created_at']);
        });

        Schema::table('campaign_workers', function (Blueprint $table) {
            $table->dropColumn(['denied_at', 'slot_released']);
            $table->dropIndex(['status']);
            $table->dropIndex(['created_at']);
        });

        if (Schema::hasTable('login_points')) {
            Schema::table('login_points', function (Blueprint $table) {
                $table->dropIndex(['is_redeemed']);
                $table->dropIndex(['created_at']);
            });
        }
    }
}
