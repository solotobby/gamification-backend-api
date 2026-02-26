<?php

namespace App\Repositories;

use App\Models\Rating;


class RatingRepositoryModel
{

    public function create(array $data)
    {
        return Rating::create($data);
    }
}
