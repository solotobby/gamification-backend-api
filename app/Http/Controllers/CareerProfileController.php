<?php

namespace App\Http\Controllers;

use App\Services\CareerProfileService;
use Illuminate\Http\Request;

class CareerProfileController extends Controller
{
    public function __construct(protected CareerProfileService $service) {}

    public function show()
    {
        return $this->service->getMyProfile();
    }
    public function update(Request $request)
    {
        return $this->service->updateProfile($request);
    }

    public function completeOnboarding()
    {
        return $this->service->completeOnboarding();
    }

    public function storeExperience(Request $request)
    {
        return $this->service->addExperience($request);
    }
    public function updateExperience(Request $request, $id)
    {
        return $this->service->updateExperience($request, $id);
    }
    public function destroyExperience($id)
    {
        return $this->service->deleteExperience($id);
    }

    public function storeEducation(Request $request)
    {
        return $this->service->addEducation($request);
    }
    public function updateEducation(Request $request, $id)
    {
        return $this->service->updateEducation($request, $id);
    }
    public function destroyEducation($id)
    {
        return $this->service->deleteEducation($id);
    }
    public function indexCareer(Request $request)
    {
        return $this->service->getCareerProfiles($request, publicOnly: true);
    }

    public function showCareer($id)
    {
        return $this->service->getCareerProfileDetail($id);
    }


    public function storeCertification(Request $request)
    {
        return $this->service->addCertification($request);
    }
    public function destroyCertification($id)
    {
        return $this->service->deleteCertification($id);
    }

    public function updateSocialProfiles(Request $request)
    {
        return $this->service->updateSocialProfiles($request);
    }
    public function skillOptions()
    {
        return $this->service->getSkillOptions();
    }

    public function analytics()
    {
        return $this->service->getAnalytics();
    }

    public function uploadPhoto(Request $request)
    {
        return $this->service->uploadPhoto($request);
    }
    public function uploadCv(Request $request)
    {
        return $this->service->uploadCv($request);
    }
    public function uploadCertificationFile(Request $request, $id)
    {
        return $this->service->uploadCertificationFile($request, $id);
    }
}
