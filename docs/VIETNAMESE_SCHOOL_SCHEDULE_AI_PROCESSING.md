# Vietnamese School Schedule AI Processing Guide

## Overview
This guide demonstrates how to process Vietnamese school schedule data (imported from CSV) and send it to AI for optimization.

## Sample Data from User ID 4
The user has imported 3 school schedule entries in Vietnamese:

### Entry 1: Math Class
- **Date**: 9/12/2025
- **Class**: Lớp 6A2 (Grade 6A2)
- **Subject**: Toán (Mathematics)
- **Time**: 7:00 - 7:45
- **Room**: E03
- **Note**: Món toán về hằng đẳng thức đáng nhớ (Math lesson on notable identities)

### Entry 2: Music Class
- **Date**: 10/12/2025
- **Class**: Lớp 9D4 (Grade 9D4)
- **Subject**: Môn âm nhạc (Music)
- **Time**: 13:30 - 14:15
- **Room**: B04
- **Note**: Học thuyết về âm nhạc (Music theory)

### Entry 3: English Midterm Exam
- **Date**: 11/12/2025
- **Class**: Lớp 7A1 (Grade 7A1)
- **Subject**: Thi môn tiếng anh giữa kì (English midterm exam)
- **Time**: 9:30 - 10:30
- **Room**: A01
- **Note**: Kì thi khảo sát chất lượng giữa kỳ quan trọng đánh giá năng lực (Important midterm quality assessment exam)

## Complete Workflow

### Step 1: Import CSV File
```bash
# Upload Vietnamese school schedule CSV
curl -X POST 'http://127.0.0.1:8000/api/v1/schedule-imports' \
  -F "file=@school_schedule.csv" \
  -F "import_type=file_upload" \
  -F "source_type=csv" \
  -F "user_id=4"
```

### Step 2: Retrieve Imported Events
```bash
# Get imported events for User 4
curl -X GET 'http://127.0.0.1:8000/api/v1/events/imported?user_id=4' \
  -H "Accept: application/json"
```

### Step 3: Send to AI for Processing
Assuming the imported events have IDs: 101, 102, 103

```bash
curl -X POST 'http://127.0.0.1:8000/api/v1/events/select-for-ai?user_id=4' \
  -H "Content-Type: application/json" \
  -d '{
    "event_ids": [101, 102, 103],
    "ai_task": "optimize",
    "context": "Tối ưu hóa lịch học cho trường học với 3 lớp khác nhau. Cần sắp xếp thời gian hợp lý, tránh xung đột phòng học và đảm bảo thời gian nghỉ giữa các tiết học.",
    "options": {
      "include_metadata": true,
      "include_ai_analysis": true,
      "school_schedule": true
    }
  }'
```

## JavaScript Implementation for Vietnamese School Schedule

```javascript
class VietnameseSchoolScheduleProcessor {
  constructor(baseURL = 'http://127.0.0.1:8000/api/v1') {
    this.baseURL = baseURL;
  }

  /**
   * Process Vietnamese school schedule data
   */
  async processSchoolSchedule(userId, scheduleData) {
    // Step 1: Parse the Vietnamese schedule data
    const parsedEntries = this.parseVietnameseSchedule(scheduleData.entries);
    
    // Step 2: Get imported events from API
    const importedEvents = await this.getImportedEvents(userId);
    
    // Step 3: Match and prepare for AI
    const matchedEvents = this.matchEvents(parsedEntries, importedEvents);
    
    // Step 4: Send to AI for optimization
    return this.sendToAI(userId, matchedEvents);
  }

  /**
   * Parse Vietnamese schedule format
   */
  parseVietnameseSchedule(entries) {
    return entries.map(entry => {
      const data = entry.original_data;
      
      // Convert Vietnamese time format
      const startTime = this.parseVietnameseTime(data.gio_bat_dau);
      const endTime = this.parseVietnameseTime(data.gio_ket_thuc);
      
      // Parse date (DD/MM/YYYY format)
      const [day, month, year] = data.ngay.split('/');
      const date = `${year}-${month.padStart(2, '0')}-${day.padStart(2, '0')}`;
      
      return {
        date: date,
        class: data.lop,
        subject: data.mon_hoc,
        startTime: startTime,
        endTime: endTime,
        room: data.phong,
        note: data.ghi_chu,
        // Determine priority based on event type
        priority: this.calculatePriority(data.mon_hoc),
        // AI-relevant metadata
        metadata: {
          isExam: data.mon_hoc.toLowerCase().includes('thi'),
          gradeLevel: this.extractGradeLevel(data.lop),
          subjectType: this.classifySubject(data.mon_hoc)
        }
      };
    });
  }

  /**
   * Parse Vietnamese time format (e.g., "7 giờ 45 phút" -> "07:45")
   */
  parseVietnameseTime(timeStr) {
    // Remove extra spaces
    timeStr = timeStr.trim();
    
    // Handle "X giờ" (X hours)
    if (timeStr.match(/^\d+\s*giờ$/)) {
      const hours = timeStr.match(/\d+/)[0];
      return `${hours.padStart(2, '0')}:00`;
    }
    
    // Handle "X giờ Y phút" (X hours Y minutes)
    const match = timeStr.match(/(\d+)\s*giờ\s*(\d+)\s*phút/);
    if (match) {
      const hours = match[1];
      const minutes = match[2];
      return `${hours.padStart(2, '0')}:${minutes.padStart(2, '0')}`;
    }
    
    // Handle "X giờ Y" (X hours Y - shorthand)
    const shortMatch = timeStr.match(/(\d+)\s*giờ\s*(\d+)/);
    if (shortMatch) {
      const hours = shortMatch[1];
      const minutes = shortMatch[2];
      return `${hours.padStart(2, '0')}:${minutes.padStart(2, '0')}`;
    }
    
    // Fallback: try to extract any numbers
    const numbers = timeStr.match(/\d+/g);
    if (numbers && numbers.length >= 2) {
      return `${numbers[0].padStart(2, '0')}:${numbers[1].padStart(2, '0')}`;
    }
    
    return timeStr; // Return as-is if parsing fails
  }

  /**
   * Calculate priority based on subject type
   */
  calculatePriority(subject) {
    const subjectLower = subject.toLowerCase();
    
    // Highest priority for exams
    if (subjectLower.includes('thi') || subjectLower.includes('kiểm tra')) {
      return 5;
    }
    
    // Core subjects get higher priority
    if (subjectLower.includes('toán') || 
        subjectLower.includes('văn') || 
        subjectLower.includes('anh')) {
      return 4;
    }
    
    // Science subjects
    if (subjectLower.includes('lý') || 
        subjectLower.includes('hóa') || 
        subjectLower.includes('sinh')) {
      return 3;
    }
    
    // Arts and other subjects
    return 2;
  }

  /**
   * Extract grade level from class name
   */
  extractGradeLevel(className) {
    const match = className.match(/Lớp\s*(\d+)/);
    return match ? parseInt(match[1]) : null;
  }

  /**
   * Classify subject type
   */
  classifySubject(subject) {
    const subjectLower = subject.toLowerCase();
    
    if (subjectLower.includes('thi')) return 'exam';
    if (subjectLower.includes('toán')) return 'math';
    if (subjectLower.includes('văn')) return 'literature';
    if (subjectLower.includes('anh')) return 'english';
    if (subjectLower.includes('lý')) return 'physics';
    if (subjectLower.includes('hóa')) return 'chemistry';
    if (subjectLower.includes('sinh')) return 'biology';
    if (subjectLower.includes('sử')) return 'history';
    if (subjectLower.includes('địa')) return 'geography';
    if (subjectLower.includes('nhạc')) return 'music';
    if (subjectLower.includes('thể dục')) return 'physical_education';
    
    return 'other';
  }

  /**
   * Get imported events from API
   */
  async getImportedEvents(userId) {
    const response = await fetch(
      `${this.baseURL}/events/imported?user_id=${userId}`
    );
    const data = await response.json();
    return data.data;
  }

  /**
   * Match parsed entries with imported events
   */
  matchEvents(parsedEntries, importedEvents) {
    // In a real scenario, you would match based on date/time/subject
    // For this example, we'll assume the first N events match
    return importedEvents.slice(0, parsedEntries.length).map(event => event.id);
  }

  /**
   * Send to AI for optimization
   */
  async sendToAI(userId, eventIds) {
    const response = await fetch(
      `${this.baseURL}/events/select-for-ai?user_id=${userId}`,
      {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          event_ids: eventIds,
          ai_task: 'optimize',
          context: this.generateVietnameseContext(),
          options: {
            include_metadata: true,
            include_ai_analysis: true,
            school_schedule: true,
            language: 'vi'
          }
        })
      }
    );
    
    return response.json();
  }

  /**
   * Generate context in Vietnamese for AI
   */
  generateVietnameseContext() {
    return `Tối ưu hóa lịch học cho trường học Việt Nam. 
    Yêu cầu:
    1. Sắp xếp thời gian học hợp lý cho học sinh
    2. Tránh xung đột phòng học
    3. Đảm bảo thời gian nghỉ giữa các tiết
    4. Ưu tiên các môn thi và môn chính vào buổi sáng
    5. Cân bằng giữa các môn học nặng và nhẹ`;
  }
}

// Usage Example
async function processUser4Schedule() {
  const processor = new VietnameseSchoolScheduleProcessor();
  
  // The data from User 4's CSV import
  const scheduleData = {
    entries: [
      {
        raw_text: "9/12/2025,Lớp 6A2,Toán,7 giờ,7 giờ 45 phút,E03,Món toán về hằng đẳng thức đáng nhớ",
        row_number: 2,
        original_data: {
          lop: "Lớp 6A2",
          ngay: "9/12/2025",
          phong: "E03",
          ghi_chu: "Món toán về hằng đẳng thức đáng nhớ",
          mon_hoc: "Toán",
          gio_bat_dau: "7 giờ",
          gio_ket_thuc: "7 giờ 45 phút"
        }
      },
      {
        raw_text: "10/12/2025,Lớp 9D4,Môn âm nhạc,13 giờ 30 phút,2 giờ 15 phút,B04,Học thuyết về âm nhạc",
        row_number: 3,
        original_data: {
          lop: "Lớp 9D4",
          ngay: "10/12/2025",
          phong: "B04",
          ghi_chu: "Học thuyết về âm nhạc",
          mon_hoc: "Môn âm nhạc",
          gio_bat_dau: "13 giờ 30 phút",
          gio_ket_thuc: "2 giờ 15 phút"  // Note: This seems incorrect (should be 14 giờ 15 phút)
        }
      },
      {
        raw_text: "11/12/2025,Lớp 7A1,Thi môn tiếng anh giữa kì,9 giờ 30 phút,10 giờ 30 phút,A01,Kì thi khảo sát chất lượng giữa kỳ quan trọng đánh giá năng lực",
        row_number: 4,
        original_data: {
          lop: "Lớp 7A1",
          ngay: "11/12/2025",
          phong: "A01",
          ghi_chu: "Kì thi khảo sát chất lượng giữa kỳ quan trọng đánh giá năng lực",
          mon_hoc: "Thi môn tiếng anh giữa kì",
          gio_bat_dau: "9 giờ 30 phút",
          gio_ket_thuc: "10 giờ 30 phút"
        }
      }
    ]
  };
  
  try {
    // Process the schedule
    const result = await processor.processSchoolSchedule(4, scheduleData);
    
    console.log('AI Processing Result:');
    console.log(`Selection ID: ${result.selection_id}`);
    console.log(`Events sent: ${result.data.events_count}`);
    console.log(`Task: ${result.data.task}`);
    console.log('Ready for AI optimization');
    
    // The AI will now process these events considering:
    // - School schedule constraints
    // - Vietnamese education system requirements
    // - Room availability
    // - Student workload balance
    
    return result;
  } catch (error) {
    console.error('Failed to process schedule:', error);
  }
}

// Run the processing
processUser4Schedule();
```

## React Component for Vietnamese School Schedule

```jsx
import React, { useState, useEffect } from 'react';

const VietnameseSchoolScheduleManager = ({ userId = 4 }) => {
  const [scheduleData, setScheduleData] = useState(null);
  const [importedEvents, setImportedEvents] = useState([]);
  const [processing, setProcessing] = useState(false);
  const [aiResult, setAiResult] = useState(null);

  // Sample data structure
  const sampleSchedule = {
    entries: [
      {
        original_data: {
          lop: "Lớp 6A2",
          ngay: "9/12/2025",
          phong: "E03",
          ghi_chu: "Món toán về hằng đẳng thức đáng nhớ",
          mon_hoc: "Toán",
          gio_bat_dau: "7 giờ",
          gio_ket_thuc: "7 giờ 45 phút"
        }
      },
      {
        original_data: {
          lop: "Lớp 9D4",
          ngay: "10/12/2025",
          phong: "B04",
          ghi_chu: "Học thuyết về âm nhạc",
          mon_hoc: "Môn âm nhạc",
          gio_bat_dau: "13 giờ 30 phút",
          gio_ket_thuc: "14 giờ 15 phút"
        }
      },
      {
        original_data: {
          lop: "Lớp 7A1",
          ngay: "11/12/2025",
          phong: "A01",
          ghi_chu: "Kì thi khảo sát chất lượng giữa kỳ",
          mon_hoc: "Thi môn tiếng anh giữa kì",
          gio_bat_dau: "9 giờ 30 phút",
          gio_ket_thuc: "10 giờ 30 phút"
        }
      }
    ]
  };

  useEffect(() => {
    // Load imported events on mount
    loadImportedEvents();
  }, [userId]);

  const loadImportedEvents = async () => {
    try {
      const response = await fetch(
        `http://127.0.0.1:8000/api/v1/events/imported?user_id=${userId}`
      );
      const data = await response.json();
      setImportedEvents(data.data);
      setScheduleData(sampleSchedule); // In real app, this would come from the import
    } catch (error) {
      console.error('Failed to load events:', error);
    }
  };

  const sendToAIOptimization = async () => {
    if (importedEvents.length === 0) {
      alert('Không có sự kiện nào để tối ưu hóa');
      return;
    }

    setProcessing(true);
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
            context: 'Tối ưu hóa lịch học cho trường học. Ưu tiên các môn thi, sắp xếp thời gian học hợp lý.',
            options: {
              include_metadata: true,
              include_ai_analysis: true,
              school_schedule: true
            }
          })
        }
      );
      
      const result = await response.json();
      setAiResult(result);
      
      if (result.status === 'success') {
        alert(`Đã gửi ${result.data.events_count} sự kiện để AI tối ưu hóa`);
      }
    } catch (error) {
      console.error('Lỗi khi gửi đến AI:', error);
      alert('Không thể xử lý yêu cầu');
    } finally {
      setProcessing(false);
    }
  };

  return (
    <div className="vietnamese-schedule-manager">
      <h2>Quản lý Lịch Học - User {userId}</h2>
      
      {/* Display Schedule Data */}
      {scheduleData && (
        <div className="schedule-preview">
          <h3>Dữ liệu Lịch Học Đã Import</h3>
          <table className="schedule-table">
            <thead>
              <tr>
                <th>Ngày</th>
                <th>Lớp</th>
                <th>Môn học</th>
                <th>Thời gian</th>
                <th>Phòng</th>
                <th>Ghi chú</th>
              </tr>
            </thead>
            <tbody>
              {scheduleData.entries.map((entry, index) => (
                <tr key={index}>
                  <td>{entry.original_data.ngay}</td>
                  <td>{entry.original_data.lop}</td>
                  <td>{entry.original_data.mon_hoc}</td>
                  <td>{entry.original_data.gio_bat_dau} - {entry.original_data.gio_ket_thuc}</td>
                  <td>{entry.original_data.phong}</td>
                  <td>{entry.original_data.ghi_chu}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {/* AI Optimization Section */}
      <div className="ai-optimization">
        <h3>Tối Ưu Hóa với AI</h3>
        
        <div className="optimization-options">
          <p>Tổng số sự kiện đã import: {importedEvents.length}</p>
          
          <button 
            onClick={sendToAIOptimization}
            disabled={processing || importedEvents.length === 0}
            className="optimize-btn"
          >
            {processing ? 'Đang xử lý...' : 'Gửi đến AI để Tối Ưu Hóa'}
          </button>
        </div>

        {/* AI Result Display */}
        {aiResult && aiResult.status === 'success' && (
          <div className="ai-result">
            <h4>Kết quả AI</h4>
            <p>ID Xử lý: {aiResult.selection_id}</p>
            <p>Số sự kiện: {aiResult.data.events_count}</p>
            <p>Nhiệm vụ: {aiResult.data.task}</p>
            <p>Trạng thái: Sẵn sàng cho AI</p>
          </div>
        )}
      </div>

      {/* Summary Statistics */}
      <div className="statistics">
        <h3>Thống kê</h3>
        <ul>
          <li>Tổng số tiết học: {scheduleData?.entries.length || 0}</li>
          <li>Số lớp khác nhau: {new Set(scheduleData?.entries.map(e => e.original_data.lop)).size || 0}</li>
          <li>Số môn học: {new Set(scheduleData?.entries.map(e => e.original_data.mon_hoc)).size || 0}</li>
          <li>Có kỳ thi: {scheduleData?.entries.some(e => e.original_data.mon_hoc.toLowerCase().includes('thi')) ? 'Có' : 'Không'}</li>
        </ul>
      </div>
    </div>
  );
};

export default VietnameseSchoolScheduleManager;
```

## Python Implementation

```python
import requests
import json
from datetime import datetime

class VietnameseScheduleAIProcessor:
    def __init__(self, base_url='http://127.0.0.1:8000/api/v1'):
        self.base_url = base_url
    
    def process_user4_schedule(self):
        """Process User 4's Vietnamese school schedule"""
        
        # User 4's schedule data
        schedule_data = {
            "entries": [
                {
                    "original_data": {
                        "lop": "Lớp 6A2",
                        "ngay": "9/12/2025",
                        "phong": "E03",
                        "ghi_chu": "Món toán về hằng đẳng thức đáng nhớ",
                        "mon_hoc": "Toán",
                        "gio_bat_dau": "7 giờ",
                        "gio_ket_thuc": "7 giờ 45 phút"
                    }
                },
                {
                    "original_data": {
                        "lop": "Lớp 9D4",
                        "ngay": "10/12/2025",
                        "phong": "B04",
                        "ghi_chu": "Học thuyết về âm nhạc",
                        "mon_hoc": "Môn âm nhạc",
                        "gio_bat_dau": "13 giờ 30 phút",
                        "gio_ket_thuc": "14 giờ 15 phút"
                    }
                },
                {
                    "original_data": {
                        "lop": "Lớp 7A1",
                        "ngay": "11/12/2025",
                        "phong": "A01",
                        "ghi_chu": "Kì thi khảo sát chất lượng giữa kỳ",
                        "mon_hoc": "Thi môn tiếng anh giữa kì",
                        "gio_bat_dau": "9 giờ 30 phút",
                        "gio_ket_thuc": "10 giờ 30 phút"
                    }
                }
            ]
        }
        
        # Step 1: Get imported events for User 4
        print("Lấy danh sách sự kiện đã import cho User 4...")
        imported_events = self.get_imported_events(4)
        
        if not imported_events:
            print("Không tìm thấy sự kiện nào đã import")
            return None
        
        print(f"Tìm thấy {len(imported_events)} sự kiện")
        
        # Step 2: Extract event IDs
        event_ids = [event['id'] for event in imported_events[:3]]  # Get first 3 events
        
        # Step 3: Send to AI for optimization
        print(f"Gửi {len(event_ids)} sự kiện đến AI để tối ưu hóa...")
        result = self.send_to_ai_optimization(4, event_ids)
        
        return result
    
    def get_imported_events(self, user_id):
        """Get imported events for a user"""
        response = requests.get(
            f"{self.base_url}/events/imported",
            params={'user_id': user_id}
        )
        
        if response.status_code == 200:
            data = response.json()
            return data.get('data', [])
        return []
    
    def send_to_ai_optimization(self, user_id, event_ids):
        """Send events to AI for optimization"""
        
        # Vietnamese context for school schedule optimization
        context = """
        Tối ưu hóa lịch học cho trường học với các yêu cầu:
        1. Môn thi cần được ưu tiên và có thời gian chuẩn bị
        2. Các lớp khác nhau không được xung đột phòng học
        3. Cân bằng thời gian học giữa các môn
        4. Môn học chính (Toán, Văn, Anh) nên học vào buổi sáng
        5. Đảm bảo thời gian nghỉ giữa các tiết học
        """
        
        payload = {
            "event_ids": event_ids,
            "ai_task": "optimize",
            "context": context,
            "options": {
                "include_metadata": True,
                "include_ai_analysis": True,
                "school_schedule": True,
                "language": "vi"
            }
        }
        
        response = requests.post(
            f"{self.base_url}/events/select-for-ai",
            params={'user_id': user_id},
            json=payload
        )
        
        if response.status_code == 200:
            result = response.json()
            
            print("\n✅ Kết quả xử lý AI:")
            print(f"   - Selection ID: {result.get('selection_id')}")
            print(f"   - Số sự kiện: {result['data']['events_count']}")
            print(f"   - Nhiệm vụ: {result['data']['task']}")
            print(f"   - Trạng thái: {'Sẵn sàng' if result['data']['ready_for_ai'] else 'Chưa sẵn sàng'}")
            
            return result
        else:
            print(f"❌ Lỗi: {response.status_code}")
            print(response.text)
            return None

# Execute
if __name__ == "__main__":
    processor = VietnameseScheduleAIProcessor()
    result = processor.process_user4_schedule()
    
    if result:
        print("\n📊 Tóm tắt:")
        print(f"Đã gửi thành công lịch học của User 4 đến AI")
        print(f"AI sẽ tối ưu hóa {result['data']['events_count']} sự kiện")
        print(f"Theo dõi tiến trình với ID: {result['selection_id']}")
```

## Key Features for Vietnamese School Schedule

1. **Time Format Handling**: Converts Vietnamese time format ("7 giờ 45 phút") to standard format
2. **Priority Assignment**: Exams get highest priority, core subjects get higher priority
3. **Grade Level Extraction**: Extracts grade level from class names (Lớp 6A2 → Grade 6)
4. **Subject Classification**: Categorizes subjects (Math, English, Music, etc.)
5. **Context in Vietnamese**: Provides optimization context in Vietnamese for better AI understanding

## AI Optimization Considerations

The AI will optimize the schedule considering:
- **Vietnamese school system requirements**
- **Room availability and conflicts**
- **Student workload balance**
- **Exam preparation time**
- **Core subjects in morning slots**
- **Break times between classes**

---

**Version**: 1.0  
**Last Updated**: December 2025