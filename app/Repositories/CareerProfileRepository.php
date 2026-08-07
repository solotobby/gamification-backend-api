<?php

namespace App\Repositories;

use App\Models\CareerProfile;
use App\Models\Certification;
use App\Models\Education;
use App\Models\Experience;
use App\Models\SocialProfile;
use App\Models\Skill;
use App\Models\University;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CareerProfileRepository
{
    public function getOrCreate($userId): CareerProfile
    {
        return CareerProfile::firstOrCreate(['user_id' => $userId]);
    }

    public function findBySlug(string $slug): ?CareerProfile
    {
        return CareerProfile::with([
            'skills:id,name',
            'experiences',
            'educations.university',
            'certifications',
            'socialProfiles',
            'availabilities',
            'badges',
        ])->where('slug', $slug)->where('is_public', true)->first();
    }

    public function updateProfile($userId, array $data): CareerProfile
    {
        $profile = $this->getOrCreate($userId);
        $profile->update($data);
        return $profile->fresh();
    }

    public function syncAvailabilities($careerProfileId, array $types): void
    {
        DB::table('career_availabilities')->where('career_profile_id', $careerProfileId)->delete();
        $rows = array_map(fn($t) => [
            'career_profile_id' => $careerProfileId,
            'type' => $t,
            'created_at' => now(),
            'updated_at' => now(),
        ], $types);
        if ($rows) DB::table('career_availabilities')->insert($rows);
    }

    public function syncSkills($userId, array $skillIds): void
    {
        DB::table('career_skills')->where('user_id', $userId)->delete();
        $rows = array_map(fn($id) => [
            'user_id' => $userId,
            'skill_id' => $id,
            'created_at' => now(),
            'updated_at' => now(),
        ], array_unique($skillIds));
        if ($rows) DB::table('career_skills')->insert($rows);
    }

    // ── Experience ──────────────────────────────────────────────
    public function addExperience($userId, array $data): Experience
    {
        return Experience::create(array_merge($data, ['user_id' => $userId]));
    }

    public function updateExperience($id, $userId, array $data): Experience
    {
        $exp = Experience::where('id', $id)->where('user_id', $userId)->firstOrFail();
        $exp->update($data);
        return $exp->fresh();
    }

    public function deleteExperience($id, $userId): void
    {
        Experience::where('id', $id)->where('user_id', $userId)->delete();
    }

    // ── Education ────────────────────────────────────────────────
    public function addEducation($userId, array $data): Education
    {
        if (!empty($data['institution']) && empty($data['university_id'])) {
            $university = University::firstOrCreate(
                ['slug' => Str::slug($data['institution'])],
                ['name' => $data['institution']]
            );
            $data['university_id'] = $university->id;
        }
        return Education::create(array_merge($data, ['user_id' => $userId]));
    }

    public function updateEducation($id, $userId, array $data): Education
    {
        $edu = Education::where('id', $id)->where('user_id', $userId)->firstOrFail();
        $edu->update($data);
        return $edu->fresh();
    }

    public function deleteEducation($id, $userId): void
    {
        Education::where('id', $id)->where('user_id', $userId)->delete();
    }

    // ── Certification ───────────────────────────────────────────
    public function addCertification($userId, array $data): Certification
    {
        return Certification::create(array_merge($data, ['user_id' => $userId]));
    }

    public function updateCertification($id, $userId, array $data): Certification
    {
        $cert = Certification::where('id', $id)->where('user_id', $userId)->firstOrFail();
        $cert->update($data);
        return $cert->fresh();
    }

    public function deleteCertification($id, $userId): void
    {
        Certification::where('id', $id)->where('user_id', $userId)->delete();
    }


    public function updateFile($userId, string $field, string $path): CareerProfile
    {
        $profile = $this->getOrCreate($userId);
        $profile->update([$field => $path]);
        return $profile->fresh();
    }

    // ── Social profiles ─────────────────────────────────────────
    public function syncSocialProfiles($userId, array $profiles): void
    {
        SocialProfile::where('user_id', $userId)->delete();
        $rows = array_map(fn($p) => array_merge($p, [
            'user_id' => $userId,
            'created_at' => now(),
            'updated_at' => now(),
        ]), $profiles);
        if ($rows) SocialProfile::insert($rows);
    }

    public function getSkillsList()
    {
        return Skill::all(['id', 'name']);
    }

    public function getAnalytics($userId): array
    {
        $profile = CareerProfile::where('user_id', $userId)->first();
        if (!$profile) {
            return ['profile_views' => 0, 'cv_downloads' => 0, 'shares' => 0, 'countries' => []];
        }

        $rows = DB::table('profile_views')->where('career_profile_id', $profile->id);

        return [
            'profile_views' => (clone $rows)->where('action', 'view')->count(),
            'cv_downloads'  => (clone $rows)->where('action', 'cv_download')->count(),
            'shares'        => (clone $rows)->where('action', 'share')->count(),
            'countries'     => (clone $rows)->whereNotNull('country')->distinct()->pluck('country'),
            'views_last_30_days' => (clone $rows)->where('action', 'view')->where('created_at', '>=', now()->subDays(30))->count(),
        ];
    }
    public function findPublicBySlug(string $slug): ?CareerProfile
    {
        return CareerProfile::with([
            'user:id,name',
            'skills:id,name',
            'experiences',
            'educations.university',
            'certifications',
            'socialProfiles',
            'availabilities',
            'badges',
        ])->where('slug', $slug)->where('is_public', true)->first();
    }

    public function categoryIndex(string $categorySlug, $page = 1, $perPage = 20)
    {
        // category matches against Skill::name slug — e.g. /talent/software-developers -> "Software Developer" skill
        $skill = Skill::whereRaw('LOWER(REPLACE(name, " ", "-")) LIKE ?', [rtrim($categorySlug, 's') . '%'])->first();

        $query = CareerProfile::query()->where('is_public', true);

        if ($skill) {
            $query->whereHas('skills', fn($q) => $q->where('skills.id', $skill->id));
        }

        return $query->with('skills:id,name')->latest()->paginate($perPage, ['*'], 'page', $page);
    }

    public function skillPage(string $skillSlug, $page = 1, $perPage = 20)
    {
        $skill = Skill::whereRaw('LOWER(REPLACE(name, " ", "-")) = ?', [$skillSlug])->firstOrFail();

        $profiles = CareerProfile::query()
            ->where('is_public', true)
            ->whereHas('skills', fn($q) => $q->where('skills.id', $skill->id))
            ->with('skills:id,name')
            ->latest()
            ->paginate($perPage, ['*'], 'page', $page);

        return [$skill, $profiles];
    }

    public function universityPage(string $universitySlug, $page = 1, $perPage = 20)
    {
        $university = University::where('slug', $universitySlug)->firstOrFail();

        $profiles = CareerProfile::query()
            ->where('is_public', true)
            ->whereHas('educations', fn($q) => $q->where('university_id', $university->id))
            ->with('skills:id,name')
            ->latest()
            ->paginate($perPage, ['*'], 'page', $page);

        return [$university, $profiles];
    }

    public function trackView($careerProfileId, ?string $action = 'view'): void
    {
        DB::table('profile_views')->insert([
            'career_profile_id' => $careerProfileId,
            'viewer_user_id'    => auth()->id(),
            'action'             => $action,
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);
    }
}
