<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RawScheduleEntryResource extends JsonResource
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
            'import_id' => $this->import_id,
            'user_id' => $this->user_id,
            'row_number' => $this->row_number,
            'raw_text' => $this->raw_text,
            'original_data' => $this->original_data,
            'parsed_data' => [
                'title' => $this->parsed_title,
                'description' => $this->parsed_description,
                'start_datetime' => $this->parsed_start_datetime?->toIso8601String(),
                'end_datetime' => $this->parsed_end_datetime?->toIso8601String(),
                'duration_minutes' => $this->parsed_duration_minutes,
                'location' => $this->parsed_location,
                'priority' => $this->parsed_priority,
            ],
            'ai_analysis' => [
                'detected_keywords' => $this->detected_keywords,
                'parsed_data' => $this->ai_parsed_data,
                'confidence' => $this->ai_confidence,
                'detected_category' => $this->ai_detected_category,
                'detected_importance' => $this->ai_detected_importance,
                'meets_threshold' => $this->when($this->ai_confidence !== null, function () {
                    return $this->ai_confidence >= 0.7;
                }),
            ],
            'status' => [
                'processing' => $this->processing_status,
                'conversion' => $this->conversion_status,
                'is_converted' => $this->isConverted(),
                'has_errors' => $this->hasParsingErrors(),
                'manual_review_required' => $this->manual_review_required,
            ],
            'converted_event_id' => $this->converted_event_id,
            'parsing_errors' => $this->parsing_errors,
            'manual_review_notes' => $this->manual_review_notes,
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
            'import' => new RawScheduleImportResource($this->whenLoaded('import')),
            'converted_event' => $this->whenLoaded('convertedEvent', function () {
                return [
                    'id' => $this->convertedEvent->id,
                    'title' => $this->convertedEvent->title,
                    'start_datetime' => $this->convertedEvent->start_datetime,
                ];
            }),
        ];
    }
}