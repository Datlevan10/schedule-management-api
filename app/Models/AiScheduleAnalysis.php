<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiScheduleAnalysis extends Model
{
    use HasFactory;

    protected $table = 'ai_schedule_analyses';

    protected $fillable = [
        'user_id',
        'import_id',
        'input_data',
        'analysis_type',
        'target_date',
        'end_date',
        'status',
        'ai_model',
        'ai_request_payload',
        'ai_response',
        'optimized_schedule',
        'optimization_metrics',
        'ai_reasoning',
        'confidence_score',
        'user_preferences',
        'work_start_time',
        'work_end_time',
        'break_duration',
        'excluded_time_slots',
        'processing_time_ms',
        'token_usage',
        'api_cost',
        'error_details',
        'retry_count',
        'user_approved',
        'user_rating',
        'user_feedback',
        'user_modifications'
    ];

    protected $casts = [
        'input_data' => 'array',
        'ai_request_payload' => 'array',
        'ai_response' => 'array',
        'optimized_schedule' => 'array',
        'optimization_metrics' => 'array',
        'user_preferences' => 'array',
        'excluded_time_slots' => 'array',
        'error_details' => 'array',
        'user_modifications' => 'array',
        'target_date' => 'date',
        'end_date' => 'date',
        'confidence_score' => 'decimal:2',
        'api_cost' => 'decimal:4',
        'user_approved' => 'boolean'
    ];

    /**
     * Get the user that owns the analysis
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the import that triggered this analysis
     */
    public function import(): BelongsTo
    {
        return $this->belongsTo(RawScheduleImport::class, 'import_id');
    }

    /**
     * Get the schedule slots for this analysis
     */
    public function scheduleSlots(): HasMany
    {
        return $this->hasMany(OptimizedScheduleSlot::class, 'analysis_id');
    }

    /**
     * Scope for completed analyses
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope for pending analyses
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope for user approved analyses
     */
    public function scopeApproved($query)
    {
        return $query->where('user_approved', true);
    }

    /**
     * Calculate total scheduled time
     */
    public function getTotalScheduledTimeAttribute(): int
    {
        if (!$this->optimized_schedule || !isset($this->optimized_schedule['schedule_slots'])) {
            return 0;
        }

        $totalMinutes = 0;
        foreach ($this->optimized_schedule['schedule_slots'] as $slot) {
            $totalMinutes += $slot['duration_minutes'] ?? 0;
        }

        return $totalMinutes;
    }

    /**
     * Get utilization rate
     */
    public function getUtilizationRateAttribute(): float
    {
        if (!$this->optimization_metrics || !isset($this->optimization_metrics['utilization_rate'])) {
            return 0.0;
        }

        return (float) $this->optimization_metrics['utilization_rate'];
    }

    /**
     * Mark as approved by user
     */
    public function approve(int $rating = null, string $feedback = null): void
    {
        $this->update([
            'user_approved' => true,
            'user_rating' => $rating,
            'user_feedback' => $feedback
        ]);
    }

    /**
     * Create schedule slots from optimized schedule
     */
    public function createScheduleSlots(): void
    {
        if (!$this->optimized_schedule || !isset($this->optimized_schedule['schedule_slots'])) {
            return;
        }

        foreach ($this->optimized_schedule['schedule_slots'] as $slotData) {
            $this->scheduleSlots()->create([
                'user_id' => $this->user_id,
                'date' => $this->target_date,
                'start_time' => $slotData['start_time'],
                'end_time' => $slotData['end_time'],
                'duration_minutes' => $slotData['duration_minutes'] ?? 0,
                'task_title' => $slotData['task_title'],
                'task_description' => $slotData['task_description'] ?? null,
                'location' => $slotData['location'] ?? null,
                'priority' => $slotData['priority'] ?? 'medium',
                'category' => $slotData['category'] ?? null,
                'ai_reasoning' => $slotData['reasoning'] ?? null,
                'suitability_score' => $slotData['suitability_score'] ?? null,
                'optimization_factors' => $slotData['optimization_factors'] ?? [],
                'is_flexible' => $slotData['can_be_rescheduled'] ?? false,
                'alternative_slots' => $slotData['alternative_slots'] ?? null,
                'reminder_minutes_before' => $slotData['reminder_minutes_before'] ?? 15,
            ]);
        }
    }

    /**
     * Get status color for UI
     */
    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'completed' => 'success',
            'processing' => 'warning',
            'failed' => 'danger',
            'partial' => 'info',
            default => 'secondary'
        };
    }
}