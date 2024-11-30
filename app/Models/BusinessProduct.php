<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BusinessProduct extends Model
{
    use HasFactory;

    protected $table = 'business_products';
    protected $fillable = [
        'business_id',
        'pid',
        'unique',
        'name',
        'description',
        'price',
        'img',
        'visits',
        'is_live'
    ];

    public function business()
    {
        return $this->belongsTo(Business::class, 'business_id');
    }

    public function businessCategory()
    {
        return $this->belongsTo(BusinessCategory::class);
    }
}
