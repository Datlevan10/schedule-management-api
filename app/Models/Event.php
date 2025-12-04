<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Event extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'start_datetime',
        'end_datetime',
        'location',
        'status',
        'event_category_id',
        'user_id',
        'priority',
        'ai_calculated_priority',
        'importance_score',
        'event_metadata',
        'participants',
        'requirements',
        'preparation_items',
        'completion_percentage',
        'recurring_pattern',
        'parent_event_id'
    ];

    protected $casts = [
        'start_datetime' => 'datetime',
        'end_datetime' => 'datetime',
        'event_metadata' => 'array',
        'participants' => 'array',
        'requirements' => 'array',
        'preparation_items' => 'array',
        'recurring_pattern' => 'array'
    ];

    public function category()
    {
        return $this->belongsTo(EventCategory::class, 'event_category_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function parentEvent()
    {
        return $this->belongsTo(Event::class, 'parent_event_id');
    }

    public function childEvents()
    {
        return $this->hasMany(Event::class, 'parent_event_id');
    }
}
