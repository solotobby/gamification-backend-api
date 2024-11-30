<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SurveyForm extends Model
{
    use HasFactory;
    protected $table = 'survey_forms';
    protected $fillable = [
        'survey_id',
        'type',
        'name',
        'required',
        'choices',
        'active'
    ];
}
