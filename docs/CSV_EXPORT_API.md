# CSV Export API Documentation

## Overview
The CSV Export API allows you to export imported schedule data and converted events in various formats optimized for different use cases, including AI processing.

## Available Endpoints

### 1. Export Import Data
**GET** `/api/v1/schedule-imports/{id}/export`

Export all entries from a specific import in CSV format.

#### Parameters
- `user_id` (required): User ID
- `format` (optional): Export format
  - `original` - Original format as imported (default)
  - `parsed` - Parsed and processed data
  - `standard` - Standard calendar format
  - `ai_enhanced` - With AI suggestions and analysis
  - `vietnamese_school` - Vietnamese school schedule format
- `filename` (optional): Custom filename for download

#### Example Request
```bash
curl -X GET "http://localhost:8000/api/v1/schedule-imports/7/export?user_id=1&format=ai_enhanced&filename=schedule.csv" \
  -H "Accept: application/json" \
  -o schedule.csv
```

### 2. Export Converted Events
**GET** `/api/v1/schedule-imports/{id}/export-events`

Export events that were converted from an import.

#### Parameters
- `user_id` (required): User ID
- `format` (optional): Export format
  - `standard` (default)
  - `detailed` - Include all event fields
  - `calendar` - Calendar application format

#### Example Request
```bash
curl -X GET "http://localhost:8000/api/v1/schedule-imports/7/export-events?user_id=1&format=detailed" \
  -H "Accept: application/json" \
  -o events.csv
```

### 3. Batch Export
**POST** `/api/v1/schedule-imports/export-batch`

Export multiple imports in a single CSV file.

#### Request Body
```json
{
    "import_ids": [5, 7, 9],
    "format": "standard",
    "user_id": 1
}
```

#### Example Request
```bash
curl -X POST "http://localhost:8000/api/v1/schedule-imports/export-batch?user_id=1" \
  -H "Content-Type: application/json" \
  -d '{"import_ids": [5, 7], "format": "standard"}' \
  -o batch_export.csv
```

### 4. Preview Export
**GET** `/api/v1/schedule-imports/{id}/preview`

Preview export data before downloading (returns JSON).

#### Parameters
- `user_id` (required): User ID
- `format` (optional): Export format
- `limit` (optional): Number of entries to preview (default: 5)

#### Example Request
```bash
curl -X GET "http://localhost:8000/api/v1/schedule-imports/7/preview?user_id=1&format=ai_enhanced&limit=3" \
  -H "Accept: application/json"
```

## Export Formats

### 1. Original Format
Exports data exactly as it was imported, maintaining original column names and values.

**Use Case**: Backup, re-import, data preservation

### 2. Parsed Format
Exports data after parsing and processing, with standardized field names.

**Fields**: Title, Description, Start Date, End Date, Location, Priority, Confidence, Status

**Use Case**: Review parsing results, quality check

### 3. Standard Format
Exports in a standard calendar format compatible with most applications.

**Fields**: Title, Description, Start Date, End Date, Location, Priority, Status

**Use Case**: Import to calendar apps, general use

### 4. AI Enhanced Format
Includes AI analysis, suggestions, and metadata for AI processing.

**Fields**: 
- Title, Description, Start/End Date, Location, Priority
- Category (AI detected)
- Keywords (AI extracted)
- AI Confidence Score
- Importance Score
- Suggested Actions
- Original Text

**Use Case**: AI training, advanced analysis, automation

### 5. Vietnamese School Format
Specialized format for Vietnamese educational institutions.

**Fields**: Ngày, Lớp, Môn học, Giờ bắt đầu, Giờ kết thúc, Phòng, Ghi chú

**Use Case**: School schedule management, educational planning

## Response Format

### Successful Export
- **Content-Type**: text/csv; charset=UTF-8
- **Content-Disposition**: attachment; filename="schedule.csv"
- Returns CSV file with BOM for proper UTF-8 encoding

### Error Response
```json
{
    "success": false,
    "message": "Failed to export CSV",
    "error": "Detailed error message"
}
```

## Example Outputs

### Standard Format
```csv
Title,Description,Start Date,End Date,Location,Priority
"Team Meeting","Weekly sync","2024-01-22 09:00:00","2024-01-22 10:00:00","Room A",3
"Project Review","Q1 review","2024-01-23 14:00:00","2024-01-23 16:00:00","Board Room",5
```

### AI Enhanced Format
```csv
Title,Description,Start Date,End Date,Location,Priority,Category,Keywords,AI Confidence,Importance Score,Suggested Actions,Original Text
"Team Meeting","Weekly sync","2024-01-22 09:00:00","2024-01-22 10:00:00","Room A",3,"Meeting","team,sync,weekly",0.85,0.6,"","Team Meeting,Weekly sync,..."
```

### Vietnamese Format
```csv
Ngày,Lớp,Môn học,Giờ bắt đầu,Giờ kết thúc,Phòng,Ghi chú
"22/01/2024","Lớp 6A2","Toán","7:00","7:45","E03","Hằng đẳng thức"
"23/01/2024","Lớp 9D4","Âm nhạc","13:30","14:15","B04","Lý thuyết âm nhạc"
```

## Integration Examples

### Python
```python
import requests
import pandas as pd
from io import StringIO

# Export data
response = requests.get(
    "http://localhost:8000/api/v1/schedule-imports/7/export",
    params={"user_id": 1, "format": "ai_enhanced"}
)

# Load into pandas
df = pd.read_csv(StringIO(response.text))
print(df.head())
```

### JavaScript
```javascript
// Using fetch API
async function exportSchedule(importId, format = 'standard') {
    const response = await fetch(
        `/api/v1/schedule-imports/${importId}/export?user_id=1&format=${format}`
    );
    
    const blob = await response.blob();
    const url = window.URL.createObjectURL(blob);
    
    // Download file
    const a = document.createElement('a');
    a.href = url;
    a.download = `schedule_${format}.csv`;
    a.click();
}
```

### PHP
```php
// Using cURL
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "http://localhost:8000/api/v1/schedule-imports/7/export?user_id=1&format=standard");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$csvData = curl_exec($ch);
curl_close($ch);

// Save to file
file_put_contents('schedule.csv', $csvData);
```

## Best Practices

1. **Choose the Right Format**: Select format based on your use case
2. **UTF-8 Encoding**: Files include BOM for proper encoding in Excel
3. **Large Exports**: Use pagination or filters for large datasets
4. **AI Processing**: Use `ai_enhanced` format for machine learning
5. **Validation**: Use preview endpoint before large exports

## Error Handling

Common error codes:
- `404`: Import not found
- `403`: Unauthorized access
- `500`: Server error during export

Always check the `success` field in JSON responses for error handling.

---

**Version**: 1.0  
**Last Updated**: December 2024