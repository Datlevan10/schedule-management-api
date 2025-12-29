<?php

// Test script to verify active tasks logic
// Run with: php artisan tinker test-active-tasks-logic.php

$userId = 4; // Adjust this to a valid user ID

echo "\n========== Active Tasks Logic Test ==========\n";
echo "Testing for User ID: $userId\n\n";

// 1. Count Manual Tasks (Events)
echo "1. MANUAL TASKS (Events table):\n";
echo "--------------------------------\n";

$totalEvents = \App\Models\Event::where('user_id', $userId)->count();
echo "  Total events: $totalEvents\n";

$aiAnalyzedEvents = \App\Models\Event::where('user_id', $userId)
    ->where('ai_analysis_status', 'completed')
    ->count();
echo "  AI-analyzed events: $aiAnalyzedEvents\n";

$activeManualTasks = \App\Models\Event::where('user_id', $userId)
    ->where('ai_analysis_status', 'completed')
    ->whereNotIn('status', ['completed', 'cancelled', 'failed'])
    ->count();
echo "  Active AI-analyzed events: $activeManualTasks\n";

// Show breakdown by status
$eventsByStatus = \App\Models\Event::where('user_id', $userId)
    ->where('ai_analysis_status', 'completed')
    ->selectRaw('status, COUNT(*) as count')
    ->groupBy('status')
    ->pluck('count', 'status')
    ->toArray();
echo "  Breakdown by status:\n";
foreach ($eventsByStatus as $status => $count) {
    echo "    - $status: $count\n";
}

// Show all events status (including non-AI analyzed)
$allEventsByStatus = \App\Models\Event::where('user_id', $userId)
    ->selectRaw('status, ai_analysis_status, COUNT(*) as count')
    ->groupBy('status', 'ai_analysis_status')
    ->get();
echo "\n  All events breakdown:\n";
foreach ($allEventsByStatus as $row) {
    $aiStatus = $row->ai_analysis_status ?: 'not_analyzed';
    echo "    - Status: {$row->status}, AI: {$aiStatus}, Count: {$row->count}\n";
}

// 2. Count CSV Tasks (RawScheduleEntries)
echo "\n2. CSV TASKS (RawScheduleEntries table):\n";
echo "-----------------------------------------\n";

$totalEntries = \App\Models\RawScheduleEntry::where('user_id', $userId)->count();
echo "  Total entries: $totalEntries\n";

$aiAnalyzedEntries = \App\Models\RawScheduleEntry::where('user_id', $userId)
    ->where('ai_analysis_status', 'completed')
    ->count();
echo "  AI-analyzed entries: $aiAnalyzedEntries\n";

$activeCsvTasks = \App\Models\RawScheduleEntry::where('user_id', $userId)
    ->where('ai_analysis_status', 'completed')
    ->where(function($query) {
        $query->where('processing_status', '!=', 'failed')
            ->where(function($q) {
                $q->whereNull('conversion_status')
                  ->orWhere('conversion_status', '!=', 'failed');
            });
    })
    ->whereNull('converted_event_id')
    ->count();
echo "  Active AI-analyzed entries: $activeCsvTasks\n";

// Show breakdown by processing status
$entriesByProcessing = \App\Models\RawScheduleEntry::where('user_id', $userId)
    ->where('ai_analysis_status', 'completed')
    ->selectRaw('processing_status, COUNT(*) as count')
    ->groupBy('processing_status')
    ->pluck('count', 'processing_status')
    ->toArray();
echo "\n  AI-analyzed entries by processing_status:\n";
foreach ($entriesByProcessing as $status => $count) {
    $statusLabel = $status ?: 'null';
    echo "    - $statusLabel: $count\n";
}

// Show breakdown by conversion status
$entriesByConversion = \App\Models\RawScheduleEntry::where('user_id', $userId)
    ->where('ai_analysis_status', 'completed')
    ->selectRaw('conversion_status, COUNT(*) as count')
    ->groupBy('conversion_status')
    ->pluck('count', 'conversion_status')
    ->toArray();
echo "\n  AI-analyzed entries by conversion_status:\n";
foreach ($entriesByConversion as $status => $count) {
    $statusLabel = $status ?: 'null';
    echo "    - $statusLabel: $count\n";
}

// Show all entries breakdown
$allEntriesBreakdown = \App\Models\RawScheduleEntry::where('user_id', $userId)
    ->selectRaw('ai_analysis_status, processing_status, conversion_status, COUNT(*) as count')
    ->groupBy('ai_analysis_status', 'processing_status', 'conversion_status')
    ->get();
echo "\n  All entries complete breakdown:\n";
foreach ($allEntriesBreakdown as $row) {
    $aiStatus = $row->ai_analysis_status ?: 'not_analyzed';
    $procStatus = $row->processing_status ?: 'null';
    $convStatus = $row->conversion_status ?: 'null';
    echo "    - AI: {$aiStatus}, Processing: {$procStatus}, Conversion: {$convStatus}, Count: {$row->count}\n";
}

// Count converted entries
$convertedEntries = \App\Models\RawScheduleEntry::where('user_id', $userId)
    ->whereNotNull('converted_event_id')
    ->count();
echo "\n  Already converted to events: $convertedEntries\n";

// 3. Total Active Tasks
echo "\n3. TOTAL ACTIVE TASKS (Summary):\n";
echo "==================================\n";
$totalActiveTasks = $activeManualTasks + $activeCsvTasks;
echo "  Manual tasks (AI-analyzed, active): $activeManualTasks\n";
echo "  CSV tasks (AI-analyzed, active): $activeCsvTasks\n";
echo "  ----------------------------------\n";
echo "  TOTAL ACTIVE TASKS: $totalActiveTasks\n";

echo "\n4. LOGIC VERIFICATION:\n";
echo "======================\n";
echo "  Active tasks criteria:\n";
echo "  - Must have ai_analysis_status = 'completed'\n";
echo "  - For Events: status NOT IN ('completed', 'cancelled', 'failed')\n";
echo "  - For RawScheduleEntries:\n";
echo "    * processing_status != 'failed'\n";
echo "    * conversion_status is NULL or != 'failed'\n";
echo "    * converted_event_id is NULL (not double-counted)\n";

echo "\n========== End of Test ==========\n\n";