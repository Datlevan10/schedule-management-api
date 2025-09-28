<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ParsingRuleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'rule_name' => $this->rule_name,
            'profession_id' => $this->profession_id,
            'profession' => $this->whenLoaded('profession', function () {
                return [
                    'id' => $this->profession->id,
                    'name' => $this->profession->name,
                ];
            }),
            'rule_type' => $this->rule_type,
            'rule_pattern' => $this->rule_pattern,
            'rule_action' => $this->rule_action,
            'conditions' => $this->conditions,
            'priority_order' => $this->priority_order,
            'examples' => [
                'positive' => $this->positive_examples,
                'negative' => $this->negative_examples,
                'positive_count' => is_array($this->positive_examples) ? count($this->positive_examples) : 0,
                'negative_count' => is_array($this->negative_examples) ? count($this->negative_examples) : 0,
            ],
            'performance' => [
                'accuracy_rate' => $this->accuracy_rate ? round($this->accuracy_rate, 3) : null,
                'usage_count' => $this->usage_count,
                'success_count' => $this->success_count,
                'success_percentage' => $this->usage_count > 0 ? round(($this->success_count / $this->usage_count) * 100, 1) : 0,
            ],
            'is_active' => $this->is_active,
            'created_by' => $this->created_by,
            'creator' => $this->whenLoaded('creator', function () {
                return [
                    'id' => $this->creator->id,
                    'name' => $this->creator->name,
                    'email' => $this->creator->email,
                ];
            }),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
            'status' => [
                'is_reliable' => $this->accuracy_rate && $this->accuracy_rate >= 0.8,
                'is_popular' => $this->usage_count > 100,
                'needs_improvement' => $this->accuracy_rate && $this->accuracy_rate < 0.6,
                'has_examples' => !empty($this->positive_examples) || !empty($this->negative_examples),
            ],
            'scope' => [
                'is_global' => is_null($this->profession_id),
                'profession_specific' => !is_null($this->profession_id),
                'access_level' => is_null($this->profession_id) ? 'global' : 'profession',
            ],
        ];
    }
}