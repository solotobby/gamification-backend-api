<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BusinessCategory extends Model
{
    use HasFactory;

    protected $table = 'business_categories'; 
    protected $fillable = [
        'name',
        'is_active'
    ];

    public function businesses()
    {
        return $this->hasMany(Business::class);
    }
}
