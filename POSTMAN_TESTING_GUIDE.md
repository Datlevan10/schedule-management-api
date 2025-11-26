# Schedule Template CSV Download & Import - Postman Testing Guide

## Prerequisites
- Postman installed
- Valid API authentication token
- Base URL: `{{base_url}}/api/v1` (configure in Postman environment)

## Available APIs

### 1. CSV Template Download APIs

#### A. Download Template CSV by ID
**Endpoint:** `GET /api/v1/schedule-import-templates/{id}/download`
- **Purpose:** Downloads the CSV template file for a specific template
- **Authentication:** Required (Bearer token)
- **Path Parameter:** 
  - `id`: Template ID (integer)
- **Response:** CSV file download

**Postman Setup:**
1. Create new GET request
2. URL: `{{base_url}}/api/v1/schedule-import-templates/1/download`
3. Headers:
   - `Authorization`: `Bearer {{token}}`
   - `Accept`: `text/csv`
4. Send & Download response

#### B. Download Sample Data CSV
**Endpoint:** `GET /api/v1/schedule-import-templates/{id}/download-sample`
- **Purpose:** Downloads sample CSV with example data
- **Authentication:** Required
- **Path Parameter:** 
  - `id`: Template ID
- **Response:** CSV file with 5 sample rows

**Postman Setup:**
1. GET request to: `{{base_url}}/api/v1/schedule-import-templates/1/download-sample`
2. Add Bearer token
3. Send & Download

#### C. Download Instructions
**Endpoint:** `GET /api/v1/schedule-import-templates/{id}/download-instructions`
- **Purpose:** Downloads markdown instructions for the template
- **Authentication:** Required
- **Response:** Markdown file

### 2. List Available Templates

**Endpoint:** `GET /api/v1/schedule-import-templates`
- **Purpose:** Get list of all available templates
- **Query Parameters (optional):**
  - `profession_id`: Filter by profession
  - `is_active`: Filter active templates (boolean)
  - `is_default`: Filter default templates (boolean)
  - `file_type`: Filter by file type (csv, excel, etc.)
  - `per_page`: Items per page (default: 15)

**Postman Example:**
```
GET {{base_url}}/api/v1/schedule-import-templates?is_active=true&file_type=csv
```

### 3. Get Single Template Details

**Endpoint:** `GET /api/v1/schedule-import-templates/{id}`
- **Purpose:** Get detailed information about a specific template
- **Response:** JSON with template details including columns, mappings, etc.

### 4. Import CSV File

**Endpoint:** `POST /api/v1/schedule-imports`
- **Purpose:** Upload and import a CSV file
- **Method:** POST
- **Headers:**
  - `Authorization`: `Bearer {{token}}`
  - `Content-Type`: `multipart/form-data`
- **Body (form-data):**
  - `import_type`: `file_upload`
  - `source_type`: `csv`
  - `file`: Select CSV file
  - `template_id`: (optional) Template ID to use for parsing

**Postman Setup:**
1. Create POST request
2. URL: `{{base_url}}/api/v1/schedule-imports`
3. Body type: `form-data`
4. Add fields:
   - Key: `import_type`, Value: `file_upload`
   - Key: `source_type`, Value: `csv`
   - Key: `file`, Type: File, Select your CSV
   - Key: `template_id`, Value: `1` (optional)
5. Send request

### 5. Check Import Status

**Endpoint:** `GET /api/v1/schedule-imports/{import_id}`
- **Purpose:** Check the status of your import
- **Response:** Import details with processing status

### 6. Get Import Entries

**Endpoint:** `GET /api/v1/schedule-imports/{import_id}/entries`
- **Purpose:** Get parsed entries from the import
- **Query Parameters:**
  - `processing_status`: Filter by status
  - `per_page`: Pagination

## Complete Testing Flow

### Step 1: Get Available Templates
```http
GET {{base_url}}/api/v1/schedule-import-templates
Authorization: Bearer {{token}}
```

### Step 2: Download Template CSV
```http
GET {{base_url}}/api/v1/schedule-import-templates/1/download
Authorization: Bearer {{token}}
```
Save the downloaded CSV file.

### Step 3: Fill Template with Your Data
Edit the CSV file with your schedule data following the template structure.

### Step 4: Re-import the CSV
```http
POST {{base_url}}/api/v1/schedule-imports
Authorization: Bearer {{token}}
Content-Type: multipart/form-data

Body (form-data):
- import_type: file_upload
- source_type: csv
- file: [Select your edited CSV]
- template_id: 1
```

### Step 5: Check Import Status
```http
GET {{base_url}}/api/v1/schedule-imports/{import_id}
Authorization: Bearer {{token}}
```

### Step 6: Process Import (if needed)
```http
POST {{base_url}}/api/v1/schedule-imports/{import_id}/process
Authorization: Bearer {{token}}
```

### Step 7: Convert to Events
```http
POST {{base_url}}/api/v1/schedule-imports/{import_id}/convert
Authorization: Bearer {{token}}
Content-Type: application/json

Body:
{
    "min_confidence": 0.7
}
```

## Postman Collection Setup

### Environment Variables
Create a Postman environment with:
- `base_url`: Your API base URL (e.g., `http://localhost:8000`)
- `token`: Your authentication token

### Pre-request Script (for authentication)
```javascript
pm.request.headers.add({
    key: 'Authorization',
    value: 'Bearer ' + pm.environment.get('token')
});
```

### Tests Script (to save import_id)
For the import endpoint, add this test:
```javascript
if (pm.response.code === 201) {
    var jsonData = pm.response.json();
    pm.environment.set("import_id", jsonData.data.id);
}
```

## Response Examples

### Successful Template List Response
```json
{
    "data": [
        {
            "id": 1,
            "template_name": "Basic Schedule Template",
            "file_type": "csv",
            "profession_id": 1,
            "columns": ["date", "time", "subject", "location"],
            "is_active": true,
            "is_default": true
        }
    ],
    "meta": {
        "current_page": 1,
        "total": 5
    }
}
```

### Successful Import Response
```json
{
    "success": true,
    "message": "Schedule import created successfully",
    "data": {
        "id": 123,
        "user_id": 1,
        "import_type": "file_upload",
        "source_type": "csv",
        "status": "pending",
        "original_filename": "schedule.csv"
    }
}
```

## Error Handling

### Common Errors
1. **401 Unauthorized**: Missing or invalid token
2. **404 Not Found**: Template or import ID doesn't exist
3. **422 Validation Error**: Invalid file format or missing required fields
4. **500 Server Error**: Processing error

### Error Response Format
```json
{
    "success": false,
    "message": "Error description",
    "errors": {
        "field": ["Validation error message"]
    }
}
```

## Tips for Testing

1. **Save Response Examples**: Use Postman's "Save Response" feature to keep examples
2. **Use Collection Runner**: Test the complete flow automatically
3. **Monitor Headers**: Check response headers for rate limiting
4. **Test Edge Cases**: 
   - Empty CSV files
   - Large CSV files (>10MB)
   - Invalid formats
   - Special characters in data
5. **Use Postman Console**: Debug request/response details

## CSV Format Requirements

### Example CSV Structure
```csv
date,subject,title,description,start_time,end_time,location
2024-01-15,Mathematics,Calculus Lecture,Advanced calculus topics,09:00,10:30,Room 101
2024-01-15,Physics,Lab Session,Quantum mechanics lab,14:00,16:00,Lab 2
```

### Required Fields (varies by template)
- `date`: Format YYYY-MM-DD
- `start_time`: Format HH:MM
- `end_time`: Format HH:MM
- `title` or `subject`: Event name

### Optional Fields
- `description`: Detailed information
- `location`: Venue/room
- `priority`: 1-5 scale
- `category`: Event type

## Notes
- File size limit: 10MB
- Supported formats: CSV, Excel (xlsx)
- Processing is asynchronous for large files
- Check import status regularly for processing updates