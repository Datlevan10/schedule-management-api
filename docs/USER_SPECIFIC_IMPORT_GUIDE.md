# User-Specific CSV Import Data Retrieval Guide

## Overview
This guide explains how to retrieve CSV-imported data for specific users, which is essential for AI training and providing personalized scheduling results.

## API Endpoint

### Get Imported Events by User ID
```bash
GET http://127.0.0.1:8000/api/v1/events/imported?user_id={userId}
```

## Required Parameter
- **user_id** (required): The ID of the user whose imported data you want to retrieve

## Example Usage

### 1. Basic Request - Get All Imported Events for User
```bash
# For User ID = 1
curl --location 'http://127.0.0.1:8000/api/v1/events/imported?user_id=1' \
  -H "Accept: application/json"

# For User ID = 2
curl --location 'http://127.0.0.1:8000/api/v1/events/imported?user_id=2' \
  -H "Accept: application/json"
```

### 2. With Filtering Options
```bash
# Get high-confidence imports only (confidence > 0.8)
curl --location 'http://127.0.0.1:8000/api/v1/events/imported?user_id=2&min_confidence=0.8' \
  -H "Accept: application/json"

# Get from specific import
curl --location 'http://127.0.0.1:8000/api/v1/events/imported?user_id=2&import_id=5' \
  -H "Accept: application/json"

# Sort by priority
curl --location 'http://127.0.0.1:8000/api/v1/events/imported?user_id=2&sort_by=priority&sort_order=desc' \
  -H "Accept: application/json"
```

## Response Format
```json
{
  "status": "success",
  "message": "Imported events retrieved successfully",
  "data": [
    {
      "id": 40,
      "title": "Documentation",
      "description": "Update API docs",
      "start_datetime": "2024-01-26T09:00:00.000000Z",
      "end_datetime": "2024-01-26T11:00:00.000000Z",
      "location": "Home Office",
      "user_id": 1,
      "priority": 2,
      "event_metadata": {
        "imported": true,
        "import_id": 5,
        "entry_id": 23,
        "ai_confidence": "0.75"
      },
      "status": "scheduled",
      "import_source": {
        "id": 5,
        "filename": "schedule_basic.csv",
        "type": "csv",
        "imported_at": "2025-12-10T11:30:26.000000Z",
        "total_records": 10
      }
    }
  ],
  "pagination": {
    "current_page": 1,
    "last_page": 1,
    "per_page": 15,
    "total": 15
  }
}
```

## Complete Workflow for AI Training

### Step 1: User Uploads CSV
```bash
# User 2 uploads their CSV file
curl -X POST 'http://127.0.0.1:8000/api/v1/schedule-imports' \
  -F "file=@schedule.csv" \
  -F "import_type=file_upload" \
  -F "source_type=csv" \
  -F "user_id=2"
```

### Step 2: Retrieve User's Imported Data
```bash
# Get all imported events for User 2
curl --location 'http://127.0.0.1:8000/api/v1/events/imported?user_id=2' \
  -H "Accept: application/json"
```

### Step 3: Send to AI for Processing
```bash
# Select specific events for AI optimization
curl -X POST 'http://127.0.0.1:8000/api/v1/events/select-for-ai?user_id=2' \
  -H "Content-Type: application/json" \
  -d '{
    "event_ids": [101, 102, 103],
    "ai_task": "optimize",
    "context": "User 2 schedule optimization"
  }'
```

## JavaScript/Frontend Implementation

```javascript
class UserImportService {
  /**
   * Get imported events for specific user
   * @param {number} userId - User ID
   * @param {Object} filters - Optional filters
   */
  async getUserImportedEvents(userId, filters = {}) {
    const params = new URLSearchParams({
      user_id: userId,
      ...filters
    });

    const response = await fetch(
      `http://127.0.0.1:8000/api/v1/events/imported?${params}`
    );
    
    const data = await response.json();
    return data;
  }

  /**
   * Prepare user data for AI training
   * @param {number} userId - User ID
   */
  async prepareForAITraining(userId) {
    // Step 1: Get all imported events
    const imported = await this.getUserImportedEvents(userId);
    
    // Step 2: Filter high-quality data (high confidence)
    const highQuality = imported.data.filter(event => 
      parseFloat(event.event_metadata.ai_confidence) >= 0.7
    );
    
    // Step 3: Format for AI
    const trainingData = highQuality.map(event => ({
      title: event.title,
      description: event.description,
      start: event.start_datetime,
      end: event.end_datetime,
      priority: event.priority,
      confidence: event.event_metadata.ai_confidence,
      source: event.import_source.filename
    }));
    
    return trainingData;
  }

  /**
   * Get user's schedule for AI analysis
   * @param {number} userId - User ID
   */
  async getUserScheduleForAI(userId) {
    // Get imported events
    const imported = await this.getUserImportedEvents(userId);
    
    if (imported.data.length === 0) {
      return {
        status: 'no_data',
        message: 'User has no imported events'
      };
    }
    
    // Group by import source
    const groupedData = {};
    imported.data.forEach(event => {
      const source = event.import_source.filename;
      if (!groupedData[source]) {
        groupedData[source] = [];
      }
      groupedData[source].push(event);
    });
    
    return {
      status: 'success',
      userId: userId,
      totalEvents: imported.data.length,
      sources: Object.keys(groupedData),
      data: groupedData,
      readyForAI: true
    };
  }
}

// Usage Example
const service = new UserImportService();

// Example 1: Get data for User 2
async function getUser2Schedule() {
  const userData = await service.getUserImportedEvents(2);
  
  if (userData.data.length > 0) {
    console.log(`User 2 has ${userData.data.length} imported events`);
    
    // Prepare for AI
    const aiData = await service.prepareForAITraining(2);
    console.log('AI Training Data:', aiData);
    
    // Send to AI for scheduling
    const eventIds = userData.data.map(e => e.id);
    const response = await fetch(
      'http://127.0.0.1:8000/api/v1/events/select-for-ai?user_id=2',
      {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          event_ids: eventIds,
          ai_task: 'optimize',
          context: 'Optimize User 2 schedule based on imported data'
        })
      }
    );
    
    const result = await response.json();
    console.log('AI Processing Started:', result.selection_id);
  } else {
    console.log('User 2 has no imported data');
  }
}

// Example 2: Multi-user processing
async function processMultipleUsers(userIds) {
  const results = [];
  
  for (const userId of userIds) {
    const schedule = await service.getUserScheduleForAI(userId);
    
    if (schedule.status === 'success') {
      results.push({
        userId: userId,
        events: schedule.totalEvents,
        sources: schedule.sources
      });
    }
  }
  
  return results;
}

// Process users 1, 2, 3
processMultipleUsers([1, 2, 3]).then(results => {
  console.log('User schedules ready for AI:', results);
});
```

## React Component Example

```jsx
import React, { useState, useEffect } from 'react';

const UserScheduleImporter = ({ userId }) => {
  const [importedEvents, setImportedEvents] = useState([]);
  const [loading, setLoading] = useState(false);
  const [aiProcessing, setAiProcessing] = useState(false);

  useEffect(() => {
    if (userId) {
      fetchUserImportedEvents();
    }
  }, [userId]);

  const fetchUserImportedEvents = async () => {
    setLoading(true);
    try {
      const response = await fetch(
        `http://127.0.0.1:8000/api/v1/events/imported?user_id=${userId}`
      );
      const data = await response.json();
      setImportedEvents(data.data);
    } catch (error) {
      console.error('Failed to fetch imported events:', error);
    } finally {
      setLoading(false);
    }
  };

  const sendToAI = async () => {
    if (importedEvents.length === 0) {
      alert('No imported events to process');
      return;
    }

    setAiProcessing(true);
    try {
      const eventIds = importedEvents.map(e => e.id);
      
      const response = await fetch(
        `http://127.0.0.1:8000/api/v1/events/select-for-ai?user_id=${userId}`,
        {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            event_ids: eventIds,
            ai_task: 'optimize',
            context: `Optimize schedule for user ${userId}`
          })
        }
      );
      
      const result = await response.json();
      alert(`AI processing started. Selection ID: ${result.selection_id}`);
    } catch (error) {
      console.error('Failed to send to AI:', error);
      alert('Failed to start AI processing');
    } finally {
      setAiProcessing(false);
    }
  };

  return (
    <div className="user-schedule-container">
      <h2>User {userId} - Imported Schedule</h2>
      
      {loading && <p>Loading imported events...</p>}
      
      {!loading && importedEvents.length === 0 && (
        <p>No imported events found for this user.</p>
      )}
      
      {importedEvents.length > 0 && (
        <>
          <div className="stats">
            <p>Total Events: {importedEvents.length}</p>
            <p>Sources: {[...new Set(importedEvents.map(e => e.import_source.filename))].join(', ')}</p>
          </div>
          
          <div className="events-grid">
            {importedEvents.map(event => (
              <div key={event.id} className="event-card">
                <h4>{event.title}</h4>
                <p>{event.description}</p>
                <small>Priority: {event.priority}</small>
                <small>Confidence: {(parseFloat(event.event_metadata.ai_confidence) * 100).toFixed(0)}%</small>
              </div>
            ))}
          </div>
          
          <button 
            onClick={sendToAI} 
            disabled={aiProcessing}
            className="ai-process-btn"
          >
            {aiProcessing ? 'Processing...' : 'Send to AI for Optimization'}
          </button>
        </>
      )}
    </div>
  );
};

export default UserScheduleImporter;
```

## Common Use Cases

### 1. AI Training with User-Specific Data
```javascript
// Get User 2's imported data for training
const user2Data = await fetch('http://127.0.0.1:8000/api/v1/events/imported?user_id=2');
const events = await user2Data.json();

// Filter high-confidence events for better AI training
const trainingSet = events.data.filter(e => 
  parseFloat(e.event_metadata.ai_confidence) >= 0.8
);
```

### 2. Multi-Import Analysis
```javascript
// Get all imports grouped for User 2
const grouped = await fetch('http://127.0.0.1:8000/api/v1/events/imported-grouped?user_id=2');
const groupedData = await grouped.json();

// Analyze each import source
groupedData.data.forEach(group => {
  console.log(`Import: ${group.import.filename}`);
  console.log(`Events: ${group.events_count}`);
  console.log(`Confidence: ${group.import.ai_confidence}`);
});
```

### 3. Real-Time Schedule Optimization
```javascript
// When User 2 uploads a new CSV
async function onCSVUpload(userId, file) {
  // 1. Upload CSV
  const formData = new FormData();
  formData.append('file', file);
  formData.append('user_id', userId);
  
  await fetch('/api/v1/schedule-imports', {
    method: 'POST',
    body: formData
  });
  
  // 2. Wait a moment for processing
  await new Promise(resolve => setTimeout(resolve, 2000));
  
  // 3. Get the imported data
  const imported = await fetch(`/api/v1/events/imported?user_id=${userId}`);
  const data = await imported.json();
  
  // 4. Automatically send to AI
  if (data.data.length > 0) {
    const eventIds = data.data.map(e => e.id);
    await sendToAIForOptimization(userId, eventIds);
  }
}
```

## Important Notes

1. **User Isolation**: Each user's data is isolated - User 2 cannot see User 1's imported data
2. **AI Confidence**: Use the `ai_confidence` field to filter quality data for AI training
3. **Pagination**: For users with many imports, use pagination parameters
4. **Real-time Updates**: After CSV upload, data is immediately available via this API

---

**Version**: 1.0  
**Last Updated**: December 2025