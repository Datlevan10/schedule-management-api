<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RawScheduleEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'import_id',
        'user_id',
        'row_number',
        'raw_text',
        'original_data',
        'parsed_title',
        'parsed_description',
        'parsed_start_datetime',
        'parsed_end_datetime',
        'parsed_location',
        'parsed_priority',
        'detected_keywords',
        'ai_parsed_data',
        'ai_confidence',
        'ai_detected_category',
        'ai_detected_importance',
        'ai_analysis_status',
        'ai_analyzed_at',
        'ai_analysis_id',
        'ai_analysis_result',
        'ai_analysis_locked',
        'processing_status',
        'conversion_status',
        'converted_event_id',
        'parsing_errors',
        'manual_review_required',
        'manual_review_notes',
    ];

    protected $casts = [
        'original_data' => 'array',
        'detected_keywords' => 'array',
        'ai_parsed_data' => 'array',
        'ai_analysis_result' => 'array',
        'parsing_errors' => 'array',
        'ai_confidence' => 'decimal:2',
        'ai_detected_importance' => 'decimal:2',
        'parsed_start_datetime' => 'datetime',
        'parsed_end_datetime' => 'datetime',
        'ai_analyzed_at' => 'datetime',
        'manual_review_required' => 'boolean',
        'ai_analysis_locked' => 'boolean',
    ];

    /**
     * Get the import that owns this entry
     */
    public function import(): BelongsTo
    {
        return $this->belongsTo(RawScheduleImport::class, 'import_id');
    }

    /**
     * Get the user that owns this entry
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the converted event if exists
     */
    public function convertedEvent(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'converted_event_id');
    }

    /**
     * Scope for pending entries
     */
    public function scopePending($query)
    {
        return $query->where('processing_status', 'pending');
    }

    /**
     * Scope for parsed entries
     */
    public function scopeParsed($query)
    {
        return $query->where('processing_status', 'parsed');
    }

    /**
     * Scope for converted entries
     */
    public function scopeConverted($query)
    {
        return $query->where('processing_status', 'converted');
    }

    /**
     * Scope for failed entries
     */
    public function scopeFailed($query)
    {
        return $query->where('processing_status', 'failed');
    }

    /**
     * Scope for entries requiring manual review
     */
    public function scopeRequiresManualReview($query)
    {
        return $query->where('manual_review_required', true);
    }

    /**
     * Scope for high confidence entries
     */
    public function scopeHighConfidence($query, $threshold = 0.8)
    {
        return $query->where('ai_confidence', '>=', $threshold);
    }

    /**
     * Check if entry is successfully converted
     */
    public function isConverted(): bool
    {
        return $this->conversion_status === 'success' && $this->converted_event_id !== null;
    }

    /**
     * Check if entry has parsing errors
     */
    public function hasParsingErrors(): bool
    {
        return !empty($this->parsing_errors) || $this->processing_status === 'failed';
    }

    /**
     * Mark entry as parsed
     */
    public function markAsParsed(): void
    {
        $this->update(['processing_status' => 'parsed']);
    }

    /**
     * Mark entry as converted
     */
    public function markAsConverted(int $eventId): void
    {
        $this->update([
            'processing_status' => 'converted',
            'conversion_status' => 'success',
            'converted_event_id' => $eventId,
        ]);
    }

    /**
     * Mark entry as failed
     */
    public function markAsFailed(array $errors = []): void
    {
        $this->update([
            'processing_status' => 'failed',
            'conversion_status' => 'failed',
            'parsing_errors' => array_merge($this->parsing_errors ?? [], $errors),
        ]);
    }

    /**
     * Mark for manual review
     */
    public function markForManualReview(string $reason = null): void
    {
        $this->update([
            'manual_review_required' => true,
            'conversion_status' => 'manual_review',
            'manual_review_notes' => $reason,
        ]);
    }

    /**
     * Get parsed duration in minutes
     */
    public function getParsedDurationMinutesAttribute()
    {
        if (!$this->parsed_start_datetime || !$this->parsed_end_datetime) {
            return null;
        }
        
        return $this->parsed_start_datetime->diffInMinutes($this->parsed_end_datetime);
    }

    /**
     * Check if entry is available for AI analysis
     */
    public function isAvailableForAiAnalysis(): bool
    {
        return !$this->ai_analysis_locked && 
               in_array($this->ai_analysis_status, ['pending', 'failed']);
    }

    /**
     * Check if entry has been analyzed by AI
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
     * Mark entry as being analyzed by AI
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
     * Mark entry as AI analyzed
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
     * Scope for entries available for AI analysis
     */
    public function scopeAvailableForAiAnalysis($query)
    {
        return $query->where('ai_analysis_locked', false)
                    ->whereIn('ai_analysis_status', ['pending', 'failed']);
    }

    /**
     * Scope for AI analyzed entries
     */
    public function scopeAiAnalyzed($query)
    {
        return $query->where('ai_analysis_status', 'completed');
    }

    /**
     * Scope for entries in AI analysis
     */
    public function scopeInAiAnalysis($query)
    {
        return $query->where('ai_analysis_status', 'in_progress');
    }
}