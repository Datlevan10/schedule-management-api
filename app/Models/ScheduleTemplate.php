<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScheduleTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'profession_id',
        'created_by',
        'name',
        'description',
        'template_type',
        'field_mapping',
        'required_fields',
        'optional_fields',
        'default_values',
        'date_formats',
        'time_formats',
        'keyword_patterns',
        'validation_rules',
        'ai_processing_rules',
        'usage_count',
        'success_rate',
        'is_active',
        'is_default',
    ];

    protected $casts = [
        'field_mapping' => 'array',
        'required_fields' => 'array',
        'optional_fields' => 'array',
        'default_values' => 'array',
        'date_formats' => 'array',
        'time_formats' => 'array',
        'keyword_patterns' => 'array',
        'validation_rules' => 'array',
        'ai_processing_rules' => 'array',
        'success_rate' => 'decimal:2',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
    ];

    /**
     * Get the profession for this template
     */
    public function profession(): BelongsTo
    {
        return $this->belongsTo(Profession::class);
    }

    /**
     * Get the user who created this template
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get user preferences using this template
     */
    public function userPreferences(): HasMany
    {
        return $this->hasMany(UserSchedulePreference::class, 'default_template_id');
    }

    /**
     * Scope for active templates
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for default templates
     */
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    /**
     * Scope for profession-specific templates
     */
    public function scopeForProfession($query, $professionId)
    {
        return $query->where('profession_id', $professionId);
    }

    /**
     * Scope for template type
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('template_type', $type);
    }

    /**
     * Increment usage count
     */
    public function incrementUsage(): void
    {
        $this->increment('usage_count');
    }

    /**
     * Update success rate
     */
    public function updateSuccessRate(int $successCount, int $totalCount): void
    {
        if ($totalCount > 0) {
            $rate = ($successCount / $totalCount) * 100;
            $this->update(['success_rate' => round($rate, 2)]);
        }
    }

    /**
     * Get all field names (required + optional)
     */
    public function getAllFieldsAttribute(): array
    {
        return array_merge(
            $this->required_fields ?? [],
            $this->optional_fields ?? []
        );
    }

    /**
     * Check if field is required
     */
    public function isFieldRequired(string $field): bool
    {
        return in_array($field, $this->required_fields ?? []);
    }

    /**
     * Get default value for field
     */
    public function getFieldDefault(string $field)
    {
        return $this->default_values[$field] ?? null;
    }

    /**
     * Validate data against template
     */
    public function validateData(array $data): array
    {
        $errors = [];
        
        // Check required fields
        foreach ($this->required_fields ?? [] as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                $errors[] = "Required field '{$field}' is missing";
            }
        }
        
        // Apply validation rules
        foreach ($this->validation_rules ?? [] as $field => $rules) {
            if (isset($data[$field])) {
                // Implement validation logic based on rules
                // This is a placeholder for actual validation
            }
        }
        
        return $errors;
    }
}