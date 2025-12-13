# CSV Task AI Analysis API Documentation

## Overview
This API provides AI analysis capabilities for tasks imported from CSV files. It can parse Vietnamese schedule data, optimize task scheduling, and prevent duplicate analysis through status tracking.

## Key Features
✅ AI analysis for CSV imported tasks  
✅ Vietnamese language support (time/date parsing)  
✅ Task locking to prevent re-analysis  
✅ Batch analysis for multiple imports  
✅ Real-time status tracking  

## Prerequisites
Each CSV entry already has AI analysis tracking fields:
- `is_available_for_analysis` - Boolean indicating if task can be analyzed
- `analysis_status` - Current status (pending/in_progress/completed/failed)
- `is_locked` - Prevents duplicate analysis

## Main API Endpoints

### 1. Analyze CSV Tasks
**POST** `/api/v1/csv-tasks/analyze`

Submit selected CSV tasks for AI analysis. The API will:
1. Check if tasks are available for analysis
2. Lock tasks to prevent duplicate processing
3. Send to AI for parsing and optimization
4. Return analysis ID for tracking

**Request Body:**
```json
{
  "user_id": 4,
  "entry_ids": [55, 56, 57],  // IDs from CSV entries
  "analysis_type": "both",     // "parse", "optimize", or "both"
  "target_date": "2025-12-15",
  "preferences": {
    "work_hours": "07:00-17:00",
    "break_duration": 15
  }
}
```

**Response:**
```json
{
  "status": "success",
  "message": "Tasks submitted for AI analysis",
  "data": {
    "analysis_id": 123,
    "batch_id": "uuid-here",
    "tasks_count": 3,
    "analysis_type": "both",
    "estimated_completion": "2025-12-13T15:30:00Z"
  }
}
```

**Error Response (if tasks already analyzed):**
```json
{
  "status": "error",
  "message": "Some tasks are already being analyzed or completed",
  "locked_entries": [55, 56]
}
```

### 2. Get Analysis Results
**GET** `/api/v1/csv-tasks/analysis-results/{analysisId}`

Retrieve the results of AI analysis.

**Response:**
```json
{
  "status": "success",
  "data": {
    "analysis_id": 123,
    "status": "completed",
    "tasks_analyzed": 3,
    "results": {
      "parsed_tasks": [
        {
          "id": 55,
          "original": {
            "ngay": "9/12/2025",
            "lop": "Lớp 6A2",
            "mon_hoc": "Toán",
            "gio_bat_dau": "7 giờ",
            "gio_ket_thuc": "7 giờ 45 phút"
          },
          "parsed": {
            "title": "Toán - Hằng đẳng thức",
            "description": "Môn toán về hằng đẳng thức đáng nhớ",
            "start_datetime": "2025-12-09T07:00:00",
            "end_datetime": "2025-12-09T07:45:00",
            "location": "E03",
            "priority": 3
          },
          "ai_confidence": 0.92,
          "analysis_status": "completed"
        }
      ],
      "optimized_schedule": {
        "schedule": [...],
        "conflicts_resolved": 0,
        "optimization_score": 0.85
      },
      "ai_reasoning": "Schedule optimized for morning classes...",
      "confidence_score": 0.90
    }
  }
}
```

### 3. Parse Vietnamese Schedule
**POST** `/api/v1/csv-tasks/parse-vietnamese`

Specialized endpoint for parsing Vietnamese time/date formats.

**Request Body:**
```json
{
  "user_id": 4,
  "entries": [
    {
      "id": 55,
      "original_data": {
        "ngay": "15/12/2025",
        "lop": "Lớp 10A",
        "mon_hoc": "Vật lý",
        "gio_bat_dau": "8 giờ 30 phút",
        "gio_ket_thuc": "10 giờ",
        "phong": "Lab-01",
        "ghi_chu": "Thực hành thí nghiệm quan trọng"
      }
    }
  ]
}
```

**Parsing Rules:**
- Date format: `dd/mm/yyyy` (e.g., "15/12/2025")
- Time format: `X giờ Y phút` (e.g., "8 giờ 30 phút" → 08:30)
- Priority detection: Keywords like "quan trọng" (important) → higher priority

### 4. Batch Analyze Multiple Imports
**POST** `/api/v1/csv-tasks/batch-analyze`

Analyze all entries from multiple CSV imports at once.

**Request Body:**
```json
{
  "user_id": 4,
  "import_ids": [12, 13, 14],
  "analysis_options": {
    "skip_completed": true,
    "priority_threshold": 3
  }
}
```

### 5. Check Analysis Status
**GET** `/api/v1/csv-tasks/analysis-status`

Get statistics about AI analysis for user's tasks.

**Query Parameters:**
- `user_id` (required)
- `import_id` (optional) - Filter by specific import

**Response:**
```json
{
  "status": "success",
  "data": {
    "statistics": {
      "total": 50,
      "pending": 30,
      "in_progress": 5,
      "completed": 15,
      "failed": 0,
      "available_for_analysis": 30
    },
    "recent_analyses": [
      {
        "id": 123,
        "status": "completed",
        "analysis_type": "both",
        "created_at": "2025-12-13T15:00:00Z",
        "confidence_score": 0.90
      }
    ]
  }
}
```

## Frontend Implementation Guide

### Step 1: Check Task Availability
Before showing tasks for selection, check the `is_available_for_analysis` flag:

```javascript
// Get CSV entries
const response = await fetch(
  `/api/v1/schedule-imports/${importId}/entries?user_id=${userId}`
);
const data = await response.json();

// Filter available tasks
const availableTasks = data.data.filter(
  task => task.ai_analysis.is_available_for_analysis
);

// Disable selection for locked/completed tasks
tasks.forEach(task => {
  if (!task.ai_analysis.is_available_for_analysis) {
    // Disable checkbox/button
    disableTaskSelection(task.id);
    // Show status icon
    showStatusIcon(task.ai_analysis.analysis_status);
  }
});
```

### Step 2: Submit for Analysis
```javascript
async function analyzeSelectedTasks(userId, selectedTaskIds) {
  const response = await fetch('/api/v1/csv-tasks/analyze', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      user_id: userId,
      entry_ids: selectedTaskIds,
      analysis_type: 'both',
      preferences: {
        work_hours: '07:00-17:00',
        break_duration: 15
      }
    })
  });

  const result = await response.json();
  
  if (result.status === 'success') {
    // Save analysis ID for tracking
    const analysisId = result.data.analysis_id;
    
    // Disable analyzed tasks in UI
    selectedTaskIds.forEach(id => disableTask(id));
    
    // Poll for results
    setTimeout(() => checkResults(analysisId), 3000);
  } else if (result.locked_entries) {
    alert(`${result.locked_entries.length} tasks are already analyzed`);
  }
}
```

### Step 3: Check Results
```javascript
async function checkResults(analysisId) {
  const response = await fetch(
    `/api/v1/csv-tasks/analysis-results/${analysisId}`
  );
  const result = await response.json();
  
  if (result.data.status === 'completed') {
    // Display results
    showAnalysisResults(result.data.results);
  } else if (result.data.status === 'processing') {
    // Continue polling
    setTimeout(() => checkResults(analysisId), 2000);
  } else if (result.data.status === 'failed') {
    // Handle error
    showError(result.data.error);
  }
}
```

## Task Status Flow
```
pending → in_progress → completed
           ↓              ↓
         failed        (locked)
           ↓
      (can retry)
```

## Vietnamese Language Support

The API automatically handles Vietnamese formats:

| Vietnamese | Parsed | Example |
|------------|--------|---------|
| "7 giờ" | 07:00 | Morning class |
| "13 giờ 30 phút" | 13:30 | Afternoon class |
| "9/12/2025" | 2025-12-09 | Date format |
| "quan trọng" | priority: 4 | Important task |
| "thi" | priority: 4 | Exam |

## Complete Example

```javascript
class CsvAiAnalysisManager {
  constructor(userId) {
    this.userId = userId;
    this.apiBase = '/api/v1';
  }

  async loadAndAnalyzeTasks(importId) {
    // 1. Get tasks
    const tasks = await this.getCsvTasks(importId);
    
    // 2. Filter available tasks
    const available = tasks.filter(t => t.ai_analysis.is_available_for_analysis);
    
    if (available.length === 0) {
      console.log('All tasks already analyzed');
      return;
    }
    
    // 3. Submit for analysis
    const taskIds = available.map(t => t.id);
    const analysis = await this.analyzeTasks(taskIds);
    
    // 4. Wait for results
    const results = await this.waitForResults(analysis.analysis_id);
    
    // 5. Display results
    this.displayResults(results);
  }

  async getCsvTasks(importId) {
    const res = await fetch(
      `${this.apiBase}/schedule-imports/${importId}/entries?user_id=${this.userId}`
    );
    const data = await res.json();
    return data.data;
  }

  async analyzeTasks(taskIds) {
    const res = await fetch(`${this.apiBase}/csv-tasks/analyze`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        user_id: this.userId,
        entry_ids: taskIds,
        analysis_type: 'both'
      })
    });
    const data = await res.json();
    return data.data;
  }

  async waitForResults(analysisId, maxAttempts = 10) {
    for (let i = 0; i < maxAttempts; i++) {
      const res = await fetch(
        `${this.apiBase}/csv-tasks/analysis-results/${analysisId}`
      );
      const data = await res.json();
      
      if (data.data.status === 'completed') {
        return data.data.results;
      }
      
      await new Promise(resolve => setTimeout(resolve, 2000));
    }
    throw new Error('Analysis timeout');
  }

  displayResults(results) {
    console.log('Analysis complete!');
    console.log(`Confidence: ${results.confidence_score}`);
    console.log(`Tasks parsed: ${results.parsed_tasks.length}`);
    
    results.parsed_tasks.forEach(task => {
      console.log(`- ${task.parsed.title} at ${task.parsed.start_datetime}`);
    });
  }
}

// Usage
const manager = new CsvAiAnalysisManager(4);
manager.loadAndAnalyzeTasks(12);
```

## Testing

Run the test script:
```bash
./test-csv-ai-analysis.sh
```

## Error Handling

Common errors and solutions:

| Error | Cause | Solution |
|-------|-------|----------|
| 409 - Tasks locked | Already being analyzed | Wait or check status |
| 404 - Not found | Invalid entry IDs | Verify task IDs exist |
| 400 - Invalid request | Missing parameters | Check required fields |
| 500 - Server error | AI service issue | Retry after delay |

## Performance Notes

- Tasks are locked immediately to prevent duplicate analysis
- Analysis typically takes 5-30 seconds depending on task count
- Poll every 2-3 seconds for results
- Cache results to avoid repeated API calls
- Batch analyze for better performance with multiple imports