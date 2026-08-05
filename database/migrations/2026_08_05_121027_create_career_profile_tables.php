<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('universities', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('career_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('slug')->unique();
            $table->string('professional_title')->nullable();
            $table->string('headline')->nullable();
            $table->text('summary')->nullable();
            $table->string('professional_level')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('country')->nullable();
            $table->unsignedTinyInteger('profile_completeness')->default(0);
            $table->unsignedTinyInteger('talent_score')->default(0);
            $table->string('photo_path')->nullable();
            $table->string('cv_file_path')->nullable();
            $table->boolean('is_public')->default(false);
            $table->timestamps();
        });

        Schema::create('career_availabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('career_profile_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->timestamps();
            $table->unique(['career_profile_id', 'type']);
        });

        Schema::create('career_skills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('skill_id')->constrained('skills')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'skill_id']);
        });

        Schema::create('experiences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('employer');
            $table->string('position');
            $table->string('employment_type')->nullable();
            $table->string('location')->nullable();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->text('responsibilities')->nullable();
            $table->text('achievements')->nullable();
            $table->timestamps();
        });

        Schema::create('educations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('university_id')->nullable()->constrained();
            $table->string('institution');
            $table->string('qualification');
            $table->string('course')->nullable();
            $table->unsignedSmallInteger('start_year');
            $table->unsignedSmallInteger('end_year')->nullable();
            $table->boolean('is_current')->default(false);
            $table->timestamps();
        });

        Schema::create('certifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('issuer');
            $table->date('issue_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->string('credential_id')->nullable();
            $table->string('verification_url')->nullable();
            $table->string('file_path')->nullable();
            $table->timestamps();
        });

        Schema::create('social_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('platform');
            $table->string('url');
            $table->timestamps();
        });

        Schema::create('verification_badges', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('label');
        });

        Schema::create('user_verification_badges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('verification_badge_id')->constrained()->cascadeOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->unique(['user_id', 'verification_badge_id']);
        });

        Schema::create('profile_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('career_profile_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('viewer_user_id')->nullable();
            $table->string('country')->nullable();
            $table->string('action')->default('view');
            $table->timestamps();
            $table->index(['career_profile_id', 'action']);
        });

        Schema::create('recommendations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('recommender_user_id')->nullable();
            $table->string('recommender_name')->nullable();
            $table->string('recommender_title')->nullable();
            $table->text('message');
            $table->boolean('is_public')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recommendations');
        Schema::dropIfExists('profile_views');
        Schema::dropIfExists('user_verification_badges');
        Schema::dropIfExists('verification_badges');
        Schema::dropIfExists('social_profiles');
        Schema::dropIfExists('certifications');
        Schema::dropIfExists('educations');
        Schema::dropIfExists('experiences');
        Schema::dropIfExists('career_skills');
        Schema::dropIfExists('career_availabilities');
        Schema::dropIfExists('career_profiles');
        Schema::dropIfExists('universities');
    }
};
