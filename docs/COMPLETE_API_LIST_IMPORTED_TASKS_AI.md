# Complete API List: Imported Tasks & AI Analysis

## 📥 SECTION 1: APIs to Retrieve Imported Tasks

### 1.1 Get All Imported Events/Tasks
**GET** `/api/v1/events/imported`

Retrieves all events/tasks imported from CSV files for a specific user.

```bash
# Basic usage
curl -X GET 'http://127.0.0.1:8000/api/v1/events/imported?user_id=1'

# With filters
curl -X GET 'http://127.0.0.1:8000/api/v1/events/imported?user_id=1&min_confidence=0.7&sort_by=priority'
```

**Query Parameters:**
- `user_id` (required): User ID
- `import_id` (optional): Filter by specific import
- `min_confidence` (optional): Minimum AI confidence score
- `sort_by` (optional): created_at|start_datetime|priority|confidence
- `sort_order` (optional): asc|desc
- `per_page` (optional): Items per page
- `page` (optional): Page number

**Response:**
```json
{
  "status": "success",
  "message": "Imported events retrieved successfully",
  "data": [
    {
      "id": 35,
      "title": "Team Meeting",
      "description": "Weekly sync",
      "start_datetime": "2024-01-23T09:00:00Z",
      "end_datetime": "2024-01-23T10:00:00Z",
      "user_id": 1,
      "priority": 3,
      "event_metadata": {
        "imported": true,
        "import_id": 5,
        "entry_id": 23,
        "ai_confidence": "0.85"
      },
      "import_source": {
        "id": 5,
        "filename": "schedule.csv",
        "type": "csv",
        "imported_at": "2024-01-15T10:00:00Z",
        "total_records": 50
      }
    }
  ]
}
```

### 1.2 Get Imported Events Grouped by Source
**GET** `/api/v1/events/imported-grouped`

Groups imported events by their source file, useful for overview.

```bash
curl -X GET 'http://127.0.0.1:8000/api/v1/events/imported-grouped?user_id=1'
```

**Query Parameters:**
- `user_id` (required): User ID
- `include_events` (optional): Include full event data (default: false)

**Response:**
```json
{
  "status": "success",
  "message": "Imported events grouped by source",
  "total_imports": 3,
  "total_events": 45,
  "data": [
    {
      "import": {
        "id": 5,
        "filename": "schedule.csv",
        "source_type": "csv",
        "status": "completed",
        "ai_confidence": "0.75"
      },
      "events_count": 15,
      "statistics": {
        "scheduled": 10,
        "completed": 3,
        "high_priority": 5
      }
    }
  ]
}
```

### 1.3 Get Import Entries (Raw Parsed Data)
**GET** `/api/v1/schedule-imports/{import_id}/entries`

Gets the raw parsed entries from a specific import.

```bash
curl -X GET 'http://127.0.0.1:8000/api/v1/schedule-imports/5/entries?user_id=1'
```

**Query Parameters:**
- `user_id` (required): User ID
- `processing_status` (optional): pending|parsed|failed
- `min_confidence` (optional): Minimum confidence score
- `manual_review_required` (optional): true|false

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 23,
      "raw_text": "Team Meeting,Weekly sync,2024-01-23 09:00,Room A",
      "parsed_data": {
        "title": "Team Meeting",
        "description": "Weekly sync",
        "start_datetime": "2024-01-23 09:00:00",
        "location": "Room A"
      },
      "ai_analysis": {
        "confidence": 0.85,
        "category": "meeting",
        "priority_suggestion": 3
      },
      "status": {
        "processing": "parsed",
        "manual_review_required": false
      }
    }
  ]
}
```

### 1.4 Get All Imports for a User
**GET** `/api/v1/schedule-imports`

Lists all CSV imports for a specific user.

```bash
curl -X GET 'http://127.0.0.1:8000/api/v1/schedule-imports?user_id=1&status=completed'
```

**Query Parameters:**
- `user_id` (required): User ID
- `status` (optional): completed|pending|failed
- `import_type` (optional): file_upload|manual_input
- `from_date` (optional): Start date filter
- `to_date` (optional): End date filter
- `sort_by` (optional): created_at|updated_at
- `sort_order` (optional): asc|desc

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 5,
      "user_id": 1,
      "filename": "schedule.csv",
      "import_type": "file_upload",
      "source_type": "csv",
      "status": "completed",
      "total_records_found": 10,
      "successfully_processed": 10,
      "failed_records": 0,
      "ai_confidence_score": "0.75",
      "created_at": "2024-01-15T10:00:00Z"
    }
  ]
}
```

### 1.5 Get User's Events (Including Imported)
**GET** `/api/v1/events/user/{userId}`

Gets all events for a user, including imported ones.

```bash
curl -X GET 'http://127.0.0.1:8000/api/v1/events/user/1?manual_only=false'
```

**Query Parameters:**
- `manual_only` (optional): true to exclude imported events, false to include all
- `priority_min` (optional): Minimum priority filter
- `priority_max` (optional): Maximum priority filter

---

## 🤖 SECTION 2: APIs for AI Analysis

### 2.1 Select Events for AI Processing
**POST** `/api/v1/events/select-for-ai`

Select specific imported events and send them to AI for analysis.

```bash
curl -X POST 'http://127.0.0.1:8000/api/v1/events/select-for-ai?user_id=1' \
  -H "Content-Type: application/json" \
  -d '{
    "event_ids": [35, 36, 37, 40, 41],
    "ai_task": "optimize",
    "context": "Optimize my weekly schedule for productivity",
    "options": {
      "include_metadata": true,
      "include_ai_analysis": true
    }
  }'
```

**Request Body:**
- `event_ids` (required): Array of event IDs to analyze
- `ai_task` (required): Task type
  - `optimize` - Optimize schedule timing
  - `analyze` - Analyze patterns and insights
  - `reschedule` - Suggest rescheduling
  - `prioritize` - Re-prioritize tasks
  - `conflict_resolution` - Resolve conflicts
- `context` (optional): Additional context for AI
- `options` (optional): Processing options

**Response:**
```json
{
  "status": "success",
  "message": "Events selected for AI processing",
  "selection_id": "ai_selection_xyz123",
  "data": {
    "task": "optimize",
    "events_count": 5,
    "events_preview": [...],
    "ready_for_ai": true
  }
}
```

### 2.2 Process Import with AI
**POST** `/api/v1/schedule-imports/{import_id}/process`

Process an entire import with AI analysis.

```bash
curl -X POST 'http://127.0.0.1:8000/api/v1/schedule-imports/5/process?user_id=1' \
  -H "Content-Type: application/json" \
  -d '{
    "template_id": null
  }'
```

**Response:**
```json
{
  "success": true,
  "message": "Import processing started",
  "data": {
    "import_id": 5,
    "status": "processing",
    "entries_to_process": 10
  }
}
```

### 2.3 Convert Entries to Events with AI
**POST** `/api/v1/schedule-imports/{import_id}/convert`

Use AI to convert parsed entries into events.

```bash
curl -X POST 'http://127.0.0.1:8000/api/v1/schedule-imports/5/convert?user_id=1' \
  -H "Content-Type: application/json" \
  -d '{
    "min_confidence": 0.7,
    "entry_ids": []
  }'
```

**Request Body:**
- `min_confidence` (optional): Minimum confidence threshold (0.0-1.0)
- `entry_ids` (optional): Specific entry IDs to convert (empty = all)

**Response:**
```json
{
  "success": true,
  "data": {
    "total_processed": 10,
    "successfully_converted": 8,
    "failed_conversions": 1,
    "manual_review_required": 1,
    "event_ids": [35, 36, 37, 38, 39, 40, 41, 42]
  }
}
```

### 2.4 AI Schedule Analysis
**POST** `/api/v1/ai/schedule-analysis`

Comprehensive AI analysis of imported schedule.

```bash
curl -X POST 'http://127.0.0.1:8000/api/v1/ai/schedule-analysis' \
  -H "Content-Type: application/json" \
  -d '{
    "user_id": 1,
    "analysis_type": "optimization",
    "date_range": {
      "start": "2024-01-01",
      "end": "2024-01-31"
    },
    "options": {
      "include_suggestions": true,
      "check_conflicts": true,
      "optimize_priorities": true
    }
  }'
```

### 2.5 Get AI Analysis Results
**GET** `/api/v1/ai-analyses/{analysisId}`

Retrieve results from a specific AI analysis.

```bash
curl -X GET 'http://127.0.0.1:8000/api/v1/ai-analyses/123'
```

**Response:**
```json
{
  "id": 123,
  "user_id": 1,
  "analysis_type": "optimization",
  "status": "completed",
  "results": {
    "optimized_schedule": [...],
    "suggestions": [...],
    "conflicts_resolved": 3,
    "efficiency_improvement": "25%"
  },
  "created_at": "2024-01-15T10:00:00Z"
}
```

### 2.6 Get User's AI Analysis History
**GET** `/api/v1/ai-analyses/user/{userId}`

Get all AI analyses for a specific user.

```bash
curl -X GET 'http://127.0.0.1:8000/api/v1/ai-analyses/user/1?type=optimization'
```

**Query Parameters:**
- `type` (optional): Filter by analysis type
- `status` (optional): completed|processing|failed
- `from_date` (optional): Start date
- `to_date` (optional): End date

---

## 📊 SECTION 3: Statistics & Insights APIs

### 3.1 Import Statistics
**GET** `/api/v1/schedule-imports/statistics`

Get overall statistics for user's imports.

```bash
curl -X GET 'http://127.0.0.1:8000/api/v1/schedule-imports/statistics?user_id=1'
```

**Response:**
```json
{
  "success": true,
  "data": {
    "total_imports": 25,
    "completed_imports": 20,
    "failed_imports": 3,
    "pending_imports": 2,
    "total_entries": 250,
    "converted_entries": 180,
    "pending_review": 15,
    "average_confidence": 0.72
  }
}
```

### 3.2 AI Analytics Dashboard
**GET** `/api/v1/ai-analytics/user/{userId}`

Get comprehensive AI analytics for a user.

```bash
curl -X GET 'http://127.0.0.1:8000/api/v1/ai-analytics/user/1'
```

---

## 🔄 SECTION 4: Complete Workflow Examples

### Example 1: Basic Import → Retrieve → AI Analysis

```javascript
// Step 1: Import CSV
const formData = new FormData();
formData.append('file', csvFile);
formData.append('user_id', '1');

const importResponse = await fetch('/api/v1/schedule-imports', {
  method: 'POST',
  body: formData
});
const importResult = await importResponse.json();

// Step 2: Retrieve imported events
const eventsResponse = await fetch('/api/v1/events/imported?user_id=1');
const events = await eventsResponse.json();

// Step 3: Select for AI analysis
const eventIds = events.data.map(e => e.id);
const aiResponse = await fetch('/api/v1/events/select-for-ai?user_id=1', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    event_ids: eventIds,
    ai_task: 'optimize',
    context: 'Optimize schedule for productivity'
  })
});

const aiResult = await aiResponse.json();
console.log('AI Selection ID:', aiResult.selection_id);
```

### Example 2: User-Specific Retrieval and Analysis

```python
import requests

# For User ID 4
user_id = 4

# 1. Get all imported events for User 4
response = requests.get(
    'http://127.0.0.1:8000/api/v1/events/imported',
    params={'user_id': user_id}
)
imported_events = response.json()

# 2. Filter high-confidence events
high_confidence = [
    event for event in imported_events['data']
    if float(event['event_metadata']['ai_confidence']) >= 0.7
]

# 3. Send to AI for optimization
event_ids = [e['id'] for e in high_confidence]
ai_response = requests.post(
    f'http://127.0.0.1:8000/api/v1/events/select-for-ai?user_id={user_id}',
    json={
        'event_ids': event_ids,
        'ai_task': 'optimize',
        'context': 'Optimize User 4 schedule'
    }
)

result = ai_response.json()
print(f"AI Processing: {result['selection_id']}")
```

### Example 3: Batch Processing Multiple Imports

```javascript
async function processAllImportsForUser(userId) {
  // 1. Get all imports
  const imports = await fetch(`/api/v1/schedule-imports?user_id=${userId}&status=completed`);
  const importList = await imports.json();
  
  // 2. Process each import
  for (const imp of importList.data) {
    // Get events from this import
    const events = await fetch(`/api/v1/events/imported?user_id=${userId}&import_id=${imp.id}`);
    const eventData = await events.json();
    
    // Send to AI
    if (eventData.data.length > 0) {
      const eventIds = eventData.data.map(e => e.id);
      await fetch(`/api/v1/events/select-for-ai?user_id=${userId}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          event_ids: eventIds,
          ai_task: 'analyze',
          context: `Analyzing import: ${imp.filename}`
        })
      });
    }
  }
}
```

---

## 📋 Quick Reference Table

| Purpose | Method | Endpoint | Required Params |
|---------|--------|----------|-----------------|
| Get imported events | GET | `/events/imported` | user_id |
| Get grouped imports | GET | `/events/imported-grouped` | user_id |
| Get import entries | GET | `/schedule-imports/{id}/entries` | user_id |
| List all imports | GET | `/schedule-imports` | user_id |
| Send to AI | POST | `/events/select-for-ai` | user_id, event_ids, ai_task |
| Process import with AI | POST | `/schedule-imports/{id}/process` | user_id |
| Convert with AI | POST | `/schedule-imports/{id}/convert` | user_id |
| Get AI results | GET | `/ai-analyses/{id}` | - |
| Get user AI history | GET | `/ai-analyses/user/{userId}` | - |
| Get statistics | GET | `/schedule-imports/statistics` | user_id |

---

## 🔑 Key Points

1. **Always include user_id**: All APIs require user_id for data isolation
2. **AI Confidence Scores**: Use min_confidence parameter to filter quality data
3. **Selection ID**: Track AI processing with the returned selection_id
4. **Pagination**: Use per_page and page parameters for large datasets
5. **Error Handling**: Check 'status' or 'success' field in responses

---

**Version**: 1.0  
**Last Updated**: December 2025
**Documentation Status**: Complete