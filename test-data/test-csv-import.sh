#!/bin/bash

# CSV Import Test Script for Schedule Management API
# Usage: ./test-csv-import.sh

# Configuration
BASE_URL="${BASE_URL:-http://localhost:8000}"
API_ENDPOINT="${BASE_URL}/api/v1/schedule-imports"
USER_ID="${USER_ID:-1}"

# Colors for output
GREEN='\033[0;32m'
BLUE='\033[0;34m'
RED='\033[0;31m'
NC='\033[0m' # No Color

echo -e "${BLUE}=== Schedule Management API - CSV Import Testing ===${NC}"
echo ""

# Function to test CSV import
test_csv_import() {
    local csv_file=$1
    local description=$2
    
    echo -e "${GREEN}Testing: ${description}${NC}"
    echo "File: ${csv_file}"
    
    # Upload CSV file
    response=$(curl -s -X POST "${API_ENDPOINT}" \
        -H "Accept: application/json" \
        -F "import_type=file_upload" \
        -F "source_type=csv" \
        -F "file=@${csv_file}" \
        -F "user_id=${USER_ID}")
    
    # Check if successful
    if echo "$response" | grep -q '"success":true'; then
        echo -e "${GREEN}✓ Import successful${NC}"
        
        # Extract import ID
        import_id=$(echo "$response" | grep -o '"id":[0-9]*' | head -1 | cut -d: -f2)
        echo "Import ID: ${import_id}"
        
        # Get import statistics
        echo "Getting import details..."
        curl -s -X GET "${API_ENDPOINT}/${import_id}?user_id=${USER_ID}" \
            -H "Accept: application/json" | python3 -m json.tool | head -20
        
        echo ""
        return 0
    else
        echo -e "${RED}✗ Import failed${NC}"
        echo "$response" | python3 -m json.tool
        echo ""
        return 1
    fi
}

# Test 1: Basic Schedule Import
echo -e "${BLUE}Test 1: Basic Schedule Import${NC}"
test_csv_import "test-data/schedule_basic.csv" "Basic schedule with 10 entries"
echo "---"

# Test 2: Vietnamese Schedule Import  
echo -e "${BLUE}Test 2: Vietnamese Schedule Import${NC}"
test_csv_import "test-data/schedule_vietnamese.csv" "Vietnamese schedule data"
echo "---"

# Test 3: Minimal CSV (only required fields)
echo -e "${BLUE}Test 3: Minimal CSV Import${NC}"
test_csv_import "test-data/schedule_minimal.csv" "Minimal CSV with only required fields"
echo "---"

# Test 4: Manual Text Parsing
echo -e "${BLUE}Test 4: Manual Text Parsing${NC}"
echo "Testing manual text input..."

curl -s -X POST "${API_ENDPOINT}" \
    -H "Accept: application/json" \
    -H "Content-Type: application/json" \
    -d '{
        "import_type": "manual_input",
        "source_type": "manual",
        "raw_content": "Meeting with John at 2pm tomorrow in Conference Room A\nCall client at 10am on Friday\nProject deadline: January 31st",
        "user_id": '${USER_ID}'
    }' | python3 -m json.tool

echo ""
echo "---"

# Test 5: Get all imports for user
echo -e "${BLUE}Test 5: List All Imports${NC}"
echo "Getting all imports for user ${USER_ID}..."

curl -s -X GET "${API_ENDPOINT}?user_id=${USER_ID}" \
    -H "Accept: application/json" | python3 -m json.tool | head -30

echo ""
echo "---"

# Test 6: Convert entries to events
echo -e "${BLUE}Test 6: Convert Entries to Events${NC}"
echo "Enter Import ID to convert (or press Enter to skip): "
read import_id_to_convert

if [ ! -z "$import_id_to_convert" ]; then
    echo "Converting entries from import ${import_id_to_convert}..."
    
    curl -s -X POST "${API_ENDPOINT}/${import_id_to_convert}/convert?user_id=${USER_ID}" \
        -H "Accept: application/json" \
        -H "Content-Type: application/json" \
        -d '{"min_confidence": 0.5}' | python3 -m json.tool
    
    echo ""
fi

echo -e "${GREEN}=== Testing Complete ===${NC}"

# Additional useful cURL commands
echo ""
echo -e "${BLUE}Additional cURL Commands:${NC}"
echo ""
echo "# Get entries for a specific import:"
echo "curl -X GET \"${API_ENDPOINT}/\${IMPORT_ID}/entries?user_id=${USER_ID}\" -H \"Accept: application/json\" | python3 -m json.tool"
echo ""
echo "# Process/reprocess an import:"
echo "curl -X POST \"${API_ENDPOINT}/\${IMPORT_ID}/process?user_id=${USER_ID}\" -H \"Accept: application/json\" | python3 -m json.tool"
echo ""
echo "# Update an entry manually:"
echo "curl -X PATCH \"${API_ENDPOINT}/entries/\${ENTRY_ID}?user_id=${USER_ID}\" \\"
echo "  -H \"Content-Type: application/json\" \\"
echo "  -d '{\"parsed_title\": \"Updated Title\", \"manual_review_required\": false}' | python3 -m json.tool"
echo ""
echo "# Delete an import:"
echo "curl -X DELETE \"${API_ENDPOINT}/\${IMPORT_ID}?user_id=${USER_ID}\" -H \"Accept: application/json\""
echo ""
echo "# Get import statistics:"
echo "curl -X GET \"${API_ENDPOINT}/statistics?user_id=${USER_ID}\" -H \"Accept: application/json\" | python3 -m json.tool"