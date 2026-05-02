<?php

use App\Database\Migrations\BaseMigration;
use Illuminate\Database\Schema\Blueprint;

return new class extends BaseMigration
{
    public function up(): void
    {
        /*
        |--------------------------------------------------------------------------
        | SPIN MODULE
        |--------------------------------------------------------------------------
        */
        $this->create('spin_scores', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('score');
            $table->string('prize');
            $table->boolean('is_paid')->default(false);
            $table->boolean('is_high_prize')->default(false);
            $table->timestamps();
        });

        $this->create('spin_trackers', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->integer('total_spins')->default(0);
            $table->integer('total_payout')->default(0);
            $table->timestamps();
        });

        $this->create('spin_params', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('total_spins_allowed');
            $table->bigInteger('total_payouts_allowed');
            $table->timestamps();
        });

        /*
        |--------------------------------------------------------------------------
        | TASKS
        |--------------------------------------------------------------------------
        */
        $this->create('tasks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('title');
            $table->longText('description');
            $table->string('status')->default('TO-DO');
            $table->timestamps();
        });

        /*
        |--------------------------------------------------------------------------
        | POSTS
        |--------------------------------------------------------------------------
        */
        $this->create('post_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        $this->create('posts', function (Blueprint $table) {
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

        $this->create('post_views', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('post_id');
            $table->string('ip_address');
            $table->string('visited_at');
            $table->timestamps();
        });

        /*
        |--------------------------------------------------------------------------
        | SKILLS
        |--------------------------------------------------------------------------
        */
        $this->create('skills', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('point')->nullable();
            $table->boolean('isActive')->default(true);
            $table->timestamps();
        });

        $this->create('skill_assets', function (Blueprint $table) {
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
        });

        $this->create('skill_user', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('skill_asset_id');
            $table->unsignedBigInteger('user_id');
            $table->timestamps();
        });

        /*
        |--------------------------------------------------------------------------
        | EXPORT / WEBHOOKS
        |--------------------------------------------------------------------------
        */
        $this->create('export_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('status')->default('pending');
            $table->string('email');
            $table->text('file_path')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        $this->create('webhooks', function (Blueprint $table) {
            $table->id();
            $table->string('provider')->nullable();
            $table->string('event')->nullable();
            $table->json('payload')->nullable();
            $table->string('status')->default('pending');
            $table->text('message')->nullable();
            $table->timestamps();
        });

        /*
        |--------------------------------------------------------------------------
        | PASSWORD RESET
        |--------------------------------------------------------------------------
        */
        $this->create('password_resets', function (Blueprint $table) {
            $table->id();
            $table->string('email')->index();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        /*
        |--------------------------------------------------------------------------
        | MASS EMAIL
        |--------------------------------------------------------------------------
        */
        $this->create('mass_email_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('channel')->default('email');
            $table->string('subject')->nullable();
            $table->text('message')->nullable();
            $table->text('sms_message')->nullable();
            $table->string('audience_type');
            $table->integer('total_recipients')->default(0);
            $table->foreignId('sent_by')->constrained('users');
            $table->timestamps();
        });

        $this->create('mass_email_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('mass_email_campaigns')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('email');
            $table->string('status')->default('pending');
            $table->string('message_id')->nullable();
            $table->timestamps();

            $table->index(['campaign_id', 'status']);
        });

        /*
        |--------------------------------------------------------------------------
        | JOBS
        |--------------------------------------------------------------------------
        */
        $this->create('job_listings', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description');
            $table->string('type');
            $table->string('tier')->default('free');
            $table->string('location');
            $table->boolean('remote_allowed')->default(false);
            $table->string('currency', 3)->default('NGN');
            $table->string('company_name');
            $table->boolean('is_active')->default(true);
            $table->foreignId('posted_by')->constrained('users');
            $table->timestamps();
        });

        $this->create('job_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_listing_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('status')->default('pending');
            $table->timestamps();
        });

        /*
        |--------------------------------------------------------------------------
        | ALTER TABLES (SAFE BASEMIGRATION STYLE)
        |--------------------------------------------------------------------------
        */

        $this->addColumn('wallets', 'base_currency_balance', function (Blueprint $table) {
            $table->string('base_currency_balance')->default('0.00');
        });

        $this->addColumn('wallets', 'base_currency_set', function (Blueprint $table) {
            $table->boolean('base_currency_set')->default(false);
        });

        $this->addColumn('wallets', 'point', function (Blueprint $table) {
            $table->string('point')->default('0.0');
        });

        $this->addColumn('profiles', 'pathway', function (Blueprint $table) {
            $table->string('pathway')->nullable();
        });

        $this->addColumn('campaigns', 'approval_time', function (Blueprint $table) {
            $table->integer('approval_time')->default(24);
        });

        /*
        |--------------------------------------------------------------------------
        | INDEXES (SAFE)
        |--------------------------------------------------------------------------
        */
        $this->addIndex('mass_email_logs', 'campaign_status_index', function (Blueprint $table) {
            $table->index(['campaign_id', 'status'], 'campaign_status_index');
        });
    }

    public function down(): void
    {
        $this->drop('job_applications');
        $this->drop('job_listings');
        $this->drop('mass_email_logs');
        $this->drop('mass_email_campaigns');
        $this->drop('webhooks');
        $this->drop('export_jobs');
        $this->drop('skill_user');
        $this->drop('skill_assets');
        $this->drop('skills');
        $this->drop('posts');
        $this->drop('post_categories');
        $this->drop('tasks');
        $this->drop('spin_params');
        $this->drop('spin_trackers');
        $this->drop('spin_scores');
    }
};
