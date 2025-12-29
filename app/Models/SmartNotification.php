<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SmartNotification extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'user_id',
        'type',
        'subtype',
        'trigger_datetime',
        'scheduled_at',
        'sent_at',
        'title',
        'message',
        'action_data',
        'ai_generated',
        'priority_level',
        'profession_specific_data',
        'status',
        'delivery_method',
        'opened_at',
        'action_taken',
        'feedback_rating',
    ];

    protected $casts = [
        'trigger_datetime' => 'datetime',
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
        'opened_at' => 'datetime',
        'action_data' => 'array',
        'profession_specific_data' => 'array',
        'ai_generated' => 'boolean',
        'action_taken' => 'boolean',
        'priority_level' => 'integer',
        'feedback_rating' => 'integer',
    ];

    /**
     * Get the event that owns this notification
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * Get the user that owns this notification
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope for pending notifications
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope for reminder type notifications
     */
    public function scopeReminders($query)
    {
        return $query->where('type', 'reminder');
    }
}