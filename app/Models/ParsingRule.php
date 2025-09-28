<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParsingRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'rule_name',
        'profession_id',
        'rule_type',
        'rule_pattern',
        'rule_action',
        'conditions',
        'priority_order',
        'positive_examples',
        'negative_examples',
        'accuracy_rate',
        'usage_count',
        'success_count',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'rule_action' => 'array',
        'conditions' => 'array',
        'positive_examples' => 'array',
        'negative_examples' => 'array',
        'accuracy_rate' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    /**
     * Get the profession for this rule
     */
    public function profession(): BelongsTo
    {
        return $this->belongsTo(Profession::class);
    }

    /**
     * Get the user who created this rule
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope for active rules
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for rules by type
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('rule_type', $type);
    }

    /**
     * Scope for global rules (no profession)
     */
    public function scopeGlobal($query)
    {
        return $query->whereNull('profession_id');
    }

    /**
     * Scope for profession-specific rules
     */
    public function scopeForProfession($query, $professionId)
    {
        return $query->where('profession_id', $professionId);
    }

    /**
     * Scope for applicable rules (global + profession-specific)
     */
    public function scopeApplicableFor($query, $professionId)
    {
        return $query->where(function ($q) use ($professionId) {
            $q->whereNull('profession_id')
              ->orWhere('profession_id', $professionId);
        });
    }

    /**
     * Scope ordered by priority
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('priority_order', 'asc');
    }

    /**
     * Apply rule to text
     */
    public function applyToText(string $text): ?array
    {
        if (!$this->matchesPattern($text)) {
            return null;
        }

        // Check conditions if any
        if (!$this->checkConditions($text)) {
            return null;
        }

        // Return the action to apply
        return $this->rule_action;
    }

    /**
     * Check if text matches rule pattern
     */
    public function matchesPattern(string $text): bool
    {
        try {
            return preg_match($this->rule_pattern, $text) === 1;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Check if conditions are met
     */
    protected function checkConditions(string $text): bool
    {
        if (empty($this->conditions)) {
            return true;
        }

        foreach ($this->conditions as $condition) {
            // Implement condition checking logic
            // This is a placeholder for actual implementation
        }

        return true;
    }

    /**
     * Increment usage
     */
    public function incrementUsage(bool $success = false): void
    {
        $this->increment('usage_count');
        
        if ($success) {
            $this->increment('success_count');
            $this->updateAccuracy();
        }
    }

    /**
     * Update accuracy rate
     */
    protected function updateAccuracy(): void
    {
        if ($this->usage_count > 0) {
            $rate = ($this->success_count / $this->usage_count) * 100;
            $this->update(['accuracy_rate' => round($rate, 2)]);
        }
    }

    /**
     * Test rule with examples
     */
    public function testWithExamples(): array
    {
        $results = [
            'positive' => [],
            'negative' => [],
        ];

        // Test positive examples
        foreach ($this->positive_examples ?? [] as $example) {
            $results['positive'][] = [
                'example' => $example,
                'matches' => $this->matchesPattern($example),
            ];
        }

        // Test negative examples
        foreach ($this->negative_examples ?? [] as $example) {
            $results['negative'][] = [
                'example' => $example,
                'matches' => $this->matchesPattern($example),
            ];
        }

        return $results;
    }
}