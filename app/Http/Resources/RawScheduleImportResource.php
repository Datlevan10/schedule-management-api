<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RawScheduleImportResource extends JsonResource
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
            'user_id' => $this->user_id,
            'import_type' => $this->import_type,
            'source_type' => $this->source_type,
            'original_filename' => $this->original_filename,
            'file_size_bytes' => $this->file_size_bytes,
            'file_size_formatted' => $this->when($this->file_size_bytes, function () {
                return $this->formatFileSize($this->file_size_bytes);
            }),
            'mime_type' => $this->mime_type,
            'status' => $this->status,
            'processing_started_at' => $this->processing_started_at?->toIso8601String(),
            'processing_completed_at' => $this->processing_completed_at?->toIso8601String(),
            'processing_duration' => $this->when(
                $this->processing_started_at && $this->processing_completed_at,
                function () {
                    return $this->processing_started_at->diffForHumans($this->processing_completed_at, true);
                }
            ),
            'total_records_found' => $this->total_records_found,
            'successfully_processed' => $this->successfully_processed,
            'failed_records' => $this->failed_records,
            'success_rate' => $this->success_rate,
            'error_log' => $this->error_log,
            'ai_confidence_score' => $this->ai_confidence_score,
            'detected_format' => $this->detected_format,
            'detected_profession' => $this->detected_profession,
            'has_errors' => $this->hasErrors(),
            'entries_summary' => $this->when($this->relationLoaded('entries'), function () {
                $entries = $this->entries;
                return [
                    'total' => $entries->count(),
                    'pending' => $entries->where('processing_status', 'pending')->count(),
                    'parsed' => $entries->where('processing_status', 'parsed')->count(),
                    'converted' => $entries->where('processing_status', 'converted')->count(),
                    'failed' => $entries->where('processing_status', 'failed')->count(),
                    'manual_review' => $entries->where('manual_review_required', true)->count(),
                ];
            }),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
            'created_at_formatted' => $this->created_at->format('Y-m-d H:i:s'),
            'created_at_human' => $this->created_at->diffForHumans(),
        ];
    }

    /**
     * Format file size in human-readable format
     */
    protected function formatFileSize($bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));

        return round($bytes, 2) . ' ' . $units[$pow];
    }
}