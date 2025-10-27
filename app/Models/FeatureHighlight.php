<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FeatureHighlight extends Model
{
    protected $fillable = [
        'title',
        'description',
        'icon_url',
        'order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'order' => 'integer',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('order', 'asc');
    }
}
