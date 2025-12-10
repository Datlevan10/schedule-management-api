<?php

namespace App\Services;

use App\Models\RawScheduleImport;
use App\Models\RawScheduleEntry;
use App\Models\Event;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Response;
use Carbon\Carbon;

class CsvExportService
{
    /**
     * Export raw import data to CSV
     */
    public function exportImportData(RawScheduleImport $import, string $format = 'original'): string
    {
        $entries = $import->entries()->orderBy('row_number')->get();
        
        switch ($format) {
            case 'original':
                return $this->exportOriginalFormat($entries);
            case 'parsed':
                return $this->exportParsedFormat($entries);
            case 'ai_enhanced':
                return $this->exportAiEnhancedFormat($entries);
            case 'vietnamese_school':
                return $this->exportVietnameseSchoolFormat($entries);
            default:
                return $this->exportStandardFormat($entries);
        }
    }

    /**
     * Export entries in original format
     */
    protected function exportOriginalFormat(Collection $entries): string
    {
        if ($entries->isEmpty()) {
            return '';
        }

        $csv = [];
        
        // Get headers from first entry's original data
        $firstEntry = $entries->first();
        $headers = array_keys($firstEntry->original_data ?? []);
        
        if (empty($headers)) {
            // Fallback to standard headers
            $headers = ['Title', 'Description', 'Start Date', 'End Date', 'Location', 'Priority'];
        }
        
        $csv[] = $headers;
        
        // Add data rows
        foreach ($entries as $entry) {
            $row = [];
            foreach ($headers as $header) {
                $row[] = $entry->original_data[$header] ?? '';
            }
            $csv[] = $row;
        }
        
        return $this->arrayToCsv($csv);
    }

    /**
     * Export entries in parsed format
     */
    protected function exportParsedFormat(Collection $entries): string
    {
        $csv = [];
        $headers = ['Title', 'Description', 'Start Date', 'End Date', 'Location', 'Priority', 'Confidence', 'Status'];
        $csv[] = $headers;
        
        foreach ($entries as $entry) {
            $csv[] = [
                $entry->parsed_title ?? '',
                $entry->parsed_description ?? '',
                $entry->parsed_start_datetime ? Carbon::parse($entry->parsed_start_datetime)->format('Y-m-d H:i:s') : '',
                $entry->parsed_end_datetime ? Carbon::parse($entry->parsed_end_datetime)->format('Y-m-d H:i:s') : '',
                $entry->parsed_location ?? '',
                $entry->parsed_priority ?? '',
                $entry->ai_confidence ?? '0',
                $entry->processing_status ?? 'pending'
            ];
        }
        
        return $this->arrayToCsv($csv);
    }

    /**
     * Export entries with AI enhancements
     */
    protected function exportAiEnhancedFormat(Collection $entries): string
    {
        $csv = [];
        $headers = [
            'Title', 
            'Description', 
            'Start Date', 
            'End Date', 
            'Location', 
            'Priority',
            'Category',
            'Keywords',
            'AI Confidence',
            'Importance Score',
            'Suggested Actions',
            'Original Text'
        ];
        $csv[] = $headers;
        
        foreach ($entries as $entry) {
            $csv[] = [
                $entry->parsed_title ?? $entry->original_data['title'] ?? '',
                $entry->parsed_description ?? $entry->original_data['description'] ?? '',
                $entry->parsed_start_datetime ? Carbon::parse($entry->parsed_start_datetime)->format('Y-m-d H:i:s') : '',
                $entry->parsed_end_datetime ? Carbon::parse($entry->parsed_end_datetime)->format('Y-m-d H:i:s') : '',
                $entry->parsed_location ?? $entry->original_data['location'] ?? '',
                $entry->parsed_priority ?? '3',
                $entry->ai_detected_category ?? '',
                implode('; ', $entry->detected_keywords ?? []),
                $entry->ai_confidence ?? '0',
                $entry->ai_detected_importance ?? '0.5',
                $this->generateAiSuggestions($entry),
                $entry->raw_text ?? ''
            ];
        }
        
        return $this->arrayToCsv($csv);
    }

    /**
     * Export in Vietnamese school schedule format
     */
    protected function exportVietnameseSchoolFormat(Collection $entries): string
    {
        $csv = [];
        $headers = ['Ngày', 'Lớp', 'Môn học', 'Giờ bắt đầu', 'Giờ kết thúc', 'Phòng', 'Ghi chú'];
        $csv[] = $headers;
        
        foreach ($entries as $entry) {
            $data = $entry->original_data;
            
            // Handle both formats
            $date = $data['ngay'] ?? $data['Ngày'] ?? '';
            $class = $data['lop'] ?? $data['Lớp'] ?? '';
            $subject = $data['mon_hoc'] ?? $data['Môn học'] ?? '';
            $startTime = $data['gio_bat_dau'] ?? $data['Giờ bắt đầu'] ?? '';
            $endTime = $data['gio_ket_thuc'] ?? $data['Giờ kết thúc'] ?? '';
            $room = $data['phong'] ?? $data['Phòng'] ?? '';
            $notes = $data['ghi_chu'] ?? $data['Ghi chú'] ?? '';
            
            // Try to parse from parsed data if original is missing
            if (empty($date) && $entry->parsed_start_datetime) {
                $date = Carbon::parse($entry->parsed_start_datetime)->format('d/m/Y');
            }
            
            $csv[] = [
                $date,
                $class,
                $subject,
                $startTime,
                $endTime,
                $room,
                $notes
            ];
        }
        
        return $this->arrayToCsv($csv);
    }

    /**
     * Export in standard format
     */
    protected function exportStandardFormat(Collection $entries): string
    {
        $csv = [];
        $headers = ['Title', 'Description', 'Start Date', 'End Date', 'Location', 'Priority'];
        $csv[] = $headers;
        
        foreach ($entries as $entry) {
            // Try to get data from parsed fields first, then original data
            $title = $entry->parsed_title ?? 
                    $entry->original_data['title'] ?? 
                    $entry->original_data['Title'] ?? 
                    $entry->original_data['mon_hoc'] ?? '';
            
            $description = $entry->parsed_description ?? 
                          $entry->original_data['description'] ?? 
                          $entry->original_data['Description'] ?? 
                          $entry->original_data['ghi_chu'] ?? '';
            
            $startDate = $entry->parsed_start_datetime ? 
                        Carbon::parse($entry->parsed_start_datetime)->format('Y-m-d H:i:s') : 
                        ($entry->original_data['Start Date'] ?? '');
            
            $endDate = $entry->parsed_end_datetime ? 
                      Carbon::parse($entry->parsed_end_datetime)->format('Y-m-d H:i:s') : 
                      ($entry->original_data['End Date'] ?? '');
            
            $location = $entry->parsed_location ?? 
                       $entry->original_data['location'] ?? 
                       $entry->original_data['Location'] ?? 
                       $entry->original_data['phong'] ?? '';
            
            $priority = $entry->parsed_priority ?? 
                       $entry->original_data['priority'] ?? 
                       $entry->original_data['Priority'] ?? '3';
            
            $csv[] = [$title, $description, $startDate, $endDate, $location, $priority];
        }
        
        return $this->arrayToCsv($csv);
    }

    /**
     * Export converted events to CSV
     */
    public function exportEvents(Collection $events, string $format = 'standard'): string
    {
        $csv = [];
        
        switch ($format) {
            case 'detailed':
                $headers = [
                    'ID', 'Title', 'Description', 'Start Date', 'End Date', 
                    'Location', 'Status', 'Priority', 'Category', 'User',
                    'Completion %', 'Created At', 'Updated At'
                ];
                break;
            case 'calendar':
                $headers = ['Subject', 'Start Date', 'Start Time', 'End Date', 'End Time', 'Location', 'Description'];
                break;
            default:
                $headers = ['Title', 'Description', 'Start Date', 'End Date', 'Location', 'Priority', 'Status'];
        }
        
        $csv[] = $headers;
        
        foreach ($events as $event) {
            switch ($format) {
                case 'detailed':
                    $csv[] = [
                        $event->id,
                        $event->title,
                        $event->description ?? '',
                        Carbon::parse($event->start_datetime)->format('Y-m-d H:i:s'),
                        Carbon::parse($event->end_datetime)->format('Y-m-d H:i:s'),
                        $event->location ?? '',
                        $event->status,
                        $event->priority,
                        $event->category->name ?? '',
                        $event->user->name ?? '',
                        $event->completion_percentage ?? 0,
                        $event->created_at->format('Y-m-d H:i:s'),
                        $event->updated_at->format('Y-m-d H:i:s')
                    ];
                    break;
                case 'calendar':
                    $start = Carbon::parse($event->start_datetime);
                    $end = Carbon::parse($event->end_datetime);
                    $csv[] = [
                        $event->title,
                        $start->format('Y-m-d'),
                        $start->format('H:i:s'),
                        $end->format('Y-m-d'),
                        $end->format('H:i:s'),
                        $event->location ?? '',
                        $event->description ?? ''
                    ];
                    break;
                default:
                    $csv[] = [
                        $event->title,
                        $event->description ?? '',
                        Carbon::parse($event->start_datetime)->format('Y-m-d H:i:s'),
                        Carbon::parse($event->end_datetime)->format('Y-m-d H:i:s'),
                        $event->location ?? '',
                        $event->priority,
                        $event->status
                    ];
            }
        }
        
        return $this->arrayToCsv($csv);
    }

    /**
     * Convert array to CSV string
     */
    protected function arrayToCsv(array $data): string
    {
        $output = fopen('php://temp', 'r+');
        
        // Add BOM for UTF-8 to ensure proper encoding in Excel
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
        
        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);
        
        return $csv;
    }

    /**
     * Generate AI suggestions for entry
     */
    protected function generateAiSuggestions(RawScheduleEntry $entry): string
    {
        $suggestions = [];
        
        if (!$entry->parsed_title) {
            $suggestions[] = 'Add title';
        }
        
        if (!$entry->parsed_start_datetime) {
            $suggestions[] = 'Set start time';
        }
        
        if ($entry->ai_confidence < 0.7) {
            $suggestions[] = 'Manual review recommended';
        }
        
        if (!$entry->parsed_priority) {
            $suggestions[] = 'Set priority level';
        }
        
        return implode('; ', $suggestions);
    }

    /**
     * Create CSV download response
     */
    public function createCsvResponse(string $csv, string $filename = 'export.csv')
    {
        return Response::make($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0'
        ]);
    }
}