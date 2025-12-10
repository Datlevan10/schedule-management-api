# Frontend API Reference - Schedule Management System

## 📋 Complete API List for Frontend Integration

### 1. CSV Import APIs

#### 1.1 Upload CSV File
```javascript
POST /api/v1/schedule-imports
Content-Type: multipart/form-data

FormData:
- file: CSV file
- import_type: "file_upload"
- source_type: "csv"
- user_id: 1
- template_id: (optional)

Response:
{
  "success": true,
  "data": {
    "id": 5,
    "status": "completed",
    "total_records_found": 10,
    "ai_confidence_score": "0.75"
  }
}
```

#### 1.2 Import Manual Text (For AI to Parse)
```javascript
POST /api/v1/schedule-imports
Content-Type: application/json

{
  "import_type": "manual_input",
  "source_type": "manual",
  "raw_content": "Meeting tomorrow at 2pm with John",
  "user_id": 1
}
```

#### 1.3 List All Imports
```javascript
GET /api/v1/schedule-imports?user_id=1&per_page=10&sort_by=created_at&sort_order=desc

Query Params:
- user_id: 1
- status: completed|pending|failed
- import_type: file_upload|manual_input
- from_date: 2024-01-01
- to_date: 2024-12-31
```

#### 1.4 Get Import Details
```javascript
GET /api/v1/schedule-imports/{id}?user_id=1

Response includes AI analysis metadata
```

#### 1.5 Get Import Entries (Parsed Data)
```javascript
GET /api/v1/schedule-imports/{id}/entries?user_id=1

Query Params:
- processing_status: pending|parsed|failed
- min_confidence: 0.7
- manual_review_required: true|false
```

### 2. AI Processing & Analysis APIs

#### 2.1 Process Import with AI
```javascript
POST /api/v1/schedule-imports/{id}/process?user_id=1

{
  "template_id": null // optional template for parsing rules
}
```

#### 2.2 Convert Entries to Events (AI Decision)
```javascript
POST /api/v1/schedule-imports/{id}/convert?user_id=1

{
  "min_confidence": 0.5,  // AI confidence threshold
  "entry_ids": []          // specific entries or all
}

Response:
{
  "success": true,
  "data": {
    "total_processed": 10,
    "successfully_converted": 8,
    "failed_conversions": 1,
    "manual_review_required": 1
  }
}
```

#### 2.3 Update Entry with Manual Corrections
```javascript
PATCH /api/v1/schedule-imports/entries/{entry_id}?user_id=1

{
  "parsed_title": "Corrected Title",
  "parsed_priority": 5,
  "manual_review_notes": "AI misunderstood the date",
  "manual_review_required": false
}
```

#### 2.4 Get Import Statistics
```javascript
GET /api/v1/schedule-imports/statistics?user_id=1

Response:
{
  "success": true,
  "data": {
    "total_imports": 25,
    "pending_imports": 2,
    "completed_imports": 20,
    "failed_imports": 3,
    "total_entries": 250,
    "converted_entries": 180,
    "pending_review": 15,
    "average_confidence": 0.72
  }
}
```

### 3. CSV Export APIs (For AI Analysis)

#### 3.1 Export Import Data as CSV
```javascript
GET /api/v1/schedule-imports/{id}/export?user_id=1&format=ai_enhanced

Formats:
- original: As imported
- parsed: After parsing
- standard: Calendar format
- ai_enhanced: With AI metadata
- vietnamese_school: School schedule

Returns: CSV file download
```

#### 3.2 Export Events as CSV
```javascript
GET /api/v1/schedule-imports/{id}/export-events?user_id=1&format=detailed

Formats:
- standard: Basic event data
- detailed: All fields including AI scores
- calendar: For calendar apps
```

#### 3.3 Batch Export Multiple Imports
```javascript
POST /api/v1/schedule-imports/export-batch?user_id=1

{
  "import_ids": [5, 7, 9],
  "format": "ai_enhanced"
}
```

#### 3.4 Preview Export (JSON Format)
```javascript
GET /api/v1/schedule-imports/{id}/preview?user_id=1&format=ai_enhanced&limit=5

Response:
{
  "success": true,
  "data": {
    "format": "ai_enhanced",
    "total_entries": 10,
    "entries": [...],
    "available_formats": {...}
  }
}
```

### 4. AI Schedule Analysis APIs

#### 4.1 Analyze Schedule with AI
```javascript
POST /api/v1/ai/schedule-analysis

{
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
}
```

#### 4.2 Get AI Suggestions
```javascript
POST /api/v1/ai/suggestions

{
  "user_id": 1,
  "context": "schedule_optimization",
  "current_schedule": [...],
  "preferences": {...}
}
```

#### 4.3 Validate Schedule with AI
```javascript
POST /api/v1/ai/validate-schedule

{
  "entries": [...],
  "rules": {
    "check_overlaps": true,
    "validate_dates": true,
    "check_priorities": true
  }
}
```

### 5. Manual Task Templates APIs

#### 5.1 Get Task Templates
```javascript
GET /api/v1/manual-tasks/templates?language=vi

Response includes AI instructions for each template
```

#### 5.2 Create Task with Template
```javascript
POST /api/v1/manual-tasks

{
  "use_template": true,
  "template_type": "meeting",
  "template_variables": {
    "subject": "Sprint Planning",
    "participants": "Dev Team",
    "objectives": "Plan Q1"
  },
  "ai_execution_enabled": true,
  "ai_execution_instructions": "Send invites and prepare agenda"
}
```

### 6. Event Management APIs

#### 6.1 Get Events (Including Imported)
```javascript
GET /api/v1/events?user_id=1&sort_by=created_at&sort_order=desc

Filter by metadata to get imported events:
- event_metadata.imported=true
- event_metadata.import_id={id}
```

#### 6.2 Get User Events with AI Priority
```javascript
GET /api/v1/events/user/{userId}?manual_only=true&priority_min=3
```

#### 6.3 Get All Imported Events
```javascript
GET /api/v1/events/imported?user_id=1

Query Parameters:
- user_id (required): User ID
- import_id (optional): Filter by specific import
- min_confidence (optional): Minimum AI confidence score
- sort_by (optional): created_at|start_datetime|priority|confidence
- sort_order (optional): asc|desc
- per_page (optional): Number of items per page

Response:
{
  "status": "success",
  "message": "Imported events retrieved successfully",
  "data": [
    {
      "id": 35,
      "title": "Team Meeting",
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
        "imported_at": "2024-01-15 10:00:00",
        "total_records": 50
      }
    }
  ]
}
```

#### 6.4 Get Imported Events Grouped by Source
```javascript
GET /api/v1/events/imported-grouped?user_id=1

Query Parameters:
- user_id (required): User ID
- include_events (optional): Include full event data (default: false)

Response:
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
        "completed": 5,
        "high_priority": 3
      }
    }
  ]
}
```

#### 6.5 Select Events for AI Processing
```javascript
POST /api/v1/events/select-for-ai?user_id=1

Request Body:
{
  "event_ids": [35, 36, 37],
  "ai_task": "optimize", // optimize|analyze|reschedule|prioritize|conflict_resolution
  "context": "Schedule optimization for next week",
  "options": {
    "include_metadata": true,
    "include_ai_analysis": true,
    "include_related_events": false
  }
}

Response:
{
  "status": "success",
  "message": "Events selected for AI processing",
  "selection_id": "ai_selection_xyz123",
  "data": {
    "task": "optimize",
    "events_count": 3,
    "events_preview": [...],
    "ready_for_ai": true
  }
}
```

## 🤖 Frontend AI Integration Workflow

### Complete CSV Import → AI Analysis → Export Flow

```javascript
// Step 1: Upload CSV
async function uploadCSV(file) {
  const formData = new FormData();
  formData.append('file', file);
  formData.append('import_type', 'file_upload');
  formData.append('source_type', 'csv');
  formData.append('user_id', '1');
  
  const response = await fetch('/api/v1/schedule-imports', {
    method: 'POST',
    body: formData
  });
  
  return await response.json();
}

// Step 2: Get AI Analysis Results
async function getAIAnalysis(importId) {
  // Get entries with AI confidence scores
  const entries = await fetch(`/api/v1/schedule-imports/${importId}/entries?user_id=1`);
  const data = await entries.json();
  
  // Analyze confidence distribution
  const analysis = {
    highConfidence: data.data.filter(e => e.ai_analysis.confidence > 0.8),
    mediumConfidence: data.data.filter(e => e.ai_analysis.confidence > 0.5 && e.ai_analysis.confidence <= 0.8),
    lowConfidence: data.data.filter(e => e.ai_analysis.confidence <= 0.5),
    requiresReview: data.data.filter(e => e.status.manual_review_required)
  };
  
  return analysis;
}

// Step 3: Convert with AI
async function convertWithAI(importId, minConfidence = 0.7) {
  const response = await fetch(`/api/v1/schedule-imports/${importId}/convert?user_id=1`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ min_confidence: minConfidence })
  });
  
  return await response.json();
}

// Step 4: Export for Further Analysis
async function exportForAnalysis(importId) {
  // Get AI-enhanced CSV
  const response = await fetch(`/api/v1/schedule-imports/${importId}/export?user_id=1&format=ai_enhanced`);
  const blob = await response.blob();
  
  // Process CSV for analysis
  const text = await blob.text();
  return parseCSV(text);
}

// Step 5: Display AI Summary
function displayAISummary(analysis, conversionResults) {
  return {
    summary: {
      totalEntries: analysis.highConfidence.length + analysis.mediumConfidence.length + analysis.lowConfidence.length,
      successRate: `${(analysis.highConfidence.length / totalEntries * 100).toFixed(1)}%`,
      conversionRate: `${(conversionResults.data.successfully_converted / conversionResults.data.total_processed * 100).toFixed(1)}%`,
      requiresAttention: analysis.requiresReview.length,
      recommendations: generateRecommendations(analysis)
    },
    details: {
      confidenceDistribution: {
        high: analysis.highConfidence.length,
        medium: analysis.mediumConfidence.length,
        low: analysis.lowConfidence.length
      },
      conversion: conversionResults.data,
      issues: identifyIssues(analysis)
    }
  };
}

function generateRecommendations(analysis) {
  const recommendations = [];
  
  if (analysis.lowConfidence.length > 0) {
    recommendations.push({
      type: 'warning',
      message: `${analysis.lowConfidence.length} entries need manual review`,
      action: 'Review and correct low confidence entries'
    });
  }
  
  if (analysis.requiresReview.length > 0) {
    recommendations.push({
      type: 'action',
      message: `${analysis.requiresReview.length} entries flagged for review`,
      action: 'Check flagged entries for accuracy'
    });
  }
  
  return recommendations;
}
```

## 📊 AI Result Summarization Components

### 1. Confidence Score Analysis
```javascript
function analyzeConfidenceScores(entries) {
  const scores = entries.map(e => parseFloat(e.ai_analysis.confidence));
  
  return {
    average: (scores.reduce((a, b) => a + b, 0) / scores.length).toFixed(2),
    min: Math.min(...scores),
    max: Math.max(...scores),
    distribution: {
      excellent: scores.filter(s => s >= 0.9).length,  // 90-100%
      good: scores.filter(s => s >= 0.7 && s < 0.9).length,  // 70-89%
      fair: scores.filter(s => s >= 0.5 && s < 0.7).length,  // 50-69%
      poor: scores.filter(s => s < 0.5).length  // < 50%
    }
  };
}
```

### 2. Pattern Detection
```javascript
function detectPatterns(entries) {
  return {
    commonIssues: findCommonIssues(entries),
    timePatterns: analyzeTimePatterns(entries),
    locationClusters: findLocationClusters(entries),
    priorityDistribution: analyzePriorities(entries)
  };
}
```

### 3. CSV Reading for Analysis
```javascript
async function readAndAnalyzeCSV(importId) {
  // Get CSV in AI-enhanced format
  const response = await fetch(`/api/v1/schedule-imports/${importId}/export?user_id=1&format=ai_enhanced`);
  const csvText = await response.text();
  
  // Parse CSV
  const lines = csvText.split('\n');
  const headers = lines[0].split(',');
  const data = lines.slice(1).map(line => {
    const values = line.split(',');
    return headers.reduce((obj, header, index) => {
      obj[header] = values[index];
      return obj;
    }, {});
  });
  
  // Analyze
  return {
    totalRecords: data.length,
    fieldsAnalysis: analyzeFields(data),
    aiMetrics: extractAIMetrics(data),
    dataQuality: assessDataQuality(data),
    suggestions: generateAISuggestions(data)
  };
}

function extractAIMetrics(data) {
  return {
    averageConfidence: calculateAverage(data, 'AI Confidence'),
    categoriesDetected: uniqueValues(data, 'Category'),
    keywordsExtracted: extractAllKeywords(data, 'Keywords'),
    suggestedActions: collectSuggestions(data, 'Suggested Actions')
  };
}
```

## 🎯 Frontend Display Components

### AI Analysis Dashboard
```javascript
const AIAnalysisDashboard = {
  // Import Summary Widget
  ImportSummary: {
    endpoint: '/api/v1/schedule-imports/statistics',
    displays: ['total_imports', 'average_confidence', 'conversion_rate']
  },
  
  // Confidence Chart
  ConfidenceChart: {
    endpoint: '/api/v1/schedule-imports/{id}/entries',
    chartType: 'pie',
    data: 'confidence_distribution'
  },
  
  // Issues List
  IssuesList: {
    endpoint: '/api/v1/schedule-imports/{id}/entries?manual_review_required=true',
    displays: 'entries_requiring_review'
  },
  
  // Export Options
  ExportOptions: {
    formats: [
      { value: 'standard', label: 'Standard CSV' },
      { value: 'ai_enhanced', label: 'AI Enhanced (with metadata)' },
      { value: 'vietnamese_school', label: 'Vietnamese School Format' }
    ]
  }
};
```

## 📥 Working with Imported Events

### Frontend Flow for Imported Events

```javascript
// 1. Get all imported events for a user
async function getImportedEvents(userId) {
  const response = await fetch(
    `/api/v1/events/imported?user_id=${userId}&min_confidence=0.5`
  );
  const data = await response.json();
  
  return data.data.map(event => ({
    ...event,
    confidenceScore: parseFloat(event.event_metadata.ai_confidence),
    importSource: event.import_source.filename,
    needsReview: parseFloat(event.event_metadata.ai_confidence) < 0.7
  }));
}

// 2. Get events grouped by import source
async function getEventsByImportSource(userId) {
  const response = await fetch(
    `/api/v1/events/imported-grouped?user_id=${userId}`
  );
  const data = await response.json();
  
  // Display grouped data in UI
  return data.data.map(group => ({
    source: group.import.filename,
    date: group.import.imported_at,
    eventsCount: group.events_count,
    statistics: group.statistics,
    confidence: parseFloat(group.import.ai_confidence)
  }));
}

// 3. Select specific events for AI processing
async function selectEventsForAI(eventIds, task = 'optimize') {
  const response = await fetch('/api/v1/events/select-for-ai?user_id=1', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      event_ids: eventIds,
      ai_task: task,
      context: 'User requested schedule optimization',
      options: {
        include_metadata: true,
        include_ai_analysis: true
      }
    })
  });
  
  const result = await response.json();
  
  // Use selection_id to track AI processing
  return result.selection_id;
}

// 4. Complete workflow example
async function processImportedEventsWithAI() {
  // Step 1: Get imported events
  const importedEvents = await getImportedEvents(1);
  
  // Step 2: Filter low-confidence events for review
  const needsReview = importedEvents.filter(e => e.needsReview);
  
  // Step 3: Select high-confidence events for AI optimization
  const highConfidence = importedEvents
    .filter(e => e.confidenceScore >= 0.7)
    .map(e => e.id);
  
  // Step 4: Send to AI for processing
  const selectionId = await selectEventsForAI(highConfidence, 'optimize');
  
  // Step 5: Track AI processing status
  console.log('AI processing started with selection:', selectionId);
  
  return {
    totalImported: importedEvents.length,
    needsReview: needsReview.length,
    sentToAI: highConfidence.length,
    selectionId
  };
}
```

### UI Components for Imported Events

```javascript
// React component example
const ImportedEventsManager = () => {
  const [importedEvents, setImportedEvents] = useState([]);
  const [groupedEvents, setGroupedEvents] = useState([]);
  const [selectedEvents, setSelectedEvents] = useState([]);
  
  // Load imported events on mount
  useEffect(() => {
    loadImportedEvents();
  }, []);
  
  const loadImportedEvents = async () => {
    // Get all imported events
    const events = await getImportedEvents(userId);
    setImportedEvents(events);
    
    // Get grouped view
    const grouped = await getEventsByImportSource(userId);
    setGroupedEvents(grouped);
  };
  
  const handleSelectForAI = async () => {
    if (selectedEvents.length === 0) return;
    
    const selectionId = await selectEventsForAI(selectedEvents, 'optimize');
    alert(`Events sent to AI for processing. Selection ID: ${selectionId}`);
  };
  
  return (
    <div>
      <h2>Imported Events from CSV Files</h2>
      
      {/* Grouped View */}
      <div className="import-sources">
        {groupedEvents.map(group => (
          <div key={group.source} className="import-group">
            <h3>{group.source}</h3>
            <p>Events: {group.eventsCount}</p>
            <p>AI Confidence: {(group.confidence * 100).toFixed(0)}%</p>
            <div className="statistics">
              <span>Scheduled: {group.statistics.scheduled}</span>
              <span>High Priority: {group.statistics.high_priority}</span>
            </div>
          </div>
        ))}
      </div>
      
      {/* Event List with Selection */}
      <div className="events-list">
        {importedEvents.map(event => (
          <div key={event.id} className="event-item">
            <input
              type="checkbox"
              checked={selectedEvents.includes(event.id)}
              onChange={(e) => {
                if (e.target.checked) {
                  setSelectedEvents([...selectedEvents, event.id]);
                } else {
                  setSelectedEvents(selectedEvents.filter(id => id !== event.id));
                }
              }}
            />
            <div>
              <h4>{event.title}</h4>
              <p>From: {event.importSource}</p>
              <p>Confidence: {(event.confidenceScore * 100).toFixed(0)}%</p>
              {event.needsReview && <span className="warning">Needs Review</span>}
            </div>
          </div>
        ))}
      </div>
      
      <button 
        onClick={handleSelectForAI}
        disabled={selectedEvents.length === 0}
      >
        Send {selectedEvents.length} Events to AI for Processing
      </button>
    </div>
  );
};
```

## 🔄 Real-time AI Processing Status

```javascript
// WebSocket or Polling for AI Processing Status
async function trackAIProcessing(importId) {
  const checkStatus = async () => {
    const response = await fetch(`/api/v1/schedule-imports/${importId}?user_id=1`);
    const data = await response.json();
    
    return {
      status: data.data.status,
      progress: {
        total: data.data.total_records_found,
        processed: data.data.successfully_processed,
        failed: data.data.failed_records,
        percentage: (data.data.successfully_processed / data.data.total_records_found * 100)
      },
      aiConfidence: data.data.ai_confidence_score
    };
  };
  
  // Poll every 2 seconds
  const interval = setInterval(async () => {
    const status = await checkStatus();
    updateUI(status);
    
    if (status.status === 'completed' || status.status === 'failed') {
      clearInterval(interval);
      showFinalResults(status);
    }
  }, 2000);
}
```

## 📈 Metrics & KPIs for Frontend

```javascript
const AIMetrics = {
  // Import Success Rate
  importSuccessRate: async () => {
    const stats = await fetch('/api/v1/schedule-imports/statistics?user_id=1');
    const data = await stats.json();
    return (data.data.completed_imports / data.data.total_imports * 100).toFixed(1);
  },
  
  // AI Accuracy Score
  aiAccuracyScore: async () => {
    const stats = await fetch('/api/v1/schedule-imports/statistics?user_id=1');
    const data = await stats.json();
    return (parseFloat(data.data.average_confidence) * 100).toFixed(1);
  },
  
  // Conversion Efficiency
  conversionEfficiency: async () => {
    const stats = await fetch('/api/v1/schedule-imports/statistics?user_id=1');
    const data = await stats.json();
    return (data.data.converted_entries / data.data.total_entries * 100).toFixed(1);
  },
  
  // Manual Review Rate
  manualReviewRate: async () => {
    const stats = await fetch('/api/v1/schedule-imports/statistics?user_id=1');
    const data = await stats.json();
    return (data.data.pending_review / data.data.total_entries * 100).toFixed(1);
  }
};
```

---

## 🚀 Quick Start for Frontend Developers

1. **Import CSV**: Use `/api/v1/schedule-imports` POST
2. **Check AI Analysis**: Use `/api/v1/schedule-imports/{id}/entries` GET
3. **Convert to Events**: Use `/api/v1/schedule-imports/{id}/convert` POST
4. **Export Results**: Use `/api/v1/schedule-imports/{id}/export?format=ai_enhanced` GET
5. **Display Summary**: Use the statistics and analysis functions above

**Version**: 1.0  
**Last Updated**: December 2024