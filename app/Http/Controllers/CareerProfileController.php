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
}
