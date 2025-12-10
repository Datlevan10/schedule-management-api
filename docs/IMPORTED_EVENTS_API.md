# Imported Events API Documentation

## Overview
These APIs allow you to retrieve, filter, and select events/tasks that were imported from CSV files or other sources. This enables Frontend applications to display imported data and send selected events to AI for processing.

## Available Endpoints

### 1. Get All Imported Events
**GET** `/api/v1/events/imported`

Retrieves all events that were imported from external sources (CSV files, etc.)

#### Parameters
- `user_id` (required): User ID
- `import_id` (optional): Filter by specific import ID
- `min_confidence` (optional): Minimum AI confidence score (0.0 to 1.0)
- `sort_by` (optional): Field to sort by
  - `created_at` (default)
  - `start_datetime`
  - `priority`
  - `confidence`
- `sort_order` (optional): `asc` or `desc` (default: `desc`)
- `per_page` (optional): Items per page (default: 50)
- `page` (optional): Page number

#### Example Request
```bash
curl -X GET "http://localhost:8000/api/v1/events/imported?user_id=1&min_confidence=0.7&sort_by=priority&sort_order=desc"
```

#### Response
```json
{
  "status": "success",
  "message": "Imported events retrieved successfully",
  "data": [
    {
      "id": 35,
      "title": "Team Meeting",
      "description": "Weekly sync",
      "start_datetime": "2024-01-23 09:00:00",
      "end_datetime": "2024-01-23 10:00:00",
      "location": "Room A",
      "priority": 3,
      "status": "scheduled",
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
  ],
  "links": {
    "first": "...",
    "last": "...",
    "prev": null,
    "next": "..."
  },
  "meta": {
    "current_page": 1,
    "total": 45,
    "per_page": 50
  }
}
```

### 2. Get Imported Events Grouped by Source
**GET** `/api/v1/events/imported-grouped`

Retrieves imported events grouped by their import source, with statistics for each import.

#### Parameters
- `user_id` (required): User ID  
- `include_events` (optional): Include full event data (default: `false`)

#### Example Request
```bash
curl -X GET "http://localhost:8000/api/v1/events/imported-grouped?user_id=1"
```

#### Response
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
        "import_type": "file_upload",
        "imported_at": "2024-01-15 10:00:00",
        "status": "completed",
        "total_records": 15,
        "successfully_processed": 15,
        "ai_confidence": "0.75"
      },
      "events_count": 15,
      "events": null,
      "statistics": {
        "scheduled": 10,
        "completed": 3,
        "in_progress": 2,
        "cancelled": 0,
        "high_priority": 5
      }
    }
  ]
}
```

### 3. Select Events for AI Processing
**POST** `/api/v1/events/select-for-ai`

Select specific events and prepare them for AI processing. This creates a temporary selection that can be referenced for AI operations.

#### Parameters
- `user_id` (query parameter, required): User ID

#### Request Body
```json
{
  "event_ids": [35, 36, 37, 38, 40],
  "ai_task": "optimize",
  "context": "Optimize my schedule for next week",
  "options": {
    "include_metadata": true,
    "include_ai_analysis": true,
    "include_related_events": false
  }
}
```

#### Fields
- `event_ids` (required): Array of event IDs to select
- `ai_task` (required): Type of AI task to perform
  - `optimize` - Optimize schedule timing
  - `analyze` - Analyze patterns and insights
  - `reschedule` - Suggest rescheduling
  - `prioritize` - Re-prioritize tasks
  - `conflict_resolution` - Resolve scheduling conflicts
- `context` (optional): Additional context for AI processing
- `options` (optional): Processing options
  - `include_metadata`: Include import metadata
  - `include_ai_analysis`: Include previous AI analysis
  - `include_related_events`: Include related/linked events

#### Example Request
```bash
curl -X POST "http://localhost:8000/api/v1/events/select-for-ai?user_id=1" \
  -H "Content-Type: application/json" \
  -d '{
    "event_ids": [35, 36, 37],
    "ai_task": "optimize",
    "context": "Optimize schedule for productivity"
  }'
```

#### Response
```json
{
  "status": "success",
  "message": "Events selected for AI processing",
  "selection_id": "ai_selection_xyz123",
  "data": {
    "task": "optimize",
    "events_count": 3,
    "events_preview": [
      {
        "id": 35,
        "title": "Team Meeting",
        "start_datetime": "2024-01-23 09:00:00",
        "priority": 3
      }
    ],
    "ready_for_ai": true
  }
}
```

## Frontend Integration Examples

### JavaScript/React

```javascript
// Service class for imported events
class ImportedEventsService {
  constructor(baseURL = 'http://localhost:8000/api/v1') {
    this.baseURL = baseURL;
  }

  // Get all imported events
  async getImportedEvents(userId, filters = {}) {
    const params = new URLSearchParams({
      user_id: userId,
      ...filters
    });

    const response = await fetch(`${this.baseURL}/events/imported?${params}`);
    return response.json();
  }

  // Get events grouped by import source
  async getGroupedEvents(userId, includeEvents = false) {
    const response = await fetch(
      `${this.baseURL}/events/imported-grouped?user_id=${userId}&include_events=${includeEvents}`
    );
    return response.json();
  }

  // Select events for AI processing
  async selectForAI(userId, eventIds, task, context = '') {
    const response = await fetch(
      `${this.baseURL}/events/select-for-ai?user_id=${userId}`,
      {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          event_ids: eventIds,
          ai_task: task,
          context: context,
          options: {
            include_metadata: true,
            include_ai_analysis: true
          }
        })
      }
    );
    return response.json();
  }
}

// Usage example
const service = new ImportedEventsService();

// Get imported events with high confidence
const highConfidenceEvents = await service.getImportedEvents(1, {
  min_confidence: 0.8,
  sort_by: 'priority',
  sort_order: 'desc'
});

// Select events for optimization
const selection = await service.selectForAI(
  1, 
  [35, 36, 37], 
  'optimize',
  'Focus on morning productivity'
);
```

### React Component

```jsx
import React, { useState, useEffect } from 'react';

const ImportedEventsManager = ({ userId }) => {
  const [events, setEvents] = useState([]);
  const [selectedEvents, setSelectedEvents] = useState([]);
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    loadImportedEvents();
  }, [userId]);

  const loadImportedEvents = async () => {
    setLoading(true);
    try {
      const response = await fetch(
        `/api/v1/events/imported?user_id=${userId}`
      );
      const data = await response.json();
      setEvents(data.data);
    } catch (error) {
      console.error('Failed to load events:', error);
    } finally {
      setLoading(false);
    }
  };

  const handleSelectEvent = (eventId) => {
    if (selectedEvents.includes(eventId)) {
      setSelectedEvents(selectedEvents.filter(id => id !== eventId));
    } else {
      setSelectedEvents([...selectedEvents, eventId]);
    }
  };

  const sendToAI = async (task) => {
    if (selectedEvents.length === 0) {
      alert('Please select events first');
      return;
    }

    try {
      const response = await fetch(
        `/api/v1/events/select-for-ai?user_id=${userId}`,
        {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            event_ids: selectedEvents,
            ai_task: task,
            context: 'User requested AI processing'
          })
        }
      );
      
      const result = await response.json();
      alert(`Selected ${result.data.events_count} events for AI ${task}`);
      setSelectedEvents([]);
    } catch (error) {
      console.error('Failed to send to AI:', error);
    }
  };

  return (
    <div>
      <h2>Imported Events</h2>
      
      {loading && <p>Loading...</p>}
      
      <div className="events-list">
        {events.map(event => (
          <div key={event.id} className="event-item">
            <input
              type="checkbox"
              checked={selectedEvents.includes(event.id)}
              onChange={() => handleSelectEvent(event.id)}
            />
            <div>
              <h4>{event.title}</h4>
              <p>{event.description}</p>
              <small>
                Confidence: {(parseFloat(event.event_metadata.ai_confidence) * 100).toFixed(0)}%
              </small>
              <small>From: {event.import_source.filename}</small>
            </div>
          </div>
        ))}
      </div>

      <div className="actions">
        <button onClick={() => sendToAI('optimize')}>
          Optimize Selected ({selectedEvents.length})
        </button>
        <button onClick={() => sendToAI('analyze')}>
          Analyze Selected
        </button>
        <button onClick={() => sendToAI('prioritize')}>
          Re-prioritize Selected
        </button>
      </div>
    </div>
  );
};

export default ImportedEventsManager;
```

## Use Cases

### 1. Display Imported Data with Confidence Scores
```javascript
// Show events that need review (low confidence)
const needsReview = await service.getImportedEvents(userId, {
  min_confidence: 0,
  max_confidence: 0.5
});

// Display with warning badges for low-confidence items
```

### 2. Bulk Selection for AI Optimization
```javascript
// Get all events from a specific import
const importEvents = await service.getImportedEvents(userId, {
  import_id: 5
});

// Select all high-priority events for optimization
const highPriorityIds = importEvents.data
  .filter(e => e.priority >= 4)
  .map(e => e.id);

const result = await service.selectForAI(
  userId,
  highPriorityIds,
  'optimize'
);
```

### 3. Import Source Analysis
```javascript
// Get grouped view to show import statistics
const grouped = await service.getGroupedEvents(userId);

// Display import sources with success rates
grouped.data.forEach(group => {
  const successRate = (
    group.import.successfully_processed / 
    group.import.total_records * 100
  ).toFixed(1);
  
  console.log(`Import: ${group.import.filename}`);
  console.log(`Success Rate: ${successRate}%`);
  console.log(`Events Created: ${group.events_count}`);
  console.log(`High Priority: ${group.statistics.high_priority}`);
});
```

## Best Practices

1. **Filter by Confidence**: Always provide options to filter by AI confidence score
2. **Batch Operations**: Allow users to select multiple events for AI processing
3. **Show Import Context**: Display the source file and import date for transparency
4. **Review Low Confidence**: Highlight events with confidence < 0.7 for manual review
5. **Cache Selections**: Store selection IDs for tracking AI processing status

## Error Handling

```javascript
try {
  const result = await service.selectForAI(userId, eventIds, 'optimize');
  
  if (result.status === 'error') {
    console.error('AI selection failed:', result.message);
    // Show user-friendly error
  } else {
    // Process successful selection
    trackAIProgress(result.selection_id);
  }
} catch (error) {
  // Network or server error
  console.error('Request failed:', error);
}
```

---

**Version**: 1.0  
**Last Updated**: December 2025