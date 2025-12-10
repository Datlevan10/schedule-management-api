/**
 * Schedule Management API Service
 * Handles all API calls for CSV import, AI analysis, and export
 */
class ScheduleAPIService {
  constructor(baseURL = 'http://localhost:8000/api/v1', userId = 1) {
    this.baseURL = baseURL;
    this.userId = userId;
  }

  // ========== CSV Import APIs ==========

  /**
   * Upload CSV file for import
   * @param {File} file - CSV file to upload
   * @param {Object} options - Additional options
   */
  async uploadCSV(file, options = {}) {
    const formData = new FormData();
    formData.append('file', file);
    formData.append('import_type', options.importType || 'file_upload');
    formData.append('source_type', options.sourceType || 'csv');
    formData.append('user_id', this.userId);
    
    if (options.templateId) {
      formData.append('template_id', options.templateId);
    }

    const response = await fetch(`${this.baseURL}/schedule-imports`, {
      method: 'POST',
      body: formData
    });

    return response.json();
  }

  /**
   * Import manual text for AI parsing
   * @param {string} text - Raw text to parse
   */
  async importManualText(text) {
    const response = await fetch(`${this.baseURL}/schedule-imports`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        import_type: 'manual_input',
        source_type: 'manual',
        raw_content: text,
        user_id: this.userId
      })
    });

    return response.json();
  }

  /**
   * Get list of all imports
   * @param {Object} filters - Query filters
   */
  async getImports(filters = {}) {
    const params = new URLSearchParams({
      user_id: this.userId,
      per_page: filters.perPage || 10,
      sort_by: filters.sortBy || 'created_at',
      sort_order: filters.sortOrder || 'desc',
      ...filters
    });

    const response = await fetch(`${this.baseURL}/schedule-imports?${params}`);
    return response.json();
  }

  /**
   * Get import details
   * @param {number} importId - Import ID
   */
  async getImportDetails(importId) {
    const response = await fetch(
      `${this.baseURL}/schedule-imports/${importId}?user_id=${this.userId}`
    );
    return response.json();
  }

  /**
   * Get import entries
   * @param {number} importId - Import ID
   * @param {Object} filters - Query filters
   */
  async getImportEntries(importId, filters = {}) {
    const params = new URLSearchParams({
      user_id: this.userId,
      ...filters
    });

    const response = await fetch(
      `${this.baseURL}/schedule-imports/${importId}/entries?${params}`
    );
    return response.json();
  }

  // ========== AI Processing APIs ==========

  /**
   * Process import with AI
   * @param {number} importId - Import ID
   * @param {number} templateId - Optional template ID
   */
  async processWithAI(importId, templateId = null) {
    const body = templateId ? { template_id: templateId } : {};
    
    const response = await fetch(
      `${this.baseURL}/schedule-imports/${importId}/process?user_id=${this.userId}`,
      {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body)
      }
    );

    return response.json();
  }

  /**
   * Convert entries to events with AI
   * @param {number} importId - Import ID
   * @param {number} minConfidence - Minimum confidence threshold
   * @param {Array} entryIds - Specific entry IDs (optional)
   */
  async convertToEvents(importId, minConfidence = 0.5, entryIds = []) {
    const body = {
      min_confidence: minConfidence
    };

    if (entryIds.length > 0) {
      body.entry_ids = entryIds;
    }

    const response = await fetch(
      `${this.baseURL}/schedule-imports/${importId}/convert?user_id=${this.userId}`,
      {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body)
      }
    );

    return response.json();
  }

  /**
   * Update entry with manual corrections
   * @param {number} entryId - Entry ID
   * @param {Object} updates - Fields to update
   */
  async updateEntry(entryId, updates) {
    const response = await fetch(
      `${this.baseURL}/schedule-imports/entries/${entryId}?user_id=${this.userId}`,
      {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(updates)
      }
    );

    return response.json();
  }

  /**
   * Get import statistics
   */
  async getStatistics() {
    const response = await fetch(
      `${this.baseURL}/schedule-imports/statistics?user_id=${this.userId}`
    );
    return response.json();
  }

  // ========== CSV Export APIs ==========

  /**
   * Export import data as CSV
   * @param {number} importId - Import ID
   * @param {string} format - Export format
   */
  async exportCSV(importId, format = 'standard') {
    const response = await fetch(
      `${this.baseURL}/schedule-imports/${importId}/export?user_id=${this.userId}&format=${format}`
    );
    
    if (!response.ok) {
      throw new Error('Export failed');
    }

    return response.blob();
  }

  /**
   * Export converted events as CSV
   * @param {number} importId - Import ID
   * @param {string} format - Export format
   */
  async exportEvents(importId, format = 'standard') {
    const response = await fetch(
      `${this.baseURL}/schedule-imports/${importId}/export-events?user_id=${this.userId}&format=${format}`
    );
    
    if (!response.ok) {
      throw new Error('Export failed');
    }

    return response.blob();
  }

  /**
   * Batch export multiple imports
   * @param {Array} importIds - Array of import IDs
   * @param {string} format - Export format
   */
  async exportBatch(importIds, format = 'standard') {
    const response = await fetch(
      `${this.baseURL}/schedule-imports/export-batch?user_id=${this.userId}`,
      {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          import_ids: importIds,
          format: format
        })
      }
    );

    if (!response.ok) {
      throw new Error('Batch export failed');
    }

    return response.blob();
  }

  /**
   * Preview export data
   * @param {number} importId - Import ID
   * @param {string} format - Export format
   * @param {number} limit - Number of entries to preview
   */
  async previewExport(importId, format = 'standard', limit = 5) {
    const response = await fetch(
      `${this.baseURL}/schedule-imports/${importId}/preview?user_id=${this.userId}&format=${format}&limit=${limit}`
    );
    return response.json();
  }

  // ========== AI Analysis Helpers ==========

  /**
   * Analyze import confidence scores
   * @param {Array} entries - Array of entries
   */
  analyzeConfidence(entries) {
    const scores = entries.map(e => parseFloat(e.ai_analysis?.confidence || 0));
    
    return {
      average: (scores.reduce((a, b) => a + b, 0) / scores.length).toFixed(2),
      min: Math.min(...scores),
      max: Math.max(...scores),
      distribution: {
        excellent: scores.filter(s => s >= 0.9).length,
        good: scores.filter(s => s >= 0.7 && s < 0.9).length,
        fair: scores.filter(s => s >= 0.5 && s < 0.7).length,
        poor: scores.filter(s => s < 0.5).length
      },
      requiresReview: entries.filter(e => e.status?.manual_review_required).length
    };
  }

  /**
   * Generate AI summary from import data
   * @param {Object} importData - Import data
   * @param {Array} entries - Import entries
   */
  generateAISummary(importData, entries) {
    const analysis = this.analyzeConfidence(entries);
    
    return {
      importId: importData.id,
      status: importData.status,
      summary: {
        totalRecords: importData.total_records_found,
        processedRecords: importData.successfully_processed,
        failedRecords: importData.failed_records,
        successRate: `${((importData.successfully_processed / importData.total_records_found) * 100).toFixed(1)}%`,
        aiConfidenceScore: importData.ai_confidence_score
      },
      confidenceAnalysis: analysis,
      recommendations: this.generateRecommendations(analysis, entries),
      issues: this.identifyIssues(entries)
    };
  }

  /**
   * Generate recommendations based on analysis
   */
  generateRecommendations(analysis, entries) {
    const recommendations = [];

    if (analysis.distribution.poor > 0) {
      recommendations.push({
        type: 'warning',
        priority: 'high',
        message: `${analysis.distribution.poor} entries have low confidence scores`,
        action: 'Review and manually correct these entries before conversion'
      });
    }

    if (analysis.requiresReview > 0) {
      recommendations.push({
        type: 'action',
        priority: 'medium',
        message: `${analysis.requiresReview} entries flagged for manual review`,
        action: 'Check flagged entries for accuracy'
      });
    }

    if (analysis.average < 0.7) {
      recommendations.push({
        type: 'improvement',
        priority: 'medium',
        message: 'Overall confidence score is below optimal level',
        action: 'Consider improving data quality or format consistency'
      });
    }

    // Check for missing critical fields
    const missingTitles = entries.filter(e => !e.parsed_data?.title).length;
    if (missingTitles > 0) {
      recommendations.push({
        type: 'error',
        priority: 'high',
        message: `${missingTitles} entries missing titles`,
        action: 'Add titles to these entries before conversion'
      });
    }

    return recommendations;
  }

  /**
   * Identify common issues in entries
   */
  identifyIssues(entries) {
    const issues = [];

    // Missing dates
    const missingDates = entries.filter(e => !e.parsed_data?.start_datetime);
    if (missingDates.length > 0) {
      issues.push({
        type: 'missing_date',
        count: missingDates.length,
        entries: missingDates.map(e => e.id)
      });
    }

    // Overlapping events
    const overlaps = this.findOverlaps(entries);
    if (overlaps.length > 0) {
      issues.push({
        type: 'overlapping_events',
        count: overlaps.length,
        entries: overlaps
      });
    }

    // Invalid priorities
    const invalidPriorities = entries.filter(e => {
      const priority = e.parsed_data?.priority;
      return priority && (priority < 1 || priority > 5);
    });
    if (invalidPriorities.length > 0) {
      issues.push({
        type: 'invalid_priority',
        count: invalidPriorities.length,
        entries: invalidPriorities.map(e => e.id)
      });
    }

    return issues;
  }

  /**
   * Find overlapping events
   */
  findOverlaps(entries) {
    const overlaps = [];
    const sortedEntries = entries
      .filter(e => e.parsed_data?.start_datetime && e.parsed_data?.end_datetime)
      .sort((a, b) => new Date(a.parsed_data.start_datetime) - new Date(b.parsed_data.start_datetime));

    for (let i = 0; i < sortedEntries.length - 1; i++) {
      const current = sortedEntries[i];
      const next = sortedEntries[i + 1];
      
      const currentEnd = new Date(current.parsed_data.end_datetime);
      const nextStart = new Date(next.parsed_data.start_datetime);
      
      if (currentEnd > nextStart) {
        overlaps.push({
          entry1: current.id,
          entry2: next.id,
          overlap: true
        });
      }
    }

    return overlaps;
  }

  // ========== Utility Functions ==========

  /**
   * Download CSV blob as file
   */
  downloadCSV(blob, filename = 'export.csv') {
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
  }

  /**
   * Parse CSV content for analysis
   */
  async parseCSVContent(blob) {
    const text = await blob.text();
    const lines = text.split('\n');
    const headers = lines[0].split(',').map(h => h.trim().replace(/^"|"$/g, ''));
    
    const data = [];
    for (let i = 1; i < lines.length; i++) {
      if (lines[i].trim()) {
        const values = lines[i].split(',').map(v => v.trim().replace(/^"|"$/g, ''));
        const row = {};
        headers.forEach((header, index) => {
          row[header] = values[index];
        });
        data.push(row);
      }
    }
    
    return { headers, data };
  }

  /**
   * Format date for display
   */
  formatDate(dateString) {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toLocaleString('en-US', {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    });
  }

  /**
   * Calculate processing time
   */
  calculateProcessingTime(startTime, endTime) {
    const start = new Date(startTime);
    const end = new Date(endTime);
    const diff = (end - start) / 1000; // seconds
    
    if (diff < 60) return `${diff.toFixed(1)} seconds`;
    if (diff < 3600) return `${(diff / 60).toFixed(1)} minutes`;
    return `${(diff / 3600).toFixed(1)} hours`;
  }
}

// Export for use in other modules
export default ScheduleAPIService;

// Usage Example:
/*
const api = new ScheduleAPIService();

// Upload CSV
const file = document.getElementById('fileInput').files[0];
const importResult = await api.uploadCSV(file);

// Get entries and analyze
const entries = await api.getImportEntries(importResult.data.id);
const summary = api.generateAISummary(importResult.data, entries.data);

// Convert to events
const conversion = await api.convertToEvents(importResult.data.id, 0.7);

// Export results
const csvBlob = await api.exportCSV(importResult.data.id, 'ai_enhanced');
api.downloadCSV(csvBlob, 'schedule_export.csv');
*/