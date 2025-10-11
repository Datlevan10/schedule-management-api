<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class WelcomeScreen extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'subtitle',
        'background_type',
        'background_value',
        'duration',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'duration' => 'integer',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public static function getActiveScreen()
    {
        return static::active()->first();
    }

    public function activate()
    {
        static::query()->update(['is_active' => false]);
        $this->update(['is_active' => true]);
    }
}
