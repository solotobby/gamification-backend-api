<?php
// app/Services/CareerProfileService.php
namespace App\Services;

use App\Repositories\CareerProfileRepository;
use Illuminate\Support\Facades\Log;
use Throwable;

class CareerProfileService
{
    public function __construct(protected CareerProfileRepository $repo) {}

    public function getMyProfile()
    {
        try {
            $profile = $this->repo->getOrCreate(auth()->id());
            $profile->load(['skills:id,name', 'experiences', 'educations.university', 'certifications', 'socialProfiles', 'availabilities', 'badges']);

            return response()->json([
                'status' => true,
                'message' => 'Profile retrieved successfully.',
                'data' => $profile,
            ], 200);
        } catch (Throwable $e) {
            return response()->json(['status' => false, 'message' => 'Error retrieving profile.', 'error' => $e->getMessage()], 500);
        }
    }

    public function updateProfile($request)
    {
        try {
            $validated = $request->validate([
                'professional_title' => 'sometimes|string|max:150',
                'headline'            => 'sometimes|string|max:150',
                'summary'             => 'sometimes|string|max:3000',
                'professional_level'  => 'sometimes|in:student_talent,junior_professional,mid_level_professional,senior_professional,expert',
                'city'                => 'sometimes|string|max:120',
                'state'               => 'sometimes|nullable|string|max:120',
                'country'             => 'sometimes|string|max:120',
                'is_public'           => 'sometimes|boolean',
                'availabilities'      => 'sometimes|array',
                'availabilities.*'    => 'string',
                'skill_ids'           => 'sometimes|array',
                'skill_ids.*'         => 'integer|exists:skills,id',
            ]);

            $userId = auth()->id();
            $profile = $this->repo->updateProfile($userId, collect($validated)->except(['availabilities', 'skill_ids'])->toArray());

            if (array_key_exists('availabilities', $validated)) {
                $this->repo->syncAvailabilities($profile->id, $validated['availabilities']);
            }
            if (array_key_exists('skill_ids', $validated)) {
                $this->repo->syncSkills($userId, $validated['skill_ids']);
            }

            $this->recalculate($profile->fresh());

            return response()->json([
                'status' => true,
                'message' => 'Profile updated successfully.',
                'data' => $profile->fresh(['skills:id,name', 'availabilities']),
            ], 200);
        } catch (Throwable $e) {
            Log::error('Career profile update failed: ' . $e->getMessage());
            return response()->json(['status' => false, 'message' => 'Error updating profile.', 'error' => $e->getMessage()], 500);
        }
    }

    public function addExperience($request)
    {
        $validated = $request->validate([
            'employer' => 'required|string|max:150',
            'position' => 'required|string|max:150',
            'employment_type' => 'nullable|string|max:60',
            'location' => 'nullable|string|max:150',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'responsibilities' => 'nullable|string',
            'achievements' => 'nullable|string',
        ]);

        $exp = $this->repo->addExperience(auth()->id(), $validated);
        $this->recalculate($this->repo->getOrCreate(auth()->id()));

        return response()->json(['status' => true, 'message' => 'Experience added.', 'data' => $exp], 201);
    }

    public function updateExperience($request, $id)
    {
        $validated = $request->validate([
            'employer' => 'sometimes|string|max:150',
            'position' => 'sometimes|string|max:150',
            'employment_type' => 'sometimes|nullable|string|max:60',
            'location' => 'sometimes|nullable|string|max:150',
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|nullable|date|after_or_equal:start_date',
            'responsibilities' => 'sometimes|nullable|string',
            'achievements' => 'sometimes|nullable|string',
        ]);

        $exp = $this->repo->updateExperience($id, auth()->id(), $validated);
        return response()->json(['status' => true, 'message' => 'Experience updated.', 'data' => $exp], 200);
    }

    public function deleteExperience($id)
    {
        $this->repo->deleteExperience($id, auth()->id());
        $this->recalculate($this->repo->getOrCreate(auth()->id()));
        return response()->json(['status' => true, 'message' => 'Experience removed.'], 200);
    }

    public function addEducation($request)
    {
        $validated = $request->validate([
            'institution'   => 'required|string|max:200',
            'qualification' => 'required|string|max:150',
            'course'        => 'nullable|string|max:150',
            'start_year'    => 'required|integer|min:1950|max:' . (date('Y') + 1),
            'end_year'      => 'nullable|integer|min:1950|max:' . (date('Y') + 10),
            'is_current'    => 'sometimes|boolean',
        ]);

        $edu = $this->repo->addEducation(auth()->id(), $validated);
        $this->recalculate($this->repo->getOrCreate(auth()->id()));

        return response()->json(['status' => true, 'message' => 'Education added.', 'data' => $edu->load('university')], 201);
    }

    public function updateEducation($request, $id)
    {
        $validated = $request->validate([
            'institution'   => 'sometimes|string|max:200',
            'qualification' => 'sometimes|string|max:150',
            'course'        => 'sometimes|nullable|string|max:150',
            'start_year'    => 'sometimes|integer',
            'end_year'      => 'sometimes|nullable|integer',
            'is_current'    => 'sometimes|boolean',
        ]);

        $edu = $this->repo->updateEducation($id, auth()->id(), $validated);
        return response()->json(['status' => true, 'message' => 'Education updated.', 'data' => $edu], 200);
    }

    public function deleteEducation($id)
    {
        $this->repo->deleteEducation($id, auth()->id());
        $this->recalculate($this->repo->getOrCreate(auth()->id()));
        return response()->json(['status' => true, 'message' => 'Education removed.'], 200);
    }

    public function addCertification($request)
    {
        $validated = $request->validate([
            'name'              => 'required|string|max:200',
            'issuer'            => 'required|string|max:150',
            'issue_date'        => 'nullable|date',
            'expiry_date'       => 'nullable|date|after_or_equal:issue_date',
            'credential_id'     => 'nullable|string|max:100',
            'verification_url'  => 'nullable|url',
        ]);

        $cert = $this->repo->addCertification(auth()->id(), $validated);
        $this->recalculate($this->repo->getOrCreate(auth()->id()));

        return response()->json(['status' => true, 'message' => 'Certification added.', 'data' => $cert], 201);
    }

    public function deleteCertification($id)
    {
        $this->repo->deleteCertification($id, auth()->id());
        $this->recalculate($this->repo->getOrCreate(auth()->id()));
        return response()->json(['status' => true, 'message' => 'Certification removed.'], 200);
    }

    public function updateSocialProfiles($request)
    {
        $validated = $request->validate([
            'profiles'            => 'required|array',
            'profiles.*.platform' => 'required|string|max:50',
            'profiles.*.url'      => 'required|url',
        ]);

        $this->repo->syncSocialProfiles(auth()->id(), $validated['profiles']);
        $this->recalculate($this->repo->getOrCreate(auth()->id()));

        return response()->json(['status' => true, 'message' => 'Social profiles updated.'], 200);
    }

    public function getSkillOptions()
    {
        return response()->json(['status' => true, 'data' => $this->repo->getSkillsList()], 200);
    }

    /**
     * Weighted profile completeness + talent score.
     * Recalculated after any profile-affecting write, not on read — cheap writes only.
     */
    private function recalculate($profile): void
    {
        $profile->loadCount(['experiences', 'educations', 'certifications', 'skills']);

        $checks = [
            'headline'    => !empty($profile->headline) ? 10 : 0,
            'summary'     => !empty($profile->summary) ? 10 : 0,
            'skills'      => $profile->skills_count > 0 ? 15 : 0,
            'experience'  => $profile->experiences_count > 0 ? 20 : 0,
            'education'   => $profile->educations_count > 0 ? 15 : 0,
            'portfolio'   => 0, // wired up once Portfolio is linked to career profile
            'certificate' => $profile->certifications_count > 0 ? 10 : 0,
            'cv'          => !empty($profile->cv_file_path) ? 10 : 0,
            'photo'       => !empty($profile->photo_path) ? 10 : 0,
        ];

        $completeness = array_sum($checks);
        $talentScore  = (int) round($completeness * 0.86); // placeholder weighting until verification/recommendations land

        $profile->forceFill([
            'profile_completeness' => min($completeness, 100),
            'talent_score'         => min($talentScore, 100),
        ])->saveQuietly();
    }

    public function getPublic(string $slug)
    {
        try {
            $profile = $this->repo->findPublicBySlug($slug);

            if (!$profile) {
                return response()->json(['status' => false, 'message' => 'Profile not found.'], 404);
            }

            $this->repo->trackView($profile->id);

            return response()->json([
                'status' => true,
                'message' => 'Profile retrieved successfully.',
                'data' => $profile,
            ], 200);
        } catch (Throwable $e) {
            return response()->json(['status' => false, 'message' => 'Error retrieving profile.', 'error' => $e->getMessage()], 500);
        }
    }

    public function categoryIndex(string $category, $page)
    {
        try {
            $profiles = $this->repo->categoryIndex($category, $page);
            return response()->json(['status' => true, 'data' => $profiles->items(), 'pagination' => $this->pagination($profiles)], 200);
        } catch (Throwable $e) {
            return response()->json(['status' => false, 'message' => 'Error retrieving category.'], 500);
        }
    }

    public function skillPage(string $skill, $page)
    {
        try {
            [$skillModel, $profiles] = $this->repo->skillPage($skill, $page);
            return response()->json([
                'status' => true,
                'skill' => $skillModel,
                'data' => $profiles->items(),
                'pagination' => $this->pagination($profiles),
            ], 200);
        } catch (Throwable $e) {
            return response()->json(['status' => false, 'message' => 'Skill not found.'], 404);
        }
    }

    public function universityPage(string $university, $page)
    {
        try {
            [$uni, $profiles] = $this->repo->universityPage($university, $page);
            return response()->json([
                'status' => true,
                'university' => $uni,
                'data' => $profiles->items(),
                'pagination' => $this->pagination($profiles),
            ], 200);
        } catch (Throwable $e) {
            return response()->json(['status' => false, 'message' => 'University not found.'], 404);
        }
    }

    private function pagination($p): array
    {
        return [
            'total' => $p->total(),
            'per_page' => $p->perPage(),
            'current_page' => $p->currentPage(),
            'last_page' => $p->lastPage(),
        ];
    }
}
