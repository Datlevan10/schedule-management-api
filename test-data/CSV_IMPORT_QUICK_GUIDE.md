# CSV Import Quick Reference Guide

## 📁 Test Files Available

1. **schedule_basic.csv** - 10 English entries with all fields
2. **schedule_vietnamese.csv** - 10 Vietnamese entries 
3. **schedule_minimal.csv** - 3 entries with only required fields

## 🚀 Quick cURL Commands

### 1. Import CSV File
```bash
curl -X POST http://localhost:8000/api/v1/schedule-imports \
  -H "Accept: application/json" \
  -F "import_type=file_upload" \
  -F "source_type=csv" \
  -F "file=@test-data/schedule_basic.csv" \
  -F "user_id=1"
```

### 2. Import Manual Text
```bash
curl -X POST http://localhost:8000/api/v1/schedule-imports \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{
    "import_type": "manual_input",
    "source_type": "manual",
    "raw_content": "Meeting at 2pm tomorrow\nCall client at 10am",
    "user_id": 1
  }'
```

### 3. List All Imports
```bash
curl -X GET "http://localhost:8000/api/v1/schedule-imports?user_id=1" \
  -H "Accept: application/json"
```

### 4. Get Import Entries
```bash
curl -X GET "http://localhost:8000/api/v1/schedule-imports/{IMPORT_ID}/entries?user_id=1" \
  -H "Accept: application/json"
```

### 5. Convert to Events
```bash
curl -X POST "http://localhost:8000/api/v1/schedule-imports/{IMPORT_ID}/convert?user_id=1" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"min_confidence": 0.5}'
```

### 6. Get Import Statistics
```bash
curl -X GET "http://localhost:8000/api/v1/schedule-imports/statistics?user_id=1" \
  -H "Accept: application/json"
```

### 7. Update Entry Manually
```bash
curl -X PATCH "http://localhost:8000/api/v1/schedule-imports/entries/{ENTRY_ID}?user_id=1" \
  -H "Content-Type: application/json" \
  -d '{
    "parsed_title": "Updated Title",
    "parsed_priority": 5,
    "manual_review_required": false
  }'
```

### 8. Delete Import
```bash
curl -X DELETE "http://localhost:8000/api/v1/schedule-imports/{IMPORT_ID}?user_id=1" \
  -H "Accept: application/json"
```

## 📊 CSV Format

### Required Fields
- **Title** - Event title
- **Start Date** - Format: YYYY-MM-DD HH:MM:SS

### Optional Fields
- **Description** - Event description
- **End Date** - Format: YYYY-MM-DD HH:MM:SS
- **Location** - Event location
- **Priority** - Number 1-5 (5 is highest)

### Example CSV
```csv
Title,Description,Start Date,End Date,Location,Priority
Team Meeting,Weekly sync,2024-01-22 09:00:00,2024-01-22 10:00:00,Room A,3
Project Review,Q1 review,2024-01-23 14:00:00,2024-01-23 16:00:00,Board Room,5
```

## 🔧 Using Postman

1. **Import Collection**: Import `CSV_Import_Collection.postman.json` into Postman
2. **Set Variables**: 
   - `base_url`: http://localhost:8000
   - `user_id`: 1
3. **Upload CSV**: Use "Import CSV File" request and select file in Body > form-data
4. **Check Results**: The collection saves `import_id` automatically for subsequent requests

## 🧪 Test Script

Run all tests automatically:
```bash
cd test-data
./test-csv-import.sh
```

## 📝 Field Name Variations Supported

The system automatically detects these field variations:
- **Title**: Title, title, event, subject
- **Description**: Description, description, notes, details
- **Start Date**: Start Date, start_date, StartDate, date, datetime
- **End Date**: End Date, end_date, EndDate, end_time
- **Location**: Location, location, venue, place
- **Priority**: Priority, priority, importance

## ✅ Success Response Example
```json
{
    "success": true,
    "message": "Schedule import created successfully",
    "data": {
        "id": 5,
        "status": "completed",
        "total_records_found": 10,
        "ai_confidence_score": "0.75"
    }
}
```

## ❌ Common Errors

1. **Missing required field**
   - Ensure CSV has at least Title and Start Date columns

2. **Invalid date format**
   - Use YYYY-MM-DD HH:MM:SS format

3. **File too large**
   - Maximum file size: 10MB

4. **No authentication**
   - Always include user_id parameter

## 🔍 Debugging Tips

1. Check import status:
```bash
curl -X GET "http://localhost:8000/api/v1/schedule-imports/{ID}?user_id=1"
```

2. View Laravel logs:
```bash
tail -f storage/logs/laravel.log
```

3. Check parsed data:
```bash
curl -X GET "http://localhost:8000/api/v1/schedule-imports/{ID}/entries?user_id=1"
```

---

**Version**: 1.0  
**Last Updated**: December 2024