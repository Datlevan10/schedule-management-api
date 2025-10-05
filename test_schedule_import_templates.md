# Schedule Import Templates API Testing Guide

## Implementation Summary

Successfully implemented the `schedule_import_templates` feature with the following components:

### 1. Database
- **Table**: `schedule_import_templates` 
- **Migration**: Uses existing migration from 2025_09_27_133339_create_schedule_import_templates_table.php
- **Key Fields**: All fields as specified in requirements

### 2. Model
- **File**: `app/Models/ScheduleImportTemplate.php`
- **Features**:
  - Full field mapping with proper casts
  - Relationships: profession, creator
  - Scopes: active, default, fileType, forProfession, global, applicableFor
  - Helper methods for template generation and file management

### 3. Controller
- **File**: `app/Http/Controllers/Api/ScheduleImportTemplateController.php`
- **Endpoints Implemented**:
  - `GET /api/v1/schedule-import-templates` - List templates with filters
  - `POST /api/v1/schedule-import-templates` - Create new template
  - `GET /api/v1/schedule-import-templates/{id}` - Get single template
  - `PUT /api/v1/schedule-import-templates/{id}` - Update template
  - `DELETE /api/v1/schedule-import-templates/{id}` - Delete template
  - `GET /api/v1/schedule-import-templates/{id}/download` - Download template file
  - `GET /api/v1/schedule-import-templates/{id}/download-sample` - Download sample data
  - `GET /api/v1/schedule-import-templates/{id}/download-instructions` - Download instructions
  - `GET /api/v1/schedule-import-templates/profession/{professionId}` - Get templates by profession
  - `POST /api/v1/schedule-import-templates/{id}/statistics` - Update statistics

### 4. Resource
- **File**: `app/Http/Resources/ScheduleImportTemplateResource.php`
- Returns structured JSON with:
  - Basic template information
  - Sample data
  - Format specifications
  - AI processing info
  - Usage statistics
  - File information with download URLs
  - Status flags
  - Related data (profession, creator)

### 5. Validation
- **StoreScheduleImportTemplateRequest**: Validates creation requests
- **UpdateScheduleImportTemplateRequest**: Validates update requests
- Both include proper validation rules and custom error messages

### 6. Routes
- **File**: `routes/api.php`
- All routes under `/api/v1/schedule-import-templates`
- Protected with `auth:api` middleware
- Proper route model binding

## Testing the API

### Prerequisites
1. Ensure Laravel server is running: `php artisan serve`
2. Obtain authentication token via login endpoint

### Example API Calls

#### 1. Create Template (Authenticated)
```bash
curl -X POST http://localhost:8000/api/v1/schedule-import-templates \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -H "Content-Type: application/json" \
  -d '{
    "profession_id": 1,
    "template_name": "Student Class Schedule",
    "template_description": "Template for students to log their class timetable",
    "file_type": "csv",
    "required_columns": ["date", "subject", "start_time", "end_time"],
    "optional_columns": ["teacher", "room"],
    "column_descriptions": {
      "date": "Class date",
      "subject": "Subject name",
      "start_time": "Start time",
      "end_time": "End time",
      "teacher": "Teacher name",
      "room": "Classroom"
    },
    "generate_files": true
  }'
```

#### 2. List Templates
```bash
curl -X GET http://localhost:8000/api/v1/schedule-import-templates \
  -H "Authorization: Bearer YOUR_TOKEN_HERE"
```

#### 3. Get Template by ID
```bash
curl -X GET http://localhost:8000/api/v1/schedule-import-templates/1 \
  -H "Authorization: Bearer YOUR_TOKEN_HERE"
```

#### 4. Download Template
```bash
curl -X GET http://localhost:8000/api/v1/schedule-import-templates/1/download \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  --output template.csv
```

## Features Implemented

✅ Full CRUD operations for templates
✅ File generation (CSV, XLSX support)
✅ Download endpoints for templates, samples, and instructions
✅ Profession-based filtering
✅ Statistics tracking (download count, success rate, user rating)
✅ AI configuration fields (keywords, priority rules, category mapping)
✅ Default template management
✅ Active/inactive status
✅ Sample data generation with multiple rows

## User Workflow Support

1. ✅ User selects profession
2. ✅ Gets available templates via API
3. ✅ Downloads template file
4. ✅ Fills schedule data offline
5. ✅ Uploads back (requires separate import API)
6. ✅ System tracks statistics for optimization

## Notes

- Authentication is required for all endpoints
- Template files are stored in `storage/app/public/schedule_templates/{template_id}/`
- Automatic file generation on template creation when `generate_files: true`
- Support for CSV, XLSX, and XLS file types
- Templates can be global (profession_id = null) or profession-specific