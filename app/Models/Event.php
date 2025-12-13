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
        'parent_event_id',
        'ai_analysis_status',
        'ai_analyzed_at',
        'ai_analysis_id',
        'ai_analysis_result',
        'ai_analysis_locked'
    ];

    protected $casts = [
        'start_datetime' => 'datetime',
        'end_datetime' => 'datetime',
        'event_metadata' => 'array',
        'participants' => 'array',
        'requirements' => 'array',
        'preparation_items' => 'array',
        'recurring_pattern' => 'array',
        'ai_analysis_result' => 'array',
        'ai_analyzed_at' => 'datetime',
        'ai_analysis_locked' => 'boolean'
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

    /**
     * Check if event is available for AI analysis
     */
    public function isAvailableForAiAnalysis(): bool
    {
        return !$this->ai_analysis_locked && 
               in_array($this->ai_analysis_status, ['pending', 'failed']);
    }

    /**
     * Check if event has been analyzed by AI
     */
    public function isAiAnalyzed(): bool
    {
        return $this->ai_analysis_status === 'completed';
    }

    /**
     * Check if AI analysis is in progress
     */
    public function isAiAnalysisInProgress(): bool
    {
        return $this->ai_analysis_status === 'in_progress';
    }

    /**
     * Mark event as being analyzed by AI
     */
    public function markAsAiAnalysisInProgress(string $analysisId = null): void
    {
        $this->update([
            'ai_analysis_status' => 'in_progress',
            'ai_analysis_id' => $analysisId,
            'ai_analysis_locked' => true,
        ]);
    }

    /**
     * Mark event as AI analyzed
     */
    public function markAsAiAnalyzed(array $result = []): void
    {
        $this->update([
            'ai_analysis_status' => 'completed',
            'ai_analyzed_at' => now(),
            'ai_analysis_result' => $result,
            'ai_analysis_locked' => true,
        ]);
    }

    /**
     * Mark AI analysis as failed
     */
    public function markAiAnalysisFailed(string $error = null): void
    {
        $this->update([
            'ai_analysis_status' => 'failed',
            'ai_analysis_result' => ['error' => $error],
            'ai_analysis_locked' => false,
        ]);
    }

    /**
     * Reset AI analysis status
     */
    public function resetAiAnalysis(): void
    {
        $this->update([
            'ai_analysis_status' => 'pending',
            'ai_analyzed_at' => null,
            'ai_analysis_id' => null,
            'ai_analysis_result' => null,
            'ai_analysis_locked' => false,
        ]);
    }

    /**
     * Scope for events available for AI analysis
     */
    public function scopeAvailableForAiAnalysis($query)
    {
        return $query->where('ai_analysis_locked', false)
                    ->whereIn('ai_analysis_status', ['pending', 'failed']);
    }

    /**
     * Scope for AI analyzed events
     */
    public function scopeAiAnalyzed($query)
    {
        return $query->where('ai_analysis_status', 'completed');
    }

    /**
     * Scope for events in AI analysis
     */
    public function scopeInAiAnalysis($query)
    {
        return $query->where('ai_analysis_status', 'in_progress');
    }
}
