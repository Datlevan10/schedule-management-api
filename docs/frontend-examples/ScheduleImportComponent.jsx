import React, { useState, useEffect } from 'react';
import { Upload, FileText, AlertCircle, CheckCircle, TrendingUp, Download } from 'lucide-react';

/**
 * Complete Schedule Import Component with AI Analysis
 * Handles CSV upload, AI processing, and result summarization
 */
const ScheduleImportComponent = () => {
  const [importData, setImportData] = useState(null);
  const [entries, setEntries] = useState([]);
  const [analysis, setAnalysis] = useState(null);
  const [loading, setLoading] = useState(false);
  const [activeTab, setActiveTab] = useState('upload');
  
  const API_BASE = process.env.REACT_APP_API_URL || 'http://localhost:8000/api/v1';
  const USER_ID = 1; // Get from auth context

  // Upload CSV File
  const handleFileUpload = async (event) => {
    const file = event.target.files[0];
    if (!file) return;

    setLoading(true);
    const formData = new FormData();
    formData.append('file', file);
    formData.append('import_type', 'file_upload');
    formData.append('source_type', 'csv');
    formData.append('user_id', USER_ID);

    try {
      const response = await fetch(`${API_BASE}/schedule-imports`, {
        method: 'POST',
        body: formData
      });

      const data = await response.json();
      if (data.success) {
        setImportData(data.data);
        await fetchEntries(data.data.id);
        await analyzeImport(data.data.id);
        setActiveTab('analysis');
      }
    } catch (error) {
      console.error('Upload failed:', error);
    } finally {
      setLoading(false);
    }
  };

  // Fetch Import Entries
  const fetchEntries = async (importId) => {
    try {
      const response = await fetch(
        `${API_BASE}/schedule-imports/${importId}/entries?user_id=${USER_ID}`
      );
      const data = await response.json();
      if (data.success) {
        setEntries(data.data);
      }
    } catch (error) {
      console.error('Failed to fetch entries:', error);
    }
  };

  // Analyze Import with AI
  const analyzeImport = async (importId) => {
    try {
      // Get statistics
      const statsResponse = await fetch(
        `${API_BASE}/schedule-imports/statistics?user_id=${USER_ID}`
      );
      const stats = await statsResponse.json();

      // Analyze entries
      const entriesData = entries.length > 0 ? entries : await fetchEntriesForAnalysis(importId);
      
      const analysis = {
        totalEntries: entriesData.length,
        highConfidence: entriesData.filter(e => parseFloat(e.ai_analysis.confidence) >= 0.8).length,
        mediumConfidence: entriesData.filter(e => 
          parseFloat(e.ai_analysis.confidence) >= 0.5 && 
          parseFloat(e.ai_analysis.confidence) < 0.8
        ).length,
        lowConfidence: entriesData.filter(e => parseFloat(e.ai_analysis.confidence) < 0.5).length,
        requiresReview: entriesData.filter(e => e.status.manual_review_required).length,
        averageConfidence: calculateAverageConfidence(entriesData),
        stats: stats.data
      };

      setAnalysis(analysis);
    } catch (error) {
      console.error('Analysis failed:', error);
    }
  };

  // Helper function to fetch entries for analysis
  const fetchEntriesForAnalysis = async (importId) => {
    const response = await fetch(
      `${API_BASE}/schedule-imports/${importId}/entries?user_id=${USER_ID}`
    );
    const data = await response.json();
    return data.success ? data.data : [];
  };

  // Calculate average confidence
  const calculateAverageConfidence = (entries) => {
    if (entries.length === 0) return 0;
    const sum = entries.reduce((acc, e) => acc + parseFloat(e.ai_analysis.confidence), 0);
    return (sum / entries.length * 100).toFixed(1);
  };

  // Convert entries to events
  const handleConvert = async () => {
    if (!importData) return;

    setLoading(true);
    try {
      const response = await fetch(
        `${API_BASE}/schedule-imports/${importData.id}/convert?user_id=${USER_ID}`,
        {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ min_confidence: 0.5 })
        }
      );

      const data = await response.json();
      if (data.success) {
        alert(`Successfully converted ${data.data.successfully_converted} entries to events!`);
        await analyzeImport(importData.id);
      }
    } catch (error) {
      console.error('Conversion failed:', error);
    } finally {
      setLoading(false);
    }
  };

  // Export data
  const handleExport = async (format) => {
    if (!importData) return;

    try {
      const response = await fetch(
        `${API_BASE}/schedule-imports/${importData.id}/export?user_id=${USER_ID}&format=${format}`
      );
      
      const blob = await response.blob();
      const url = window.URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `schedule_${format}_${new Date().getTime()}.csv`;
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
      window.URL.revokeObjectURL(url);
    } catch (error) {
      console.error('Export failed:', error);
    }
  };

  // Update entry manually
  const handleUpdateEntry = async (entryId, updates) => {
    try {
      const response = await fetch(
        `${API_BASE}/schedule-imports/entries/${entryId}?user_id=${USER_ID}`,
        {
          method: 'PATCH',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(updates)
        }
      );

      const data = await response.json();
      if (data.success) {
        await fetchEntries(importData.id);
        await analyzeImport(importData.id);
      }
    } catch (error) {
      console.error('Update failed:', error);
    }
  };

  return (
    <div className="p-6 max-w-7xl mx-auto">
      {/* Header */}
      <div className="mb-6">
        <h1 className="text-3xl font-bold text-gray-900">Schedule Import & AI Analysis</h1>
        <p className="text-gray-600 mt-2">Upload CSV files for AI-powered schedule processing</p>
      </div>

      {/* Tabs */}
      <div className="border-b border-gray-200 mb-6">
        <nav className="-mb-px flex space-x-8">
          <button
            onClick={() => setActiveTab('upload')}
            className={`py-2 px-1 border-b-2 font-medium text-sm ${
              activeTab === 'upload' 
                ? 'border-blue-500 text-blue-600' 
                : 'border-transparent text-gray-500 hover:text-gray-700'
            }`}
          >
            Upload
          </button>
          <button
            onClick={() => setActiveTab('analysis')}
            disabled={!importData}
            className={`py-2 px-1 border-b-2 font-medium text-sm ${
              activeTab === 'analysis' 
                ? 'border-blue-500 text-blue-600' 
                : 'border-transparent text-gray-500 hover:text-gray-700'
            } ${!importData && 'opacity-50 cursor-not-allowed'}`}
          >
            AI Analysis
          </button>
          <button
            onClick={() => setActiveTab('entries')}
            disabled={!importData}
            className={`py-2 px-1 border-b-2 font-medium text-sm ${
              activeTab === 'entries' 
                ? 'border-blue-500 text-blue-600' 
                : 'border-transparent text-gray-500 hover:text-gray-700'
            } ${!importData && 'opacity-50 cursor-not-allowed'}`}
          >
            Entries ({entries.length})
          </button>
        </nav>
      </div>

      {/* Upload Tab */}
      {activeTab === 'upload' && (
        <div className="bg-white rounded-lg shadow p-6">
          <div className="border-2 border-dashed border-gray-300 rounded-lg p-12 text-center">
            <Upload className="mx-auto h-12 w-12 text-gray-400" />
            <p className="mt-2 text-sm text-gray-600">
              Upload a CSV file to import schedule data
            </p>
            <input
              type="file"
              accept=".csv"
              onChange={handleFileUpload}
              className="mt-4 px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700"
              disabled={loading}
            />
            {loading && <p className="mt-2 text-sm text-gray-500">Processing...</p>}
          </div>

          {/* Recent Imports */}
          {importData && (
            <div className="mt-6 p-4 bg-green-50 rounded-lg">
              <div className="flex items-center">
                <CheckCircle className="h-5 w-5 text-green-500 mr-2" />
                <p className="text-green-800">
                  Successfully imported {importData.total_records_found} records
                </p>
              </div>
            </div>
          )}
        </div>
      )}

      {/* Analysis Tab */}
      {activeTab === 'analysis' && analysis && (
        <div className="space-y-6">
          {/* Summary Cards */}
          <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div className="bg-white rounded-lg shadow p-6">
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-sm text-gray-600">Total Entries</p>
                  <p className="text-2xl font-bold">{analysis.totalEntries}</p>
                </div>
                <FileText className="h-8 w-8 text-gray-400" />
              </div>
            </div>

            <div className="bg-white rounded-lg shadow p-6">
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-sm text-gray-600">AI Confidence</p>
                  <p className="text-2xl font-bold">{analysis.averageConfidence}%</p>
                </div>
                <TrendingUp className="h-8 w-8 text-green-500" />
              </div>
            </div>

            <div className="bg-white rounded-lg shadow p-6">
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-sm text-gray-600">High Confidence</p>
                  <p className="text-2xl font-bold">{analysis.highConfidence}</p>
                </div>
                <CheckCircle className="h-8 w-8 text-blue-500" />
              </div>
            </div>

            <div className="bg-white rounded-lg shadow p-6">
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-sm text-gray-600">Needs Review</p>
                  <p className="text-2xl font-bold">{analysis.requiresReview}</p>
                </div>
                <AlertCircle className="h-8 w-8 text-yellow-500" />
              </div>
            </div>
          </div>

          {/* Confidence Distribution */}
          <div className="bg-white rounded-lg shadow p-6">
            <h3 className="text-lg font-semibold mb-4">Confidence Distribution</h3>
            <div className="space-y-3">
              <ConfidenceBar 
                label="High (≥80%)" 
                count={analysis.highConfidence} 
                total={analysis.totalEntries}
                color="bg-green-500"
              />
              <ConfidenceBar 
                label="Medium (50-79%)" 
                count={analysis.mediumConfidence} 
                total={analysis.totalEntries}
                color="bg-yellow-500"
              />
              <ConfidenceBar 
                label="Low (<50%)" 
                count={analysis.lowConfidence} 
                total={analysis.totalEntries}
                color="bg-red-500"
              />
            </div>
          </div>

          {/* Actions */}
          <div className="bg-white rounded-lg shadow p-6">
            <h3 className="text-lg font-semibold mb-4">Actions</h3>
            <div className="flex space-x-4">
              <button
                onClick={handleConvert}
                disabled={loading}
                className="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50"
              >
                Convert to Events
              </button>
              <button
                onClick={() => handleExport('standard')}
                className="px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700"
              >
                <Download className="inline h-4 w-4 mr-2" />
                Export Standard
              </button>
              <button
                onClick={() => handleExport('ai_enhanced')}
                className="px-4 py-2 bg-purple-600 text-white rounded-md hover:bg-purple-700"
              >
                <Download className="inline h-4 w-4 mr-2" />
                Export AI Enhanced
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Entries Tab */}
      {activeTab === 'entries' && (
        <div className="bg-white rounded-lg shadow overflow-hidden">
          <table className="min-w-full divide-y divide-gray-200">
            <thead className="bg-gray-50">
              <tr>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Title</th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Confidence</th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
              </tr>
            </thead>
            <tbody className="bg-white divide-y divide-gray-200">
              {entries.slice(0, 10).map((entry) => (
                <tr key={entry.id}>
                  <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    {entry.parsed_data.title || 'Untitled'}
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    {entry.parsed_data.start_datetime || 'No date'}
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap">
                    <ConfidenceBadge confidence={parseFloat(entry.ai_analysis.confidence)} />
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap">
                    <StatusBadge status={entry.status.processing} />
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap text-sm">
                    <button
                      onClick={() => handleUpdateEntry(entry.id, { manual_review_required: false })}
                      className="text-blue-600 hover:text-blue-900"
                    >
                      Approve
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
};

// Helper Components
const ConfidenceBar = ({ label, count, total, color }) => {
  const percentage = total > 0 ? (count / total * 100).toFixed(1) : 0;
  
  return (
    <div>
      <div className="flex justify-between text-sm mb-1">
        <span>{label}</span>
        <span>{count} ({percentage}%)</span>
      </div>
      <div className="w-full bg-gray-200 rounded-full h-2">
        <div 
          className={`${color} h-2 rounded-full`}
          style={{ width: `${percentage}%` }}
        />
      </div>
    </div>
  );
};

const ConfidenceBadge = ({ confidence }) => {
  const percentage = (confidence * 100).toFixed(0);
  let colorClass = 'bg-gray-100 text-gray-800';
  
  if (confidence >= 0.8) colorClass = 'bg-green-100 text-green-800';
  else if (confidence >= 0.5) colorClass = 'bg-yellow-100 text-yellow-800';
  else colorClass = 'bg-red-100 text-red-800';
  
  return (
    <span className={`px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${colorClass}`}>
      {percentage}%
    </span>
  );
};

const StatusBadge = ({ status }) => {
  let colorClass = 'bg-gray-100 text-gray-800';
  
  if (status === 'parsed') colorClass = 'bg-green-100 text-green-800';
  else if (status === 'failed') colorClass = 'bg-red-100 text-red-800';
  else if (status === 'pending') colorClass = 'bg-yellow-100 text-yellow-800';
  
  return (
    <span className={`px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${colorClass}`}>
      {status}
    </span>
  );
};

export default ScheduleImportComponent;