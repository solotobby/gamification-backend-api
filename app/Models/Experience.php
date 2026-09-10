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

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function setStartDateAttribute($value)
    {
        $this->attributes['start_date'] = $this->parseFlexibleDate($value);
    }

    public function setEndDateAttribute($value)
    {
        $this->attributes['end_date'] = $this->parseFlexibleDate($value);
    }

    protected function parseFlexibleDate($value): ?string
    {
        if (empty($value)) {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        $clean = trim((string) $value);
        if (str_contains($clean, '/')) {
            $clean = str_replace('/', '-', $clean);
        }

        try {
            return \Carbon\Carbon::parse($clean)->format('Y-m-d');
        } catch (\Throwable $e) {
            return $value;
        }
    }
}
