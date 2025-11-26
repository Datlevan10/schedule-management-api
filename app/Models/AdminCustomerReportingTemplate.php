<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AdminCustomerReportingTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'template_name',
        'description',
        'customer_fields',
        'report_filters',
        'aggregation_rules',
        'report_frequency',
        'notification_settings',
        'is_active',
        'is_default',
        'customer_limit',
        'created_by',
        'last_generated_at',
        'total_reports_generated',
        'success_rate',
    ];

    protected $casts = [
        'customer_fields' => 'array',
        'report_filters' => 'array',
        'aggregation_rules' => 'array',
        'notification_settings' => 'array',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'last_generated_at' => 'datetime',
        'success_rate' => 'decimal:2',
    ];

    // Relationships
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    public function scopeByFrequency($query, $frequency)
    {
        return $query->where('report_frequency', $frequency);
    }

    // Methods
    public function markAsGenerated()
    {
        $this->update([
            'last_generated_at' => now(),
            'total_reports_generated' => $this->total_reports_generated + 1,
        ]);
    }

    public function updateSuccessRate($success)
    {
        $totalReports = $this->total_reports_generated;
        if ($totalReports > 0) {
            $currentSuccessCount = ($this->success_rate / 100) * $totalReports;
            $newSuccessCount = $success ? $currentSuccessCount + 1 : $currentSuccessCount;
            $newRate = ($newSuccessCount / ($totalReports + 1)) * 100;
            $this->update(['success_rate' => round($newRate, 2)]);
        }
    }

    public function getCustomerCount()
    {
        // This would count actual customers using this template
        // For now returning a placeholder
        return User::count();
    }

    public function isAtCustomerLimit()
    {
        if (!$this->customer_limit) {
            return false;
        }
        return $this->getCustomerCount() >= $this->customer_limit;
    }

    public function generateReportData()
    {
        // Generate report based on customer_fields and filters
        $customerFields = $this->customer_fields;
        $filters = $this->report_filters ?? [];
        
        $query = User::query();
        
        // Apply filters
        foreach ($filters as $field => $value) {
            if (isset($value) && $value !== '') {
                $query->where($field, $value);
            }
        }
        
        $customers = $query->get();
        
        // Apply aggregation rules
        $aggregated = $this->applyAggregationRules($customers);
        
        return [
            'template_name' => $this->template_name,
            'generated_at' => now(),
            'total_customers' => $customers->count(),
            'customer_data' => $aggregated,
            'filters_applied' => $filters,
        ];
    }

    protected function applyAggregationRules($customers)
    {
        $rules = $this->aggregation_rules ?? [];
        $result = [];
        
        foreach ($this->customer_fields as $field) {
            switch ($rules[$field] ?? 'count') {
                case 'count':
                    $result[$field] = $customers->count();
                    break;
                case 'unique_count':
                    $result[$field] = $customers->pluck($field)->unique()->count();
                    break;
                case 'group_by':
                    $result[$field] = $customers->groupBy($field)->map->count();
                    break;
                case 'avg':
                    $result[$field] = $customers->avg($field);
                    break;
                case 'sum':
                    $result[$field] = $customers->sum($field);
                    break;
                default:
                    $result[$field] = $customers->pluck($field)->toArray();
            }
        }
        
        return $result;
    }
}
