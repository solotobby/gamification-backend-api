<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Experience extends Model
{
    protected $fillable = [
        'user_id',
        'employer',
        'position',
        'employment_type',
        'location',
        'start_date',
        'end_date',
        'responsibilities',
        'achievements',
    ];
}
