<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class OptimizedScheduleSlot extends Model
{
    use HasFactory;

    protected $fillable = [
        'analysis_id',
        'user_id',
        'original_entry_id',
        'event_id',
        'date',
        'start_time',
        'end_time',
        'duration_minutes',
        'task_title',
        'task_description',
        'location',
        'priority',
        'category',
        'ai_reasoning',
        'suitability_score',
        'optimization_factors',
        'is_flexible',
        'alternative_slots',
        'reminder_minutes_before',
        'notification_sent',
        'notification_sent_at',
        'status',
        'user_confirmed',
        'confirmed_at',
        'completed_at'
    ];

    protected $casts = [
        'date' => 'date',
        'start_time' => 'datetime:H:i',
        'end_time' => 'datetime:H:i',
        'optimization_factors' => 'array',
        'alternative_slots' => 'array',
        'is_flexible' => 'boolean',
        'notification_sent' => 'boolean',
        'user_confirmed' => 'boolean',
        'notification_sent_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'completed_at' => 'datetime',
        'suitability_score' => 'decimal:2'
    ];

    /**
     * Get the analysis that owns the slot
     */
    public function analysis(): BelongsTo
    {
        return $this->belongsTo(AiScheduleAnalysis::class);
    }

    /**
     * Get the user that owns the slot
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the original entry if exists
     */
    public function originalEntry(): BelongsTo
    {
        return $this->belongsTo(RawScheduleEntry::class, 'original_entry_id');
    }

    /**
     * Get the associated event if created
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * Scope for today's slots
     */
    public function scopeToday($query)
    {
        return $query->whereDate('date', Carbon::today());
    }

    /**
     * Scope for upcoming slots
     */
    public function scopeUpcoming($query)
    {
        return $query->where('date', '>=', Carbon::today())
                    ->where('status', 'scheduled')
                    ->orderBy('date')
                    ->orderBy('start_time');
    }

    /**
     * Scope for slots needing notification
     */
    public function scopeNeedingNotification($query)
    {
        $now = Carbon::now();
        return $query->where('notification_sent', false)
                    ->where('status', 'scheduled')
                    ->whereRaw("CONCAT(date, ' ', start_time) <= ?", [
                        $now->copy()->addMinutes(30)->format('Y-m-d H:i:s')
                    ]);
    }

    /**
     * Get full start datetime
     */
    public function getStartDatetimeAttribute(): Carbon
    {
        return Carbon::parse($this->date->format('Y-m-d') . ' ' . $this->start_time);
    }

    /**
     * Get full end datetime
     */
    public function getEndDatetimeAttribute(): Carbon
    {
        return Carbon::parse($this->date->format('Y-m-d') . ' ' . $this->end_time);
    }

    /**
     * Check if slot is happening now
     */
    public function getIsCurrentAttribute(): bool
    {
        $now = Carbon::now();
        return $now->between($this->start_datetime, $this->end_datetime);
    }

    /**
     * Check if slot is upcoming
     */
    public function getIsUpcomingAttribute(): bool
    {
        return $this->start_datetime->isFuture();
    }

    /**
     * Check if slot is past
     */
    public function getIsPastAttribute(): bool
    {
        return $this->end_datetime->isPast();
    }

    /**
     * Get priority color
     */
    public function getPriorityColorAttribute(): string
    {
        return match($this->priority) {
            'critical' => 'danger',
            'high' => 'warning',
            'medium' => 'info',
            'low' => 'secondary',
            default => 'primary'
        };
    }

    /**
     * Get priority label in Vietnamese
     */
    public function getPriorityLabelAttribute(): string
    {
        return match($this->priority) {
            'critical' => 'Khẩn cấp',
            'high' => 'Cao',
            'medium' => 'Trung bình',
            'low' => 'Thấp',
            default => 'Không xác định'
        };
    }

    /**
     * Mark as confirmed by user
     */
    public function confirm(): void
    {
        $this->update([
            'user_confirmed' => true,
            'confirmed_at' => now()
        ]);
    }

    /**
     * Mark as completed
     */
    public function complete(): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now()
        ]);
    }

    /**
     * Cancel the slot
     */
    public function cancel(): void
    {
        $this->update(['status' => 'cancelled']);
    }

    /**
     * Create event from this slot
     */
    public function createEvent(): Event
    {
        if ($this->event_id) {
            return $this->event;
        }

        $event = Event::create([
            'user_id' => $this->user_id,
            'title' => $this->task_title,
            'description' => $this->task_description,
            'location' => $this->location,
            'start_datetime' => $this->start_datetime,
            'end_datetime' => $this->end_datetime,
            'priority' => $this->priority,
            'category' => $this->category,
            'ai_generated' => true,
            'source' => 'ai_optimization'
        ]);

        $this->update(['event_id' => $event->id]);

        return $event;
    }

    /**
     * Should send notification
     */
    public function shouldSendNotification(): bool
    {
        if ($this->notification_sent || $this->status !== 'scheduled') {
            return false;
        }

        if (!$this->reminder_minutes_before) {
            return false;
        }

        $notificationTime = $this->start_datetime->copy()->subMinutes($this->reminder_minutes_before);
        
        return Carbon::now()->gte($notificationTime);
    }
}