# Admin Customer Reporting Template API

## Overview
This API allows administrators to create, manage, and use templates for generating customer reports. The system tracks customer metrics, enables custom reporting filters, and supports automated report generation.

## Base URL
```
{{base_url}}/api/v1/admin/customer-reporting-templates
```

## Authentication
All endpoints require Bearer token authentication:
```
Authorization: Bearer {{token}}
```

## API Endpoints

### 1. List All Templates

**GET** `/api/v1/admin/customer-reporting-templates`

List all customer reporting templates with filtering and pagination.

**Query Parameters:**
- `is_active` (boolean): Filter by active status
- `frequency` (string): Filter by report frequency (daily, weekly, monthly, yearly)
- `is_default` (boolean): Filter by default status
- `search` (string): Search by template name
- `sort_by` (string): Sort field (default: created_at)
- `sort_order` (string): Sort direction (asc/desc, default: desc)
- `per_page` (integer): Items per page (default: 15)

**Example Request:**
```http
GET /api/v1/admin/customer-reporting-templates?is_active=true&frequency=monthly
Authorization: Bearer {{token}}
```

**Response:**
```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "template_name": "Monthly Customer Activity Report",
            "description": "Track customer login frequency and engagement",
            "customer_fields": ["name", "email", "profession_id", "workplace"],
            "report_filters": {
                "is_active": true,
                "created_at": "last_30_days"
            },
            "aggregation_rules": {
                "name": "count",
                "profession_id": "group_by"
            },
            "report_frequency": "monthly",
            "is_active": true,
            "is_default": true,
            "customer_limit": 1000,
            "total_reports_generated": 5,
            "success_rate": "98.50",
            "last_generated_at": "2024-11-25T10:30:00Z",
            "created_at": "2024-11-01T09:00:00Z",
            "creator": {
                "id": 1,
                "name": "Admin User",
                "email": "admin@example.com"
            }
        }
    ],
    "meta": {
        "current_page": 1,
        "last_page": 1,
        "per_page": 15,
        "total": 4
    }
}
```

### 2. Create New Template

**POST** `/api/v1/admin/customer-reporting-templates`

Create a new customer reporting template.

**Request Body:**
```json
{
    "template_name": "Weekly User Engagement Report",
    "description": "Track weekly user activity and feature usage",
    "customer_fields": ["name", "email", "profession_id", "is_active", "created_at"],
    "report_filters": {
        "is_active": true,
        "created_at": "last_7_days"
    },
    "aggregation_rules": {
        "name": "count",
        "email": "count",
        "profession_id": "group_by",
        "is_active": "count"
    },
    "report_frequency": "weekly",
    "notification_settings": {
        "email_recipients": ["admin@example.com"],
        "notify_on_generation": true,
        "include_summary": true
    },
    "is_active": true,
    "is_default": false,
    "customer_limit": 500
}
```

**Validation Rules:**
- `template_name`: required, string, max:255, unique
- `description`: nullable, string
- `customer_fields`: required, array, min:1
- `customer_fields.*`: string
- `report_filters`: nullable, array
- `aggregation_rules`: nullable, array
- `report_frequency`: required, in:daily,weekly,monthly,yearly
- `notification_settings`: nullable, array
- `is_active`: boolean
- `is_default`: boolean
- `customer_limit`: nullable, integer, min:1

**Response:**
```json
{
    "success": true,
    "message": "Template created successfully",
    "data": {
        "id": 5,
        "template_name": "Weekly User Engagement Report",
        "description": "Track weekly user activity and feature usage",
        "customer_fields": ["name", "email", "profession_id", "is_active", "created_at"],
        "report_frequency": "weekly",
        "is_active": true,
        "is_default": false,
        "created_by": 1,
        "created_at": "2024-11-26T11:30:00Z",
        "creator": {
            "id": 1,
            "name": "Admin User"
        }
    }
}
```

### 3. Get Template Details

**GET** `/api/v1/admin/customer-reporting-templates/{id}`

Get detailed information about a specific template.

**Response:**
```json
{
    "success": true,
    "data": {
        "id": 1,
        "template_name": "Monthly Customer Activity Report",
        "description": "Track customer login frequency and engagement",
        "customer_fields": ["name", "email", "profession_id", "workplace"],
        "report_filters": {
            "is_active": true,
            "created_at": "last_30_days"
        },
        "aggregation_rules": {
            "name": "count",
            "profession_id": "group_by"
        },
        "report_frequency": "monthly",
        "notification_settings": {
            "email_recipients": ["admin@example.com"],
            "notify_on_generation": true
        },
        "is_active": true,
        "is_default": true,
        "customer_limit": 1000,
        "total_reports_generated": 5,
        "success_rate": "98.50",
        "last_generated_at": "2024-11-25T10:30:00Z",
        "customer_count": 850,
        "is_at_limit": false,
        "creator": {
            "id": 1,
            "name": "Admin User",
            "email": "admin@example.com"
        }
    }
}
```

### 4. Update Template

**PUT** `/api/v1/admin/customer-reporting-templates/{id}`

Update an existing template.

**Request Body:** Same as create, but all fields are optional except validation requirements.

**Response:**
```json
{
    "success": true,
    "message": "Template updated successfully",
    "data": {
        // Updated template object
    }
}
```

### 5. Delete Template

**DELETE** `/api/v1/admin/customer-reporting-templates/{id}`

Delete a template permanently.

**Response:**
```json
{
    "success": true,
    "message": "Template deleted successfully"
}
```

### 6. Generate Report

**POST** `/api/v1/admin/customer-reporting-templates/{id}/generate-report`

Generate a report using the template configuration.

**Response:**
```json
{
    "success": true,
    "message": "Report generated successfully",
    "data": {
        "template_name": "Monthly Customer Activity Report",
        "generated_at": "2024-11-26T11:45:00Z",
        "total_customers": 850,
        "customer_data": {
            "name": 850,
            "email": 850,
            "profession_id": {
                "1": 200,
                "2": 350,
                "3": 300
            },
            "workplace": {
                "Hospital A": 150,
                "Clinic B": 200,
                "School C": 500
            }
        },
        "filters_applied": {
            "is_active": true,
            "created_at": "last_30_days"
        }
    }
}
```

### 7. Clone Template

**POST** `/api/v1/admin/customer-reporting-templates/{id}/clone`

Create a copy of an existing template.

**Response:**
```json
{
    "success": true,
    "message": "Template cloned successfully",
    "data": {
        "id": 6,
        "template_name": "Monthly Customer Activity Report (Copy)",
        "is_default": false,
        "total_reports_generated": 0,
        // ... other template fields
    }
}
```

### 8. Toggle Active Status

**PATCH** `/api/v1/admin/customer-reporting-templates/{id}/toggle-active`

Toggle the active status of a template.

**Response:**
```json
{
    "success": true,
    "message": "Template status updated successfully",
    "data": {
        "id": 1,
        "template_name": "Monthly Customer Activity Report",
        "is_active": false,
        // ... other fields
    }
}
```

### 9. Get Customer Statistics

**GET** `/api/v1/admin/customer-reporting-templates/stats/customers`

Get overall customer statistics for template configuration.

**Response:**
```json
{
    "success": true,
    "data": {
        "total_customers": 1250,
        "active_customers": 1100,
        "inactive_customers": 150,
        "customers_by_profession": {
            "Doctor": 400,
            "Teacher": 350,
            "Engineer": 300,
            "Nurse": 200
        },
        "available_fields": [
            "name", "email", "profession_id", "profession_level",
            "workplace", "department", "is_active", "created_at"
        ]
    }
}
```

## Template Configuration

### Customer Fields
Available customer fields for reporting:
- `name`: Customer full name
- `email`: Customer email address
- `profession_id`: Profession identifier
- `profession_level`: Professional level/seniority
- `workplace`: Workplace name
- `department`: Department/division
- `is_active`: Active status (boolean)
- `created_at`: Registration date

### Aggregation Rules
Available aggregation methods:
- `count`: Count total records
- `unique_count`: Count unique values
- `group_by`: Group and count by field value
- `avg`: Calculate average (for numeric fields)
- `sum`: Calculate sum (for numeric fields)

### Report Frequencies
- `daily`: Generate daily reports
- `weekly`: Generate weekly reports
- `monthly`: Generate monthly reports
- `yearly`: Generate yearly reports

### Filter Examples
```json
{
    "is_active": true,
    "profession_id": 1,
    "created_at": "last_30_days",
    "workplace": "Hospital A"
}
```

## Error Responses

### Validation Error (422)
```json
{
    "success": false,
    "message": "Validation failed",
    "errors": {
        "template_name": ["The template name field is required."],
        "customer_fields": ["The customer fields field is required."]
    }
}
```

### Not Found (404)
```json
{
    "success": false,
    "message": "Template not found",
    "error": "No query results for model [AdminCustomerReportingTemplate] 999"
}
```

### Server Error (500)
```json
{
    "success": false,
    "message": "Failed to create template",
    "error": "Database connection failed"
}
```

## Postman Collection Examples

### Environment Variables
```json
{
    "base_url": "http://localhost:8000",
    "token": "your_bearer_token_here"
}
```

### Sample Requests

#### 1. Create Basic Template
```http
POST {{base_url}}/api/v1/admin/customer-reporting-templates
Authorization: Bearer {{token}}
Content-Type: application/json

{
    "template_name": "Basic Customer Count",
    "description": "Simple customer counting template",
    "customer_fields": ["name", "email"],
    "report_frequency": "monthly",
    "is_active": true
}
```

#### 2. Generate Monthly Report
```http
POST {{base_url}}/api/v1/admin/customer-reporting-templates/1/generate-report
Authorization: Bearer {{token}}
```

#### 3. Get Templates with Filters
```http
GET {{base_url}}/api/v1/admin/customer-reporting-templates?is_active=true&frequency=weekly&per_page=5
Authorization: Bearer {{token}}
```

## Database Schema

```sql
CREATE TABLE admin_customer_reporting_templates (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    template_name VARCHAR(255) NOT NULL,
    description TEXT NULL,
    customer_fields JSON NOT NULL,
    report_filters JSON NULL,
    aggregation_rules JSON NULL,
    report_frequency VARCHAR(255) DEFAULT 'monthly',
    notification_settings JSON NULL,
    is_active BOOLEAN DEFAULT TRUE,
    is_default BOOLEAN DEFAULT FALSE,
    customer_limit INT NULL,
    created_by BIGINT NOT NULL,
    last_generated_at TIMESTAMP NULL,
    total_reports_generated INT DEFAULT 0,
    success_rate DECIMAL(5,2) DEFAULT 100.00,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_active_frequency (is_active, report_frequency),
    INDEX idx_creator_active (created_by, is_active)
);
```

## Testing the API

### 1. Run Migrations
```bash
php artisan migrate
```

### 2. Seed Sample Data (Optional)
```bash
php artisan db:seed --class=AdminCustomerReportingTemplateSeeder
```

### 3. Test with Postman
Import the example requests and test all endpoints with different scenarios:
- Create templates with various configurations
- Generate reports and verify data
- Test filtering and pagination
- Validate error handling

### 4. Common Test Scenarios
1. **Template Creation**: Test with valid and invalid data
2. **Report Generation**: Generate reports and verify aggregated data
3. **Filtering**: Test all filter combinations
4. **Pagination**: Test with different page sizes
5. **Permission**: Test with different user roles (if implemented)
6. **Edge Cases**: Empty data, large datasets, invalid IDs

## Notes
- This API assumes an admin role system (implement role middleware as needed)
- Report generation is synchronous; consider async processing for large datasets
- Customer data aggregation uses the current user table structure
- Notification settings are stored but notification sending logic needs implementation
- Consider rate limiting for report generation endpoints