<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSchedulePreference extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'preferred_import_format',
        'default_template_id',
        'timezone_preference',
        'date_format_preference',
        'time_format_preference',
        'ai_auto_categorize',
        'ai_auto_priority',
        'ai_confidence_threshold',
        'default_event_duration_minutes',
        'default_priority',
        'default_category_id',
        'notify_on_import_completion',
        'notify_on_parsing_errors',
        'custom_field_mappings',
        'custom_keywords',
    ];

    protected $casts = [
        'custom_field_mappings' => 'array',
        'custom_keywords' => 'array',
        'ai_auto_categorize' => 'boolean',
        'ai_auto_priority' => 'boolean',
        'ai_confidence_threshold' => 'decimal:2',
        'notify_on_import_completion' => 'boolean',
        'notify_on_parsing_errors' => 'boolean',
    ];

    protected $attributes = [
        'preferred_import_format' => 'csv',
        'timezone_preference' => 'Asia/Ho_Chi_Minh',
        'date_format_preference' => 'dd/mm/yyyy',
        'time_format_preference' => 'HH:mm',
        'ai_auto_categorize' => true,
        'ai_auto_priority' => true,
        'ai_confidence_threshold' => 0.7,
        'default_event_duration_minutes' => 60,
        'default_priority' => 3,
        'notify_on_import_completion' => true,
        'notify_on_parsing_errors' => true,
    ];

    /**
     * Get the user that owns this preference
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the default template
     */
    public function defaultTemplate(): BelongsTo
    {
        return $this->belongsTo(ScheduleTemplate::class, 'default_template_id');
    }

    /**
     * Get the default category
     */
    public function defaultCategory(): BelongsTo
    {
        return $this->belongsTo(EventCategory::class, 'default_category_id');
    }

    /**
     * Get or create preferences for a user
     */
    public static function getOrCreateForUser(User $user): self
    {
        return self::firstOrCreate(
            ['user_id' => $user->id],
            []
        );
    }

    /**
     * Check if AI should automatically categorize
     */
    public function shouldAutoCatego(): bool
    {
        return $this->ai_auto_categorize;
    }

    /**
     * Check if AI should automatically prioritize
     */
    public function shouldAutoPrioritize(): bool
    {
        return $this->ai_auto_priority;
    }

    /**
     * Check if confidence level meets threshold
     */
    public function meetsConfidenceThreshold(float $confidence): bool
    {
        return $confidence >= $this->ai_confidence_threshold;
    }

    /**
     * Get custom field mapping for a field
     */
    public function getFieldMapping(string $field): ?string
    {
        return $this->custom_field_mappings[$field] ?? null;
    }

    /**
     * Check if keyword is in custom keywords
     */
    public function hasCustomKeyword(string $keyword): bool
    {
        return in_array(
            strtolower($keyword),
            array_map('strtolower', $this->custom_keywords ?? [])
        );
    }

    /**
     * Add custom keyword
     */
    public function addCustomKeyword(string $keyword): void
    {
        $keywords = $this->custom_keywords ?? [];
        if (!in_array($keyword, $keywords)) {
            $keywords[] = $keyword;
            $this->update(['custom_keywords' => $keywords]);
        }
    }

    /**
     * Remove custom keyword
     */
    public function removeCustomKeyword(string $keyword): void
    {
        $keywords = $this->custom_keywords ?? [];
        $keywords = array_values(array_diff($keywords, [$keyword]));
        $this->update(['custom_keywords' => $keywords]);
    }

    /**
     * Update field mapping
     */
    public function updateFieldMapping(string $field, string $mapping): void
    {
        $mappings = $this->custom_field_mappings ?? [];
        $mappings[$field] = $mapping;
        $this->update(['custom_field_mappings' => $mappings]);
    }
}