<?php

namespace App\Services;

use App\Models\RawScheduleImport;
use App\Models\RawScheduleEntry;
use App\Models\ScheduleTemplate;
use App\Models\UserSchedulePreference;
use App\Models\ParsingRule;
use App\Models\Event;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ScheduleParsingService
{
    /**
     * Parse import data and create entries
     */
    public function parseImport(RawScheduleImport $import, ?int $templateId = null): void
    {
        try {
            $import->markAsProcessing();

            // Get user preferences
            $preferences = UserSchedulePreference::getOrCreateForUser($import->user);

            // Get template if specified
            $template = null;
            if ($templateId) {
                $template = ScheduleTemplate::find($templateId);
            } elseif ($preferences->default_template_id) {
                $template = $preferences->defaultTemplate;
            }

            // Parse content based on source type
            $parsedData = $this->parseContent(
                $import->raw_content,
                $import->source_type,
                $template
            );

            // Store parsed data
            $import->raw_data = $parsedData;
            $import->total_records_found = count($parsedData['entries'] ?? []);
            $import->save();

            // Create entries
            $this->createEntries($import, $parsedData['entries'] ?? [], $preferences);

            // Update import statistics
            $this->updateImportStatistics($import);

            $import->markAsCompleted();

        } catch (\Exception $e) {
            Log::error('Failed to parse import', [
                'import_id' => $import->id,
                'error' => $e->getMessage()
            ]);

            $import->markAsFailed(['error' => $e->getMessage()]);
        }
    }

    /**
     * Process import with AI analysis
     */
    public function processImport(RawScheduleImport $import, ?int $templateId = null): void
    {
        try {
            $import->markAsProcessing();

            // Get applicable parsing rules
            $rules = ParsingRule::active()
                ->applicableFor($import->user->profession_id)
                ->ordered()
                ->get();

            // Process each entry
            $entries = $import->entries()->pending()->get();
            foreach ($entries as $entry) {
                $this->processEntry($entry, $rules);
            }

            // Update import statistics
            $this->updateImportStatistics($import);

            $import->markAsCompleted();

        } catch (\Exception $e) {
            Log::error('Failed to process import', [
                'import_id' => $import->id,
                'error' => $e->getMessage()
            ]);

            $import->markAsFailed(['error' => $e->getMessage()]);
        }
    }

    /**
     * Parse content based on source type
     */
    protected function parseContent(string $content, string $sourceType, ?ScheduleTemplate $template = null): array
    {
        $result = ['entries' => []];

        switch ($sourceType) {
            case 'csv':
                $result = $this->parseCsv($content, $template);
                break;
            case 'json':
                $result = $this->parseJson($content, $template);
                break;
            case 'txt':
            case 'manual':
                $result = $this->parseText($content, $template);
                break;
            case 'excel':
                $result = $this->parseExcel($content, $template);
                break;
            case 'ics':
                $result = $this->parseIcs($content, $template);
                break;
        }

        return $result;
    }

    /**
     * Parse CSV content
     */
    protected function parseCsv(string $content, ?ScheduleTemplate $template = null): array
    {
        $lines = explode("\n", $content);
        $headers = str_getcsv(array_shift($lines));
        $entries = [];

        foreach ($lines as $index => $line) {
            if (empty(trim($line))) continue;

            $data = str_getcsv($line);
            $entry = [];

            foreach ($headers as $i => $header) {
                $entry[$header] = $data[$i] ?? null;
            }

            // Apply template field mapping if available
            if ($template && $template->field_mapping) {
                $entry = $this->applyFieldMapping($entry, $template->field_mapping);
            }

            $entries[] = [
                'row_number' => $index + 2, // +2 because headers are row 1
                'original_data' => $entry,
                'raw_text' => $line
            ];
        }

        return ['entries' => $entries];
    }

    /**
     * Parse JSON content
     */
    protected function parseJson(string $content, ?ScheduleTemplate $template = null): array
    {
        $data = json_decode($content, true);
        $entries = [];

        if (!is_array($data)) {
            return ['entries' => []];
        }

        // Handle both array of objects and single object
        $items = isset($data[0]) ? $data : [$data];

        foreach ($items as $index => $item) {
            // Apply template field mapping if available
            if ($template && $template->field_mapping) {
                $item = $this->applyFieldMapping($item, $template->field_mapping);
            }

            $entries[] = [
                'row_number' => $index + 1,
                'original_data' => $item,
                'raw_text' => json_encode($item)
            ];
        }

        return ['entries' => $entries];
    }

    /**
     * Parse text content
     */
    protected function parseText(string $content, ?ScheduleTemplate $template = null): array
    {
        $lines = explode("\n", $content);
        $entries = [];

        foreach ($lines as $index => $line) {
            if (empty(trim($line))) continue;

            $entries[] = [
                'row_number' => $index + 1,
                'raw_text' => $line,
                'original_data' => ['text' => $line]
            ];
        }

        return ['entries' => $entries];
    }

    /**
     * Parse Excel content (placeholder)
     */
    protected function parseExcel(string $content, ?ScheduleTemplate $template = null): array
    {
        // This would require a library like PhpSpreadsheet
        // For now, treat it as CSV
        return $this->parseCsv($content, $template);
    }

    /**
     * Parse iCalendar content (placeholder)
     */
    protected function parseIcs(string $content, ?ScheduleTemplate $template = null): array
    {
        // This would require an iCal parser library
        // For now, return empty
        return ['entries' => []];
    }

    /**
     * Apply field mapping from template
     */
    protected function applyFieldMapping(array $data, array $mapping): array
    {
        $mapped = [];

        foreach ($mapping as $targetField => $sourceField) {
            if (isset($data[$sourceField])) {
                $mapped[$targetField] = $data[$sourceField];
            }
        }

        // Keep unmapped fields
        foreach ($data as $key => $value) {
            if (!isset($mapped[$key])) {
                $mapped[$key] = $value;
            }
        }

        return $mapped;
    }

    /**
     * Create entries from parsed data
     */
    protected function createEntries(RawScheduleImport $import, array $parsedEntries, UserSchedulePreference $preferences): void
    {
        foreach ($parsedEntries as $entryData) {
            $entry = new RawScheduleEntry([
                'import_id' => $import->id,
                'user_id' => $import->user_id,
                'row_number' => $entryData['row_number'] ?? null,
                'raw_text' => $entryData['raw_text'] ?? null,
                'original_data' => $entryData['original_data'] ?? [],
                'processing_status' => 'pending',
                'conversion_status' => 'pending',
            ]);

            // Apply initial parsing
            $this->applyInitialParsing($entry, $preferences);

            $entry->save();
        }
    }

    /**
     * Apply initial parsing to entry
     */
    protected function applyInitialParsing(RawScheduleEntry $entry, UserSchedulePreference $preferences): void
    {
        $data = $entry->original_data;
        
        // Convert keys to lowercase for case-insensitive matching
        $lowerData = array_change_key_case($data, CASE_LOWER);

        // Try to extract common fields (check both original case and lowercase)
        $entry->parsed_title = $data['Title'] ?? $data['title'] ?? $lowerData['title'] ?? 
                               $data['event'] ?? $lowerData['event'] ?? 
                               $data['subject'] ?? $lowerData['subject'] ?? null;
        
        $entry->parsed_description = $data['Description'] ?? $data['description'] ?? $lowerData['description'] ?? 
                                     $data['notes'] ?? $lowerData['notes'] ?? 
                                     $data['details'] ?? $lowerData['details'] ?? null;
        
        $entry->parsed_location = $data['Location'] ?? $data['location'] ?? $lowerData['location'] ?? 
                                  $data['venue'] ?? $lowerData['venue'] ?? 
                                  $data['place'] ?? $lowerData['place'] ?? null;
        
        // Parse dates - check various field name combinations
        $startDateField = $data['Start Date'] ?? $data['start_date'] ?? $lowerData['start date'] ?? 
                         $data['StartDate'] ?? $lowerData['startdate'] ?? 
                         $data['date'] ?? $lowerData['date'] ?? 
                         $data['datetime'] ?? $lowerData['datetime'] ?? null;
        
        if ($startDateField) {
            try {
                $entry->parsed_start_datetime = Carbon::parse($startDateField);
            } catch (\Exception $e) {
                // Date parsing failed
                Log::warning('Failed to parse start date', [
                    'value' => $startDateField,
                    'error' => $e->getMessage()
                ]);
            }
        }

        $endDateField = $data['End Date'] ?? $data['end_date'] ?? $lowerData['end date'] ?? 
                       $data['EndDate'] ?? $lowerData['enddate'] ?? 
                       $data['end_time'] ?? $lowerData['end_time'] ?? null;
        
        if ($endDateField) {
            try {
                $entry->parsed_end_datetime = Carbon::parse($endDateField);
            } catch (\Exception $e) {
                // Date parsing failed
                Log::warning('Failed to parse end date', [
                    'value' => $endDateField,
                    'error' => $e->getMessage()
                ]);
            }
        }

        // Set priority - check various field names
        $priorityField = $data['Priority'] ?? $data['priority'] ?? $lowerData['priority'] ?? 
                        $data['importance'] ?? $lowerData['importance'] ?? null;
        
        if ($priorityField !== null) {
            $entry->parsed_priority = intval($priorityField);
        } else {
            $entry->parsed_priority = $preferences->default_priority ?? 3;
        }

        // Set AI confidence (would be calculated by AI)
        $entry->ai_confidence = 0.75; // Higher confidence for structured data
    }

    /**
     * Process single entry with AI and rules
     */
    protected function processEntry(RawScheduleEntry $entry, Collection $rules): void
    {
        try {
            // Apply parsing rules
            foreach ($rules as $rule) {
                if ($rule->rule_type === 'keyword_detection') {
                    $this->applyKeywordDetection($entry, $rule);
                } elseif ($rule->rule_type === 'priority_calculation') {
                    $this->applyPriorityCalculation($entry, $rule);
                } elseif ($rule->rule_type === 'category_assignment') {
                    $this->applyCategoryAssignment($entry, $rule);
                }
            }

            // Simulate AI parsing (in real implementation, this would call AI service)
            $this->simulateAiParsing($entry);

            $entry->markAsParsed();

        } catch (\Exception $e) {
            $entry->markAsFailed(['error' => $e->getMessage()]);
        }
    }

    /**
     * Apply keyword detection rule
     */
    protected function applyKeywordDetection(RawScheduleEntry $entry, ParsingRule $rule): void
    {
        $text = $entry->raw_text . ' ' . json_encode($entry->original_data);
        
        if ($rule->matchesPattern($text)) {
            $keywords = $entry->detected_keywords ?? [];
            $action = $rule->applyToText($text);
            
            if (isset($action['keywords'])) {
                $keywords = array_merge($keywords, $action['keywords']);
                $entry->detected_keywords = array_unique($keywords);
            }
        }
    }

    /**
     * Apply priority calculation rule
     */
    protected function applyPriorityCalculation(RawScheduleEntry $entry, ParsingRule $rule): void
    {
        $text = $entry->raw_text . ' ' . $entry->parsed_title . ' ' . $entry->parsed_description;
        
        if ($rule->matchesPattern($text)) {
            $action = $rule->applyToText($text);
            
            if (isset($action['priority'])) {
                $entry->parsed_priority = $action['priority'];
            }
        }
    }

    /**
     * Apply category assignment rule
     */
    protected function applyCategoryAssignment(RawScheduleEntry $entry, ParsingRule $rule): void
    {
        $text = $entry->raw_text . ' ' . $entry->parsed_title . ' ' . $entry->parsed_description;
        
        if ($rule->matchesPattern($text)) {
            $action = $rule->applyToText($text);
            
            if (isset($action['category'])) {
                $entry->ai_detected_category = $action['category'];
            }
        }
    }

    /**
     * Simulate AI parsing (placeholder for actual AI integration)
     */
    protected function simulateAiParsing(RawScheduleEntry $entry): void
    {
        // This is where you would integrate with an actual AI service
        // For now, we'll simulate some AI enhancements

        // Enhance title if missing
        if (!$entry->parsed_title && $entry->raw_text) {
            $entry->parsed_title = substr($entry->raw_text, 0, 50);
        }

        // Calculate AI confidence based on data completeness
        $confidence = 0.3;
        if ($entry->parsed_title) $confidence += 0.2;
        if ($entry->parsed_start_datetime) $confidence += 0.2;
        if ($entry->parsed_description) $confidence += 0.15;
        if ($entry->parsed_location) $confidence += 0.15;

        $entry->ai_confidence = min($confidence, 1.0);

        // Detect importance
        $entry->ai_detected_importance = $entry->parsed_priority ? ($entry->parsed_priority / 5) : 0.5;

        // Store AI parsed data
        $entry->ai_parsed_data = [
            'enhanced_title' => $entry->parsed_title,
            'suggested_duration' => 60,
            'confidence_factors' => [
                'title' => $entry->parsed_title ? 1 : 0,
                'date' => $entry->parsed_start_datetime ? 1 : 0,
                'description' => $entry->parsed_description ? 1 : 0,
            ]
        ];
    }

    /**
     * Convert entries to events
     */
    public function convertEntriesToEvents(Collection $entries): array
    {
        $results = [
            'total' => $entries->count(),
            'success' => 0,
            'failed' => 0,
            'manual_review' => 0,
        ];

        DB::beginTransaction();
        try {
            foreach ($entries as $entry) {
                try {
                    if ($entry->manual_review_required) {
                        $results['manual_review']++;
                        continue;
                    }

                    // Create event from entry
                    $event = $this->createEventFromEntry($entry);
                    
                    if ($event) {
                        $entry->markAsConverted($event->id);
                        $results['success']++;
                    } else {
                        $entry->markAsFailed(['reason' => 'Failed to create event']);
                        $results['failed']++;
                    }
                } catch (\Exception $e) {
                    $entry->markAsFailed(['error' => $e->getMessage()]);
                    $results['failed']++;
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }

        return $results;
    }

    /**
     * Create event from entry
     */
    protected function createEventFromEntry(RawScheduleEntry $entry): ?Event
    {
        // Validate required fields
        if (!$entry->parsed_title || !$entry->parsed_start_datetime) {
            return null;
        }

        $event = new Event([
            'user_id' => $entry->user_id,
            'title' => $entry->parsed_title,
            'description' => $entry->parsed_description,
            'start_datetime' => $entry->parsed_start_datetime,
            'end_datetime' => $entry->parsed_end_datetime ?? $entry->parsed_start_datetime->addHour(),
            'location' => $entry->parsed_location,
            'priority' => $entry->parsed_priority ?? 3,
            'status' => 'scheduled',
            'event_metadata' => [
                'imported' => true,
                'import_id' => $entry->import_id,
                'entry_id' => $entry->id,
                'ai_confidence' => $entry->ai_confidence,
            ]
        ]);

        $event->save();
        return $event;
    }

    /**
     * Update import statistics
     */
    protected function updateImportStatistics(RawScheduleImport $import): void
    {
        $entries = $import->entries;
        
        $import->successfully_processed = $entries->whereIn('processing_status', ['parsed', 'converted'])->count();
        $import->failed_records = $entries->where('processing_status', 'failed')->count();
        
        // Calculate AI confidence score
        $confidences = $entries->whereNotNull('ai_confidence')->pluck('ai_confidence');
        if ($confidences->count() > 0) {
            $import->ai_confidence_score = $confidences->avg();
        }
        
        $import->save();
    }
}