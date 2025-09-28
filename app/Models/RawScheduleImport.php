<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RawScheduleImport extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'import_type',
        'source_type',
        'original_filename',
        'file_size_bytes',
        'mime_type',
        'raw_content',
        'raw_data',
        'file_path',
        'status',
        'processing_started_at',
        'processing_completed_at',
        'total_records_found',
        'successfully_processed',
        'failed_records',
        'error_log',
        'ai_confidence_score',
        'detected_format',
        'detected_profession',
    ];

    protected $casts = [
        'raw_data' => 'array',
        'error_log' => 'array',
        'ai_confidence_score' => 'decimal:2',
        'processing_started_at' => 'datetime',
        'processing_completed_at' => 'datetime',
    ];

    /**
     * Get the user that owns the import
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the entries for this import
     */
    public function entries(): HasMany
    {
        return $this->hasMany(RawScheduleEntry::class, 'import_id');
    }

    /**
     * Scope for pending imports
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope for processing imports
     */
    public function scopeProcessing($query)
    {
        return $query->where('status', 'processing');
    }

    /**
     * Scope for completed imports
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope for failed imports
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Calculate success rate
     */
    public function getSuccessRateAttribute()
    {
        if ($this->total_records_found === 0) {
            return 0;
        }
        
        return round(($this->successfully_processed / $this->total_records_found) * 100, 2);
    }

    /**
     * Check if import has errors
     */
    public function hasErrors(): bool
    {
        return $this->failed_records > 0 || !empty($this->error_log);
    }

    /**
     * Mark import as processing
     */
    public function markAsProcessing(): void
    {
        $this->update([
            'status' => 'processing',
            'processing_started_at' => now(),
        ]);
    }

    /**
     * Mark import as completed
     */
    public function markAsCompleted(): void
    {
        $this->update([
            'status' => 'completed',
            'processing_completed_at' => now(),
        ]);
    }

    /**
     * Mark import as failed
     */
    public function markAsFailed(array $errors = []): void
    {
        $this->update([
            'status' => 'failed',
            'processing_completed_at' => now(),
            'error_log' => array_merge($this->error_log ?? [], $errors),
        ]);
    }
}