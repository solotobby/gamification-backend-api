<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;


class SkillAsset extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'slug',
        'description',
        'skill_id',
        'profeciency_level',
        'year_experience',
        'location',
        'availability',
        'max_price',
        'min_price',
        'status',
        'portfolio_link'
    ];


     protected static function boot()
    {
        parent::boot();

        static::creating(function (SkillAsset $skillAsset) {
            $skillAsset->slug = static::generateUniqueSlug(
                $skillAsset->resolveUserName(),
                $skillAsset->title ?? 'worker'
            );
        });

        static::updating(function (SkillAsset $skillAsset) {
            // Only regenerate if title changed and slug wasn't set manually
            if ($skillAsset->isDirty('title') && !$skillAsset->isDirty('slug')) {
                $skillAsset->slug = static::generateUniqueSlug(
                    $skillAsset->resolveUserName(),
                    $skillAsset->title,
                    $skillAsset->id
                );
            }
        });
    }

    /**
     * Resolve the owning user's name at slug-generation time.
     * user_id is already set on the model even before the initial save,
     * so this relation query works fine inside the `creating` event.
     */
    protected function resolveUserName(): string
    {
        if ($this->relationLoaded('user') && $this->user) {
            return $this->user->name;
        }

        return optional(User::find($this->user_id))->name ?? 'worker';
    }

    public static function generateUniqueSlug(string $userName, string $title, $ignoreId = null): string
    {
        $base = Str::slug($userName . ' ' . $title) ?: 'worker';
        $slug = $base;
        $i = 1;

        while (
            static::where('slug', $slug)
                ->when($ignoreId, fn($q) => $q->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }

    /**
     * Route-model-binding-friendly lookup: accept slug or numeric id.
     */
    public function resolveRouteBindingQuery($query, $value, $field = null)
    {
        if (is_numeric($value)) {
            return $query->where('id', $value);
        }

        return $query->where('slug', $value);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function skill()
    {
        return $this->belongsTo(Skill::class, 'skill_id');
    }

    public function profeciencyLevel()
    {
        return $this->belongsTo(ProfessionalProficiencyLevel::class, 'profeciency_level');
    }
}
