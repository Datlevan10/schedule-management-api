# User Statistics API Documentation

## Overview
This API provides dynamic user statistics to replace static data in the Frontend dashboard cards and analytics views. It calculates real-time metrics including active tasks, reminders, and productivity scores.

## Main Endpoints

### 1. Dashboard Card Statistics (Simple)
**GET** `/api/v1/users/{userId}/dashboard-stats`

Perfect for replacing your static card data. Returns exactly what you need for the three dashboard cards.

**Response:**
```json
{
  "status": "success",
  "data": {
    "active_tasks": 3,        // Replace "3" in Active Tasks card
    "reminders": 2,           // Replace "2" in Reminders card
    "productivity": "85%",     // Replace "85%" in Productivity card
    "productivity_score": 85,  // Raw score (0-100)
    "productivity_trend": "up" // "up", "down", or "stable" for trend icon
  }
}
```

**React Native Implementation:**
```javascript
// Replace your static data with this:
const [stats, setStats] = useState({
  active_tasks: 0,
  reminders: 0,
  productivity: '0%'
});

useEffect(() => {
  fetch(`/api/v1/users/${userId}/dashboard-stats`)
    .then(res => res.json())
    .then(data => {
      if (data.status === 'success') {
        setStats(data.data);
      }
    });
}, [userId]);

// In your render:
<Card style={styles.statCard}>
  <Text style={styles.statNumber}>{stats.active_tasks}</Text>
  <Text style={styles.statLabel}>Active Tasks</Text>
</Card>

<Card style={styles.statCard}>
  <Text style={styles.statNumber}>{stats.reminders}</Text>
  <Text style={styles.statLabel}>Reminders</Text>
</Card>

<Card style={styles.statCard}>
  <Text style={styles.statNumber}>{stats.productivity}</Text>
  <Text style={styles.statLabel}>Productivity</Text>
  {stats.productivity_trend === 'up' && <Icon name="trending-up" />}
  {stats.productivity_trend === 'down' && <Icon name="trending-down" />}
</Card>
```

### 2. Comprehensive Statistics
**GET** `/api/v1/users/{userId}/statistics`

Returns detailed statistics for analytics views, charts, and detailed dashboards.

**Response Structure:**
```json
{
  "status": "success",
  "data": {
    "summary": {
      "total_tasks": 150,           // Total tasks (events + CSV imports)
      "active_tasks": 25,           // Currently active/scheduled tasks
      "analyzed_tasks": 45,         // Tasks analyzed by AI
      "tasks_with_reminders": 30,   // Tasks that have reminders set
      "pending_reminders": 8        // Upcoming reminders (next 7 days)
    },
    "productivity": {
      "score": 85,                  // Productivity score (0-100)
      "percentage": 85,             // Same as score, as percentage
      "trend": "up",               // Trend: up/down/stable
      "level": "excellent",        // Level: excellent/good/moderate/low
      "details": {
        "completed_this_week": 12,
        "total_this_week": 15,
        "ai_optimized": 8,
        "ai_bonus": 10
      }
    },
    "completion": {
      "rate": 75.5,                // Completion rate percentage
      "completed": 45,             // Number of completed tasks
      "total": 60,                 // Total tasks in period
      "period": "this_month"      // Period of measurement
    },
    "reminders": {
      "frequency_percentage": 45.5,  // % of year with reminders
      "total_reminders": 120,       // Total reminders set
      "days_with_reminders": 166,   // Unique days with reminders
      "average_per_day": 0.7        // Average reminders per day
    },
    "ai_analysis": {
      "total_analyses": 30,         // From ai_schedule_analyses table
      "completed_analyses": 28,
      "approved_schedules": 25,
      "average_confidence": 0.85,
      "average_rating": 4.2,
      "days_analyzed": 20,
      "last_analysis": "2024-01-15T10:30:00Z"
    },
    "weekly": {
      "total_tasks": 15,
      "completed": 12,
      "scheduled": 3,
      "cancelled": 0,
      "by_day": {
        "Monday": {"total": 3, "completed": 2, "scheduled": 1},
        "Tuesday": {"total": 2, "completed": 2, "scheduled": 0},
        // ... for each day
      }
    },
    "priority_distribution": {
      "Critical": 5,
      "High": 15,
      "Medium": 25,
      "Low": 10,
      "Very Low": 2
    }
  }
}
```

### 3. Reminder Statistics
**GET** `/api/v1/users/{userId}/reminder-stats`

Get detailed reminder statistics for a specific period.

**Query Parameters:**
- `period` (optional): day/week/month/year (default: week)
- `date` (optional): Base date for calculation (default: today)

**Response:**
```json
{
  "status": "success",
  "data": {
    "period": "week",
    "start_date": "2024-01-15",
    "end_date": "2024-01-21",
    "total_reminders": 15,
    "upcoming_reminders": 8,
    "past_reminders": 7,
    "daily_average": 2.1,
    "reminder_distribution": [
      {"minutes": 15, "label": "15 minutes", "count": 5},
      {"minutes": 60, "label": "1 hour", "count": 8},
      {"minutes": 1440, "label": "1 day", "count": 2}
    ]
  }
}
```

## Key Metrics Explained

### Active Tasks
- Events with status = 'scheduled' and future start_datetime
- CSV entries not yet converted to events

### Reminders
- Events with reminder_minutes > 0
- Scheduled for the next 7 days
- Status = 'scheduled'

### Productivity Score
Calculated based on:
- Task completion rate this week (base score)
- AI optimization usage (bonus points, max 15%)
- Comparison with last week (for trend)
- Scale: 0-100

**Productivity Levels:**
- 85-100: Excellent
- 70-84: Good
- 50-69: Moderate
- 0-49: Low

### Reminder Frequency Percentage
- Percentage of days in the year that have reminders
- Based on unique days with at least one reminder
- Example: 45% means reminders on 164 days of 365

## Implementation Examples

### React Native Dashboard
```javascript
import { useEffect, useState } from 'react';
import { View, Text, ActivityIndicator } from 'react-native';

const Dashboard = ({ userId }) => {
  const [loading, setLoading] = useState(true);
  const [dashboardStats, setDashboardStats] = useState(null);
  const [fullStats, setFullStats] = useState(null);

  useEffect(() => {
    Promise.all([
      fetch(`/api/v1/users/${userId}/dashboard-stats`).then(r => r.json()),
      fetch(`/api/v1/users/${userId}/statistics`).then(r => r.json())
    ]).then(([dashboard, full]) => {
      setDashboardStats(dashboard.data);
      setFullStats(full.data);
      setLoading(false);
    });
  }, [userId]);

  if (loading) {
    return <ActivityIndicator size="large" />;
  }

  return (
    <View>
      {/* Dashboard Cards */}
      <View style={styles.cardsContainer}>
        <Card>
          <Text style={styles.number}>{dashboardStats.active_tasks}</Text>
          <Text>Active Tasks</Text>
        </Card>
        
        <Card>
          <Text style={styles.number}>{dashboardStats.reminders}</Text>
          <Text>Reminders</Text>
        </Card>
        
        <Card>
          <Text style={styles.number}>{dashboardStats.productivity}</Text>
          <Text>Productivity</Text>
          {dashboardStats.productivity_trend === 'up' && <Text>📈</Text>}
        </Card>
      </View>

      {/* Additional Stats */}
      <View style={styles.detailedStats}>
        <Text>Total Tasks: {fullStats.summary.total_tasks}</Text>
        <Text>Analyzed by AI: {fullStats.summary.analyzed_tasks}</Text>
        <Text>Completion Rate: {fullStats.completion.rate}%</Text>
        <Text>
          Reminder Frequency: {fullStats.reminders.frequency_percentage}% of year
        </Text>
      </View>
    </View>
  );
};
```

### Service Class
```javascript
class UserStatisticsService {
  static baseUrl = '/api/v1';

  static async getDashboardStats(userId) {
    const response = await fetch(`${this.baseUrl}/users/${userId}/dashboard-stats`);
    const data = await response.json();
    return data.data;
  }

  static async getFullStatistics(userId) {
    const response = await fetch(`${this.baseUrl}/users/${userId}/statistics`);
    const data = await response.json();
    return data.data;
  }

  static async getReminderStats(userId, period = 'week') {
    const response = await fetch(
      `${this.baseUrl}/users/${userId}/reminder-stats?period=${period}`
    );
    const data = await response.json();
    return data.data;
  }
}

// Usage
const stats = await UserStatisticsService.getDashboardStats(userId);
console.log(`User has ${stats.active_tasks} active tasks`);
```

## Testing

Test the endpoints with the provided script:
```bash
./test-user-statistics.sh
```

Or manually:
```bash
# Get dashboard stats
curl http://localhost:8000/api/v1/users/1/dashboard-stats

# Get full statistics
curl http://localhost:8000/api/v1/users/1/statistics

# Get reminder stats for this week
curl http://localhost:8000/api/v1/users/1/reminder-stats?period=week
```

## Notes for Frontend Team

1. **Replace Static Data**: The `dashboard-stats` endpoint directly replaces your hardcoded values
2. **Real-time Updates**: Data is calculated in real-time from the database
3. **Productivity Trend**: Use the `productivity_trend` field to show trend arrows/icons
4. **Caching**: Consider caching these results for 5-10 minutes to reduce API calls
5. **Error Handling**: Always check `status === 'success'` before using data
6. **User Context**: All endpoints require `userId` parameter

## Migration from Static to Dynamic

**Before (Static):**
```javascript
<Text style={styles.statNumber}>3</Text>  // Hardcoded
```

**After (Dynamic):**
```javascript
<Text style={styles.statNumber}>{stats.active_tasks}</Text>  // From API
```

## Performance Considerations

- Dashboard stats endpoint is optimized for quick response (~50ms)
- Full statistics may take longer (~200ms) due to complex calculations
- Consider loading dashboard stats first, then full statistics
- Use loading states while fetching data

## Support

For issues or questions about these endpoints, check:
- API logs for detailed error messages
- Database for data availability
- Network tab for API response times