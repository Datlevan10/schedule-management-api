# AI Schedule Optimization API

## Overview
This API allows you to select events imported from CSV files and send them to AI for schedule optimization. The AI can optimize, analyze, reschedule, prioritize, or resolve conflicts in your schedule.

## Main Endpoint

### Send Selected Events to AI
**POST** `/api/v1/events/select-for-ai?user_id={userId}`

This endpoint selects specific events and prepares them for AI processing.

## Request Format

### Parameters
- **user_id** (query parameter, required): The user ID

### Request Body
```json
{
  "event_ids": [35, 36, 37, 40, 41],
  "ai_task": "optimize",
  "context": "Optimize my weekly schedule for maximum productivity",
  "options": {
    "include_metadata": true,
    "include_ai_analysis": true,
    "include_related_events": false
  }
}
```

### Fields Explanation

#### ai_task (required)
The type of AI optimization to perform:
- **`optimize`** - Optimize schedule timing for efficiency
- **`analyze`** - Analyze patterns and provide insights
- **`reschedule`** - Suggest better timing for events
- **`prioritize`** - Re-prioritize tasks based on importance
- **`conflict_resolution`** - Resolve scheduling conflicts

#### event_ids (required)
Array of event IDs to send to AI. These should be IDs from imported CSV events.

#### context (optional)
Additional context to help AI understand your optimization goals.

#### options (optional)
- **include_metadata**: Include import metadata (confidence scores, source)
- **include_ai_analysis**: Include previous AI analysis if available
- **include_related_events**: Include related/linked events

## Complete Workflow Example

### Step 1: Import CSV File
```bash
curl -X POST 'http://127.0.0.1:8000/api/v1/schedule-imports' \
  -F "file=@schedule.csv" \
  -F "import_type=file_upload" \
  -F "source_type=csv" \
  -F "user_id=1"
```

### Step 2: Get Imported Events
```bash
curl -X GET 'http://127.0.0.1:8000/api/v1/events/imported?user_id=1' \
  -H "Accept: application/json"
```

### Step 3: Select Events for AI Optimization
```bash
curl -X POST 'http://127.0.0.1:8000/api/v1/events/select-for-ai?user_id=1' \
  -H "Content-Type: application/json" \
  -d '{
    "event_ids": [35, 36, 37, 40, 41],
    "ai_task": "optimize",
    "context": "Optimize for morning productivity, minimize gaps between meetings",
    "options": {
      "include_metadata": true,
      "include_ai_analysis": true
    }
  }'
```

### Response
```json
{
  "status": "success",
  "message": "Events selected for AI processing",
  "selection_id": "ai_selection_693968c11b081",
  "data": {
    "task": "optimize",
    "events_count": 5,
    "events_preview": [
      {
        "id": 35,
        "title": "Team Meeting",
        "start_datetime": "2024-01-23T09:00:00Z",
        "priority": 3
      }
    ],
    "ready_for_ai": true
  }
}
```

## JavaScript Implementation

```javascript
class AIScheduleOptimizer {
  constructor(baseURL = 'http://127.0.0.1:8000/api/v1') {
    this.baseURL = baseURL;
  }

  /**
   * Complete workflow: Import CSV → Get Events → Send to AI
   */
  async optimizeImportedSchedule(userId, csvFile) {
    // Step 1: Import CSV
    console.log('Step 1: Importing CSV file...');
    const formData = new FormData();
    formData.append('file', csvFile);
    formData.append('import_type', 'file_upload');
    formData.append('source_type', 'csv');
    formData.append('user_id', userId);

    const importResponse = await fetch(`${this.baseURL}/schedule-imports`, {
      method: 'POST',
      body: formData
    });
    const importResult = await importResponse.json();
    console.log(`Imported ${importResult.data.total_records_found} records`);

    // Step 2: Get imported events
    console.log('Step 2: Retrieving imported events...');
    const eventsResponse = await fetch(
      `${this.baseURL}/events/imported?user_id=${userId}`
    );
    const eventsData = await eventsResponse.json();
    console.log(`Found ${eventsData.data.length} events`);

    // Step 3: Filter high-confidence events
    const highConfidenceEvents = eventsData.data.filter(event => 
      parseFloat(event.event_metadata.ai_confidence) >= 0.7
    );
    
    if (highConfidenceEvents.length === 0) {
      throw new Error('No high-confidence events to optimize');
    }

    // Step 4: Send to AI for optimization
    console.log('Step 3: Sending to AI for optimization...');
    const eventIds = highConfidenceEvents.map(e => e.id);
    
    const aiResponse = await fetch(
      `${this.baseURL}/events/select-for-ai?user_id=${userId}`,
      {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          event_ids: eventIds,
          ai_task: 'optimize',
          context: 'Optimize schedule for productivity and efficiency',
          options: {
            include_metadata: true,
            include_ai_analysis: true
          }
        })
      }
    );

    const aiResult = await aiResponse.json();
    
    return {
      importId: importResult.data.id,
      totalImported: importResult.data.total_records_found,
      eventsOptimized: aiResult.data.events_count,
      selectionId: aiResult.selection_id,
      status: 'ready_for_ai_processing'
    };
  }

  /**
   * Send specific events to AI with custom task
   */
  async sendToAI(userId, eventIds, task = 'optimize', context = '') {
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
            include_ai_analysis: true,
            include_related_events: false
          }
        })
      }
    );

    return response.json();
  }

  /**
   * Different AI optimization scenarios
   */
  async optimizeForProductivity(userId, eventIds) {
    return this.sendToAI(
      userId, 
      eventIds, 
      'optimize',
      'Maximize productivity by grouping similar tasks and minimizing context switching'
    );
  }

  async resolveConflicts(userId, eventIds) {
    return this.sendToAI(
      userId,
      eventIds,
      'conflict_resolution',
      'Resolve scheduling conflicts and suggest alternative times'
    );
  }

  async prioritizeTasks(userId, eventIds) {
    return this.sendToAI(
      userId,
      eventIds,
      'prioritize',
      'Re-prioritize based on deadlines and importance'
    );
  }

  async analyzePatterns(userId, eventIds) {
    return this.sendToAI(
      userId,
      eventIds,
      'analyze',
      'Analyze scheduling patterns and provide insights'
    );
  }

  async rescheduleEvents(userId, eventIds) {
    return this.sendToAI(
      userId,
      eventIds,
      'reschedule',
      'Suggest better timing based on energy levels and task complexity'
    );
  }
}

// Usage Example
const optimizer = new AIScheduleOptimizer();

// Example 1: Complete workflow with CSV file
async function processCSVFile(file) {
  try {
    const result = await optimizer.optimizeImportedSchedule(1, file);
    console.log('Optimization started:', result);
    
    // Track with selection ID
    console.log(`AI Processing ID: ${result.selectionId}`);
    console.log(`Events being optimized: ${result.eventsOptimized}`);
  } catch (error) {
    console.error('Optimization failed:', error);
  }
}

// Example 2: Optimize specific imported events
async function optimizeSelectedEvents() {
  // First, get imported events
  const response = await fetch('http://127.0.0.1:8000/api/v1/events/imported?user_id=1');
  const events = await response.json();
  
  // Select high-priority events only
  const highPriorityIds = events.data
    .filter(e => e.priority >= 4)
    .map(e => e.id);
  
  // Send to AI for optimization
  const result = await optimizer.optimizeForProductivity(1, highPriorityIds);
  console.log('High-priority events sent for optimization:', result);
}

// Example 3: Different AI tasks
async function demonstrateAITasks() {
  const eventIds = [35, 36, 37, 40, 41];
  const userId = 1;
  
  // Task 1: Optimize schedule
  const optimize = await optimizer.optimizeForProductivity(userId, eventIds);
  console.log('Optimization:', optimize.selection_id);
  
  // Task 2: Resolve conflicts
  const conflicts = await optimizer.resolveConflicts(userId, eventIds);
  console.log('Conflict Resolution:', conflicts.selection_id);
  
  // Task 3: Analyze patterns
  const analysis = await optimizer.analyzePatterns(userId, eventIds);
  console.log('Pattern Analysis:', analysis.selection_id);
  
  // Task 4: Re-prioritize
  const prioritize = await optimizer.prioritizeTasks(userId, eventIds);
  console.log('Prioritization:', prioritize.selection_id);
  
  // Task 5: Reschedule
  const reschedule = await optimizer.rescheduleEvents(userId, eventIds);
  console.log('Rescheduling:', reschedule.selection_id);
}
```

## React Component Example

```jsx
import React, { useState, useEffect } from 'react';

const AIScheduleOptimizer = ({ userId }) => {
  const [importedEvents, setImportedEvents] = useState([]);
  const [selectedEvents, setSelectedEvents] = useState([]);
  const [aiTask, setAiTask] = useState('optimize');
  const [context, setContext] = useState('');
  const [processing, setProcessing] = useState(false);
  const [result, setResult] = useState(null);

  useEffect(() => {
    loadImportedEvents();
  }, [userId]);

  const loadImportedEvents = async () => {
    try {
      const response = await fetch(
        `http://127.0.0.1:8000/api/v1/events/imported?user_id=${userId}`
      );
      const data = await response.json();
      setImportedEvents(data.data);
    } catch (error) {
      console.error('Failed to load events:', error);
    }
  };

  const handleSelectAll = () => {
    const allIds = importedEvents.map(e => e.id);
    setSelectedEvents(allIds);
  };

  const handleSelectHighConfidence = () => {
    const highConfIds = importedEvents
      .filter(e => parseFloat(e.event_metadata.ai_confidence) >= 0.7)
      .map(e => e.id);
    setSelectedEvents(highConfIds);
  };

  const handleToggleEvent = (eventId) => {
    if (selectedEvents.includes(eventId)) {
      setSelectedEvents(selectedEvents.filter(id => id !== eventId));
    } else {
      setSelectedEvents([...selectedEvents, eventId]);
    }
  };

  const sendToAI = async () => {
    if (selectedEvents.length === 0) {
      alert('Please select at least one event');
      return;
    }

    setProcessing(true);
    try {
      const response = await fetch(
        `http://127.0.0.1:8000/api/v1/events/select-for-ai?user_id=${userId}`,
        {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            event_ids: selectedEvents,
            ai_task: aiTask,
            context: context,
            options: {
              include_metadata: true,
              include_ai_analysis: true
            }
          })
        }
      );

      const data = await response.json();
      setResult(data);
      
      if (data.status === 'success') {
        alert(`Successfully sent ${data.data.events_count} events for AI ${aiTask}`);
      }
    } catch (error) {
      console.error('Failed to send to AI:', error);
      alert('Failed to process request');
    } finally {
      setProcessing(false);
    }
  };

  return (
    <div className="ai-optimizer">
      <h2>AI Schedule Optimization</h2>
      
      {/* Quick Selection Buttons */}
      <div className="selection-buttons">
        <button onClick={handleSelectAll}>Select All</button>
        <button onClick={handleSelectHighConfidence}>
          Select High Confidence (≥70%)
        </button>
        <button onClick={() => setSelectedEvents([])}>Clear Selection</button>
      </div>

      {/* Events List */}
      <div className="events-list">
        <h3>Imported Events ({importedEvents.length})</h3>
        {importedEvents.map(event => (
          <div key={event.id} className="event-item">
            <input
              type="checkbox"
              checked={selectedEvents.includes(event.id)}
              onChange={() => handleToggleEvent(event.id)}
            />
            <div className="event-details">
              <strong>{event.title}</strong>
              <span className="confidence">
                Confidence: {(parseFloat(event.event_metadata.ai_confidence) * 100).toFixed(0)}%
              </span>
              <span className="priority">Priority: {event.priority}/5</span>
              <small>{event.import_source.filename}</small>
            </div>
          </div>
        ))}
      </div>

      {/* AI Task Selection */}
      <div className="ai-config">
        <h3>AI Configuration</h3>
        
        <div className="form-group">
          <label>AI Task:</label>
          <select value={aiTask} onChange={(e) => setAiTask(e.target.value)}>
            <option value="optimize">Optimize Schedule</option>
            <option value="analyze">Analyze Patterns</option>
            <option value="reschedule">Reschedule Events</option>
            <option value="prioritize">Re-prioritize Tasks</option>
            <option value="conflict_resolution">Resolve Conflicts</option>
          </select>
        </div>

        <div className="form-group">
          <label>Context (optional):</label>
          <textarea
            value={context}
            onChange={(e) => setContext(e.target.value)}
            placeholder="E.g., Optimize for morning productivity, minimize travel time..."
            rows="3"
          />
        </div>

        <div className="selected-info">
          Selected: {selectedEvents.length} events
        </div>

        <button 
          onClick={sendToAI}
          disabled={processing || selectedEvents.length === 0}
          className="ai-process-btn"
        >
          {processing ? 'Processing...' : `Send to AI for ${aiTask}`}
        </button>
      </div>

      {/* Result Display */}
      {result && result.status === 'success' && (
        <div className="result">
          <h3>AI Processing Started</h3>
          <p>Selection ID: {result.selection_id}</p>
          <p>Events processed: {result.data.events_count}</p>
          <p>Task: {result.data.task}</p>
          <p>Status: Ready for AI</p>
        </div>
      )}
    </div>
  );
};

export default AIScheduleOptimizer;
```

## Python Example

```python
import requests
import json

class AIScheduleOptimizer:
    def __init__(self, base_url='http://127.0.0.1:8000/api/v1'):
        self.base_url = base_url
    
    def get_imported_events(self, user_id):
        """Get all imported events for a user"""
        response = requests.get(
            f"{self.base_url}/events/imported",
            params={'user_id': user_id}
        )
        return response.json()
    
    def send_to_ai(self, user_id, event_ids, ai_task='optimize', context=''):
        """Send selected events to AI for processing"""
        response = requests.post(
            f"{self.base_url}/events/select-for-ai",
            params={'user_id': user_id},
            json={
                'event_ids': event_ids,
                'ai_task': ai_task,
                'context': context,
                'options': {
                    'include_metadata': True,
                    'include_ai_analysis': True
                }
            }
        )
        return response.json()
    
    def optimize_imported_schedule(self, user_id, min_confidence=0.7):
        """Complete workflow: Get events → Filter → Send to AI"""
        
        # Step 1: Get imported events
        print(f"Getting imported events for user {user_id}...")
        events_data = self.get_imported_events(user_id)
        
        if not events_data['data']:
            print("No imported events found")
            return None
        
        # Step 2: Filter by confidence
        high_confidence_events = [
            event for event in events_data['data']
            if float(event['event_metadata']['ai_confidence']) >= min_confidence
        ]
        
        print(f"Found {len(high_confidence_events)} high-confidence events")
        
        # Step 3: Extract IDs
        event_ids = [event['id'] for event in high_confidence_events]
        
        # Step 4: Send to AI
        print(f"Sending {len(event_ids)} events to AI for optimization...")
        result = self.send_to_ai(
            user_id=user_id,
            event_ids=event_ids,
            ai_task='optimize',
            context='Optimize schedule for maximum productivity'
        )
        
        if result['status'] == 'success':
            print(f"✓ AI processing started")
            print(f"  Selection ID: {result['selection_id']}")
            print(f"  Events: {result['data']['events_count']}")
            print(f"  Task: {result['data']['task']}")
        
        return result

# Usage
optimizer = AIScheduleOptimizer()

# Example 1: Optimize user's imported schedule
result = optimizer.optimize_imported_schedule(user_id=1, min_confidence=0.7)

# Example 2: Send specific events for different tasks
events = optimizer.get_imported_events(1)
event_ids = [e['id'] for e in events['data'][:5]]  # First 5 events

# Optimize
optimize_result = optimizer.send_to_ai(1, event_ids, 'optimize')

# Analyze patterns
analyze_result = optimizer.send_to_ai(1, event_ids, 'analyze')

# Resolve conflicts
conflict_result = optimizer.send_to_ai(1, event_ids, 'conflict_resolution')
```

## Common Use Cases

### 1. Auto-Optimize After Import
```javascript
// Automatically optimize after CSV import
async function autoOptimizeAfterImport(userId, csvFile) {
  // Upload CSV
  const formData = new FormData();
  formData.append('file', csvFile);
  formData.append('user_id', userId);
  
  await fetch('/api/v1/schedule-imports', { 
    method: 'POST', 
    body: formData 
  });
  
  // Wait for processing
  await new Promise(resolve => setTimeout(resolve, 1000));
  
  // Get and optimize
  const events = await fetch(`/api/v1/events/imported?user_id=${userId}`);
  const data = await events.json();
  const eventIds = data.data.map(e => e.id);
  
  // Send to AI
  return fetch(`/api/v1/events/select-for-ai?user_id=${userId}`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      event_ids: eventIds,
      ai_task: 'optimize',
      context: 'Auto-optimization after import'
    })
  });
}
```

### 2. Batch Processing Multiple Imports
```javascript
// Process multiple CSV imports for AI optimization
async function batchProcessImports(userId) {
  // Get all grouped imports
  const grouped = await fetch(`/api/v1/events/imported-grouped?user_id=${userId}`);
  const data = await grouped.json();
  
  // Process each import separately
  for (const group of data.data) {
    // Get events from this import
    const events = await fetch(
      `/api/v1/events/imported?user_id=${userId}&import_id=${group.import.id}`
    );
    const eventData = await events.json();
    
    if (eventData.data.length > 0) {
      const eventIds = eventData.data.map(e => e.id);
      
      // Send to AI with import-specific context
      await fetch(`/api/v1/events/select-for-ai?user_id=${userId}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          event_ids: eventIds,
          ai_task: 'optimize',
          context: `Optimize events from ${group.import.filename}`
        })
      });
      
      console.log(`Processed import: ${group.import.filename}`);
    }
  }
}
```

## Notes

1. **Selection ID**: Each AI request returns a unique `selection_id` to track processing
2. **Confidence Filtering**: Always filter by AI confidence for better optimization results
3. **Context Matters**: Provide specific context for better AI optimization
4. **Batch Size**: For large schedules, process in batches of 20-50 events

---

**Version**: 1.0  
**Last Updated**: December 2025