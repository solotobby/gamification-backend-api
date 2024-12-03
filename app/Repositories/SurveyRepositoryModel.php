<?php

namespace App\Repositories;

use App\Models\Preference;


class SurveyRepositoryModel
{

    public function listAllInterest(){
        return Preference::orderBy('name', 'ASC')->get();
    }

}
