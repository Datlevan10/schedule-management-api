<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'start_datetime' => $this->start_datetime?->toIso8601String(),
            'end_datetime' => $this->end_datetime?->toIso8601String(),
            'location' => $this->location,
            'status' => $this->status,
            'event_category_id' => $this->event_category_id,
            'user_id' => $this->user_id,
            'priority' => $this->priority,
            'ai_calculated_priority' => $this->ai_calculated_priority,
            'importance_score' => $this->importance_score,
            'event_metadata' => $this->event_metadata,
            'participants' => $this->participants,
            'requirements' => $this->requirements,
            'preparation_items' => $this->preparation_items,
            'completion_percentage' => $this->completion_percentage,
            'recurring_pattern' => $this->recurring_pattern,
            'parent_event_id' => $this->parent_event_id,
            'ai_analysis' => [
                'status' => $this->ai_analysis_status ?? 'pending',
                'analyzed_at' => $this->ai_analyzed_at?->toIso8601String(),
                'analysis_id' => $this->ai_analysis_id,
                'analysis_result' => $this->ai_analysis_result,
                'is_locked' => $this->ai_analysis_locked ?? false,
                'is_available_for_analysis' => $this->when($this->ai_analysis_status !== null, function () {
                    return $this->isAvailableForAiAnalysis();
                }),
            ],
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
