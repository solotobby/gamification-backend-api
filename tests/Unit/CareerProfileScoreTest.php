<?php

namespace Tests\Unit;

use App\Models\CareerProfile;
use App\Models\Education;
use App\Models\Experience;
use App\Repositories\CareerProfileRepository;
use App\Services\CareerProfileService;
use App\Services\Providers\SpacesService;
use Mockery;
use Tests\TestCase;

class CareerProfileScoreTest extends TestCase
{
    public function test_profile_without_certification_scores_96_percent()
    {
        $mockRepo = Mockery::mock(CareerProfileRepository::class);
        $mockSpaces = Mockery::mock(SpacesService::class);
        $service = new CareerProfileService($mockRepo, $mockSpaces);

        $profile = new CareerProfile([
            'headline' => 'Senior Backend Developer',
            'summary' => 'Experienced software engineer with over 6 years building scalable web APIs and distributed systems.',
            'photo_path' => 'photos/user_1.webp',
            'cv_file_path' => 'cvs/user_1_resume.pdf',
            'city' => 'Lagos',
            'country' => 'Nigeria',
        ]);

        $exp1 = new Experience([
            'employer' => 'Tech Corp',
            'position' => 'Senior Engineer',
            'responsibilities' => 'Built APIs and microservices',
            'achievements' => 'Scaled platform to 1M users',
        ]);

        $profile->setRelation('experiences', collect([$exp1]));
        $profile->experiences_count = 1;
        $profile->educations_count = 1;
        $profile->skills_count = 3;
        $profile->certifications_count = 0;

        $service->recalculate($profile);

        $this->assertEquals(96, $profile->profile_completeness);
        $this->assertEquals(96, $profile->talent_score);
    }

    public function test_profile_with_certification_scores_100_percent()
    {
        $mockRepo = Mockery::mock(CareerProfileRepository::class);
        $mockSpaces = Mockery::mock(SpacesService::class);
        $service = new CareerProfileService($mockRepo, $mockSpaces);

        $profile = new CareerProfile([
            'headline' => 'Senior Backend Developer',
            'summary' => 'Experienced software engineer with over 6 years building scalable web APIs and distributed systems.',
            'photo_path' => 'photos/user_1.webp',
            'cv_file_path' => 'cvs/user_1_resume.pdf',
            'city' => 'Lagos',
            'country' => 'Nigeria',
        ]);

        $exp1 = new Experience([
            'employer' => 'Tech Corp',
            'position' => 'Senior Engineer',
            'responsibilities' => 'Built APIs and microservices',
            'achievements' => 'Scaled platform to 1M users',
        ]);

        $profile->setRelation('experiences', collect([$exp1]));
        $profile->experiences_count = 1;
        $profile->educations_count = 1;
        $profile->skills_count = 3;
        $profile->certifications_count = 1;

        $service->recalculate($profile);

        $this->assertEquals(100, $profile->profile_completeness);
        $this->assertEquals(100, $profile->talent_score);
    }

    public function test_profile_with_short_bio_and_single_skill_scores_lower()
    {
        $mockRepo = Mockery::mock(CareerProfileRepository::class);
        $mockSpaces = Mockery::mock(SpacesService::class);
        $service = new CareerProfileService($mockRepo, $mockSpaces);

        $profile = new CareerProfile([
            'headline' => 'Developer',
            'summary' => 'Short bio', // < 40 chars -> 10 pts instead of 15
            'photo_path' => 'photos/user_1.webp',
            'cv_file_path' => 'cvs/user_1_resume.pdf',
            'city' => 'Lagos',
            'country' => 'Nigeria',
        ]);

        $exp1 = new Experience([
            'employer' => 'Tech Corp',
            'position' => 'Junior Dev',
            // No responsibilities or achievements -> 18 pts instead of 22
        ]);

        $profile->setRelation('experiences', collect([$exp1]));
        $profile->experiences_count = 1;
        $profile->educations_count = 1;
        $profile->skills_count = 1; // 1 skill -> 6 pts instead of 10
        $profile->certifications_count = 0;

        $service->recalculate($profile);

        // 10 (headline) + 10 (summary) + 10 (photo) + 10 (cv) + 5 (location) + 18 (exp) + 14 (edu) + 6 (skill) = 83
        $this->assertEquals(83, $profile->profile_completeness);
        $this->assertEquals(83, $profile->talent_score);
    }
}
