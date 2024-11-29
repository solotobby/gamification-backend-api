<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProfessionalTool extends Model
{
    use HasFactory;
    protected $table = 'professional_tools';
    protected $fillable = [
        'professional_id',
        'tool_id'
    ];
}
