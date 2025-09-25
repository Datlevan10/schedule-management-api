<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Profession extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'display_name',
        'description',
        'default_categories',
        'default_priorities',
        'ai_keywords',
    ];

    protected function casts(): array
    {
        return [
            'default_categories' => 'array',
            'default_priorities' => 'array',
            'ai_keywords' => 'array',
        ];
    }

    // Relationships
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function eventTypes(): HasMany
    {
        return $this->hasMany(EventType::class);
    }
}
