<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Certification extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'issuer',
        'issue_date',
        'expiry_date',
        'credential_id',
        'file_path',
    ];

    public function setIssueDateAttribute($value)
    {
        $this->attributes['issue_date'] = $this->parseFlexibleDate($value);
    }

    public function setExpiryDateAttribute($value)
    {
        $this->attributes['expiry_date'] = $this->parseFlexibleDate($value);
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
