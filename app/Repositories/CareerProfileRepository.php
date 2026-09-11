<?php

namespace App\Repositories;

use App\Models\CareerProfile;
use App\Models\Certification;
use App\Models\Education;
use App\Models\Experience;
use App\Models\Skill;
use App\Models\SocialProfile;
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
        if ($rows)
            DB::table('career_availabilities')->insert($rows);
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
        if ($rows)
            DB::table('career_skills')->insert($rows);
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

    public function getCareerProfiles(array $filters = [], $page = null, bool $publicOnly = false)
    {
        $query = CareerProfile::query()
            ->with(['user:id,name,email', 'skills:id,name'])
            ->when($publicOnly, fn($q) => $q->where('is_public', true))
            ->when($filters['skill'] ?? null, function ($q, $skill) {
                $q->whereHas('skills', function ($sq) use ($skill) {
                    if (is_numeric($skill)) {
                        $sq->where('skills.id', $skill);
                    } else {
                        $sq->where('skills.name', 'like', "%{$skill}%");
                    }
                });
            })
            ->when($filters['availability'] ?? null, fn($q, $avail) =>
                $q->whereHas('availabilities', fn($aq) => $aq->where('type', $avail)))
            ->when($filters['location'] ?? null, function ($q, $loc) {
                $loc = trim($loc);
                $q->where(function ($lq) use ($loc) {
                    $lq->where('city', 'like', "%{$loc}%")
                        ->orWhere('state', 'like', "%{$loc}%")
                        ->orWhere('country', 'like', "%{$loc}%");
                });
            })
            ->when($filters['professional_level'] ?? null, fn($q, $lvl) =>
                $q->where('professional_level', $lvl))
            ->when(isset($filters['price_min']) && $filters['price_min'] !== '', function ($q) use ($filters) {
                $min = (float) $filters['price_min'];
                $q->where(function ($pq) use ($min) {
                    $pq->where('price_max', '>=', $min)
                        ->orWhere(function ($sub) use ($min) {
                            $sub->whereNull('price_max')->where('price_min', '>=', $min);
                        });
                });
            })
            ->when(isset($filters['price_max']) && $filters['price_max'] !== '', function ($q) use ($filters) {
                $max = (float) $filters['price_max'];
                $q->where('price_min', '<=', $max);
            })
            ->when($filters['search'] ?? null, function ($q, $search) {
                $term = trim($search);
                $q->where(function ($sq) use ($term) {
                    $sq->where('professional_title', 'like', "%{$term}%")
                        ->orWhere('headline', 'like', "%{$term}%")
                        ->orWhere('summary', 'like', "%{$term}%")
                        ->orWhere('city', 'like', "%{$term}%")
                        ->orWhere('state', 'like', "%{$term}%")
                        ->orWhere('country', 'like', "%{$term}%")
                        ->orWhereHas('user', fn($uq) => $uq->where('name', 'like', "%{$term}%"))
                        ->orWhereHas('skills', fn($skq) => $skq->where('skills.name', 'like', "%{$term}%"));
                });
            });

        // ── Smart Ranking / Sorting ───────────────────────────────────
        $sort = $filters['sort'] ?? 'recommended';
        $seed = (int) ($filters['seed'] ?? crc32(date('Y-m-d') . '-talent'));

        switch ($sort) {
            case 'highest_score':
                $query->orderByDesc('talent_score')
                    ->orderByDesc('profile_completeness')
                    ->orderByDesc('id');
                break;

            case 'completeness':
                $query->orderByDesc('profile_completeness')
                    ->orderByDesc('talent_score')
                    ->orderByDesc('id');
                break;

            case 'latest':
                $query->latest('created_at');
                break;

            case 'random':
                $query->inRandomOrder($seed);
                break;

            case 'recommended':
            default:
                $driver = DB::connection()->getDriverName();
                if ($driver === 'mysql') {
                    $rawScore = "
                        (career_profiles.profile_completeness * 0.35) +
                        (career_profiles.talent_score * 0.35) +
                        (CASE WHEN career_profiles.photo_path IS NOT NULL AND career_profiles.photo_path != '' THEN 10 ELSE 0 END) +
                        (CASE WHEN career_profiles.cv_file_path IS NOT NULL AND career_profiles.cv_file_path != '' THEN 10 ELSE 0 END) +
                        (CASE WHEN career_profiles.updated_at >= NOW() - INTERVAL 30 DAY THEN 10 WHEN career_profiles.updated_at >= NOW() - INTERVAL 90 DAY THEN 5 ELSE 0 END) +
                        (MOD(ABS(career_profiles.id * {$seed}), 100) / 100.0 * 20.0)
                    ";
                    $query->orderByRaw("({$rawScore}) DESC");
                } else {
                    $query->orderByDesc('talent_score')
                        ->orderByDesc('profile_completeness')
                        ->orderByDesc('id');
                }
                break;
        }

        return $query->paginate($filters['per_page'] ?? 15, ['*'], 'page', $page);
    }

    public function getCareerProfileById($id): CareerProfile
    {
        return CareerProfile::with([
            'user:id,name,email',
            'skills:id,name',
            'experiences',
            'educations.university',
            'certifications',
            'socialProfiles',
            'availabilities',
            'badges',
        ])->findOrFail($id);
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
        if ($rows)
            SocialProfile::insert($rows);
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
            'cv_downloads' => (clone $rows)->where('action', 'cv_download')->count(),
            'shares' => (clone $rows)->where('action', 'share')->count(),
            'countries' => (clone $rows)->whereNotNull('country')->distinct()->pluck('country'),
            'views_last_30_days' => (clone $rows)->where('action', 'view')->where('created_at', '>=', now()->subDays(30))->count(),
        ];
    }

    public function findPublicBySlug(string $slug): ?CareerProfile
    {
        return CareerProfile::with([
            'user:id,name,email',
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
        $skill = Skill::whereRaw('LOWER(REPLACE(name, " ", "-")) LIKE ?', [rtrim($categorySlug, 's') . '%'])->first();

        $query = CareerProfile::query()->where('is_public', true);

        if ($skill) {
            $query->whereHas('skills', fn($q) => $q->where('skills.id', $skill->id));
        }

        $seed = (int) crc32(date('Y-m-d') . '-' . $categorySlug);
        $rawScore = "
            (career_profiles.profile_completeness * 0.35) +
            (career_profiles.talent_score * 0.35) +
            (CASE WHEN career_profiles.photo_path IS NOT NULL AND career_profiles.photo_path != '' THEN 10 ELSE 0 END) +
            (CASE WHEN career_profiles.cv_file_path IS NOT NULL AND career_profiles.cv_file_path != '' THEN 10 ELSE 0 END) +
            (MOD(ABS(career_profiles.id * {$seed}), 100) / 100.0 * 20.0)
        ";

        return $query->with(['skills:id,name', 'user:id,name,email'])
            ->orderByRaw("({$rawScore}) DESC")
            ->paginate($perPage, ['*'], 'page', $page);
    }

    public function skillPage(string $skillSlug, $page = 1, $perPage = 20)
    {
        $skill = Skill::whereRaw('LOWER(REPLACE(name, " ", "-")) = ?', [$skillSlug])->firstOrFail();

        $seed = (int) crc32(date('Y-m-d') . '-' . $skillSlug);
        $rawScore = "
            (career_profiles.profile_completeness * 0.35) +
            (career_profiles.talent_score * 0.35) +
            (CASE WHEN career_profiles.photo_path IS NOT NULL AND career_profiles.photo_path != '' THEN 10 ELSE 0 END) +
            (CASE WHEN career_profiles.cv_file_path IS NOT NULL AND career_profiles.cv_file_path != '' THEN 10 ELSE 0 END) +
            (MOD(ABS(career_profiles.id * {$seed}), 100) / 100.0 * 20.0)
        ";

        $profiles = CareerProfile::query()
            ->where('is_public', true)
            ->whereHas('skills', fn($q) => $q->where('skills.id', $skill->id))
            ->with(['skills:id,name', 'user:id,name,email'])
            ->orderByRaw("({$rawScore}) DESC")
            ->paginate($perPage, ['*'], 'page', $page);

        return [$skill, $profiles];
    }

    public function universityPage(string $universitySlug, $page = 1, $perPage = 20)
    {
        $university = University::where('slug', $universitySlug)->firstOrFail();

        $seed = (int) crc32(date('Y-m-d') . '-' . $universitySlug);
        $rawScore = "
            (career_profiles.profile_completeness * 0.35) +
            (career_profiles.talent_score * 0.35) +
            (CASE WHEN career_profiles.photo_path IS NOT NULL AND career_profiles.photo_path != '' THEN 10 ELSE 0 END) +
            (CASE WHEN career_profiles.cv_file_path IS NOT NULL AND career_profiles.cv_file_path != '' THEN 10 ELSE 0 END) +
            (MOD(ABS(career_profiles.id * {$seed}), 100) / 100.0 * 20.0)
        ";

        $profiles = CareerProfile::query()
            ->where('is_public', true)
            ->whereHas('educations', fn($q) => $q->where('university_id', $university->id))
            ->with(['skills:id,name', 'user:id,name,email'])
            ->orderByRaw("({$rawScore}) DESC")
            ->paginate($perPage, ['*'], 'page', $page);

        return [$university, $profiles];
    }

    public function trackView($careerProfileId, ?string $action = 'view'): void
    {
        DB::table('profile_views')->insert([
            'career_profile_id' => $careerProfileId,
            'viewer_user_id' => auth()->id(),
            'action' => $action,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($action === 'view') {
            CareerProfile::where('id', $careerProfileId)->increment('views_count');
        }
    }
}
