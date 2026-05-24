import React, { useRef, useState, useEffect } from 'react';
import {
  Box,
  Typography,
  Paper,
  Grid,
  FormControl,
  InputLabel,
  Select,
  MenuItem,
  TextField,
  Button,
  Autocomplete,
  Tooltip,
  Dialog,
  DialogTitle,
  DialogContent,
  LinearProgress,
  Alert,
} from '@mui/material';
import { Download, CloudUpload, CheckCircle, Error as ErrorIcon } from '@mui/icons-material';
import { useTranslation } from 'react-i18next';
import { useNavigate } from 'react-router-dom';
import ImportPreview from '../components/ImportPreview';
import { buildUrl as helpersBuildUrl, authHeaders } from '../components/ImportHelpers';
import { saveBlob, downloadJsonObject } from '../components/DownloadHelpers';
import { useVehicles } from '../contexts/VehiclesContext';
import { demoGuard } from '../utils/demoMode';

const ImportExport = () => {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const { refreshVehicles } = useVehicles();
  const [status, setStatus] = useState('all');
  const [selectedVehicles, setSelectedVehicles] = useState([]);
  const [elements, setElements] = useState([]);
  const [imageTypes, setImageTypes] = useState([]);
  const [vehicles, setVehicles] = useState([]);
  const [opStatus, setOpStatus] = useState('');
  const [importPreviewOpen, setImportPreviewOpen] = useState(false);
  const [importPreviewData, setImportPreviewData] = useState(null);
  const [importFileName, setImportFileName] = useState('');
  const [importModalOpen, setImportModalOpen] = useState(false);
  const [importProgress, setImportProgress] = useState(0);
  const [importStatus, setImportStatus] = useState('uploading'); // 'uploading', 'processing', 'success', 'error'
  const [importMessage, setImportMessage] = useState('');
  const [importError, setImportError] = useState('');
  const [exportModalOpen, setExportModalOpen] = useState(false);
  const [exportProgress, setExportProgress] = useState(0);
  const [exportStatus, setExportStatus] = useState('processing'); // 'processing', 'downloading', 'success', 'error'
  const [exportMessage, setExportMessage] = useState('');
  const [exportError, setExportError] = useState('');
  const [exportStageHistory, setExportStageHistory] = useState([]);
  const [activeExportJobId, setActiveExportJobId] = useState('');
  const [activeExportStatusUrl, setActiveExportStatusUrl] = useState('');
  const [isCancellingExport, setIsCancellingExport] = useState(false);

  const cancelExportRef = useRef(false);

  const apiBase = process.env.REACT_APP_API_URL || '';
  const auth = authHeaders;

  // Helper: build full URL (apiBase + path) with provided params object.
  const buildUrl = (path, params) => helpersBuildUrl(apiBase, path, params);

  const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

  const formatDuration = (totalSeconds) => {
    const safe = Math.max(0, Number(totalSeconds) || 0);
    const minutes = Math.floor(safe / 60);
    const seconds = safe % 60;
    if (minutes <= 0) {
      return `${seconds}s`;
    }
    return `${minutes}m ${String(seconds).padStart(2, '0')}s`;
  };

  const normalizeExportError = (error, fallback) => {
    const message = (error && error.message) ? String(error.message) : '';
    if (/operation was aborted|aborterror|the user aborted a request/i.test(message)) {
      return `${fallback}: Download stream was interrupted. Please retry export.`;
    }
    if (/networkerror|failed to fetch|network request failed/i.test(message)) {
      return `${fallback}: Network connection interrupted while exporting. Please retry.`;
    }
    if (/timed out|timeout/i.test(message)) {
      return `${fallback}: The export is taking longer than expected. Please retry.`;
    }
    return message || fallback;
  };

  const getStageLabel = (stage, status) => {
    const stageKey = String(stage || '').toLowerCase();
    const stageMap = {
      queued: 'Queued',
      prepare: 'Preparing',
      db_export: 'Exporting Database',
      json_write: 'Writing Export Files',
      zip_prepare: 'Creating ZIP',
      zip_pack: 'Adding Files to ZIP',
      zip_finalize: 'Finalizing ZIP',
      completed: 'Completed',
      failed: 'Failed',
    };

    if (stageMap[stageKey]) {
      return stageMap[stageKey];
    }

    if (status === 'completed') return 'Completed';
    if (status === 'failed') return 'Failed';
    return 'Processing';
  };

  const formatStageTimestamp = (value) => {
    const date = value ? new Date(value) : new Date();
    if (Number.isNaN(date.getTime())) {
      return new Date().toLocaleTimeString();
    }
    return date.toLocaleTimeString([], { hour12: false });
  };

  const pushStageHistory = (stage, message, timestamp) => {
    const stageText = String(stage || 'Processing');
    const messageText = String(message || 'Working...');
    const timeText = formatStageTimestamp(timestamp);

    setExportStageHistory((prev) => {
      const last = prev[prev.length - 1];
      if (last && last.stage === stageText && last.message === messageText) {
        return prev;
      }

      const next = [
        ...prev,
        {
          id: `${Date.now()}-${Math.random().toString(16).slice(2, 8)}`,
          stage: stageText,
          message: messageText,
          time: timeText,
        },
      ];

      return next.slice(-5);
    });
  };

  const getCancelUrl = () => {
    if (activeExportStatusUrl) {
      return `${activeExportStatusUrl}/cancel`;
    }

    if (activeExportJobId) {
      return `/api/vehicles/export-zip-jobs/${activeExportJobId}/cancel`;
    }

    return '';
  };

  const cancelFullExport = async () => {
    if (isCancellingExport) {
      return;
    }

    cancelExportRef.current = true;
    setIsCancellingExport(true);
    pushStageHistory('Cancelling', 'Cancellation requested. Stopping export worker...', new Date().toISOString());

    try {
      const cancelUrl = getCancelUrl();
      if (cancelUrl) {
        const resp = await fetch(cancelUrl, { method: 'POST', headers: auth() });
        if (!resp.ok) {
          const cancelErr = await getApiErrorMessage(resp, 'Failed to cancel export');
          throw new Error(cancelErr);
        }
      }

      setExportStatus('error');
      setExportError('Export cancelled by user.');
      setExportMessage('Export cancelled by user.');
      setOpStatus('Export cancelled');
      pushStageHistory('Cancelled', 'Export cancelled by user.', new Date().toISOString());
    } catch (err) {
      const msg = normalizeExportError(err, 'Failed to cancel export');
      setExportStatus('error');
      setExportError(msg);
      setExportMessage(msg);
      setOpStatus(msg);
      pushStageHistory('Cancel Failed', msg, new Date().toISOString());
    } finally {
      setIsCancellingExport(false);
      setActiveExportJobId('');
      setActiveExportStatusUrl('');
    }
  };

  const findJsonPayloadEnd = (raw) => {
    if (!raw) return null;
    let i = 0;
    while (i < raw.length && /\s/.test(raw[i])) i += 1;
    if (i >= raw.length) return null;

    const first = raw[i];
    if (first !== '{' && first !== '[') return null;

    const stack = [first === '{' ? '}' : ']'];
    let inString = false;
    let escaped = false;

    for (let idx = i + 1; idx < raw.length; idx += 1) {
      const ch = raw[idx];

      if (inString) {
        if (escaped) {
          escaped = false;
        } else if (ch === '\\') {
          escaped = true;
        } else if (ch === '"') {
          inString = false;
        }
        continue;
      }

      if (ch === '"') {
        inString = true;
        continue;
      }

      if (ch === '{') stack.push('}');
      if (ch === '[') stack.push(']');
      if (ch === '}' || ch === ']') {
        if (stack.length === 0) return null;
        const expected = stack.pop();
        if (expected !== ch) return null;
        if (stack.length === 0) return idx + 1;
      }
    }

    return null;
  };

  const parseJsonResponse = async (resp, fallbackMessage = 'Invalid JSON response') => {
    if (typeof resp?.text !== 'function') {
      if (typeof resp?.json === 'function') {
        return resp.json();
      }
      throw new Error(`${fallbackMessage}: Response body reader is unavailable`);
    }

    const raw = await resp.text();

    try {
      return JSON.parse(raw);
    } catch (err) {
      const payloadEnd = findJsonPayloadEnd(raw);
      if (payloadEnd && payloadEnd < raw.length) {
        const trimmed = raw.slice(0, payloadEnd);
        try {
          return JSON.parse(trimmed);
        } catch (trimErr) {
          // Fall through to throw below.
        }
      }

      const compact = String(raw || '').replace(/\s+/g, ' ').slice(0, 220);
      throw new Error(`${fallbackMessage}: ${compact || (err && err.message) || 'Malformed JSON'}`);
    }
  };

  // Extract useful API error details even when the server/proxy returns HTML/text.
  const getApiErrorMessage = async (resp, fallbackMessage) => {
    const status = [resp.status, resp.statusText].filter(Boolean).join(' ').trim();

    try {
      const errorData = await resp.clone().json();
      const fromJson = errorData?.error || errorData?.message;
      if (fromJson) {
        return fromJson;
      }
    } catch (e) {
      // Not JSON, continue to text extraction.
    }

    try {
      const text = (await resp.text()).trim();
      if (text) {
        const compact = text.replace(/\s+/g, ' ').slice(0, 220);
        return status
          ? `${fallbackMessage} (${status}): ${compact}`
          : `${fallbackMessage}: ${compact}`;
      }
    } catch (e) {
      // Ignore text parse errors.
    }

    return status ? `${fallbackMessage} (${status})` : fallbackMessage;
  };

  const fetchBlobWithRetry = async (url, headers, maxAttempts = 3) => {
    let lastError = null;

    for (let attempt = 1; attempt <= maxAttempts; attempt += 1) {
      try {
        const response = await fetch(url, { headers });
        if (!response.ok) {
          const msg = await getApiErrorMessage(response, 'Failed to download export archive');
          throw new Error(msg);
        }

        const blob = await response.blob();
        return blob;
      } catch (error) {
        lastError = error;
        const msg = String(error?.message || '');
        const isTransientAbort = /operation was aborted|aborterror|failed to fetch|networkerror/i.test(msg);

        if (!isTransientAbort || attempt >= maxAttempts) {
          break;
        }

        await sleep(500 * attempt);
      }
    }

    throw lastError || new Error('Failed to download export archive');
  };

  useEffect(() => {
    let mounted = true;
    (async () => {
      try {
        const resp = await fetch(apiBase + '/vehicles', { headers: auth() });
        if (!mounted) return;
        if (!resp.ok) return;
        const json = await resp.json();
        setVehicles(Array.isArray(json) ? json : (json.data || []));
      } catch (err) {
        // ignore
      }
    })();
    return () => { mounted = false; };
  }, [apiBase]);

  const elementOptions = [
    { value: 'parts', label: t('importExport.elem.parts') },
    { value: 'consumables', label: t('importExport.elem.consumables') },
    { value: 'service', label: t('importExport.elem.service') },
    { value: 'mot', label: t('importExport.elem.mot') },
    { value: 'attachments', label: t('importExport.elem.attachments') },
    { value: 'history', label: t('importExport.elem.history') },
    { value: 'consumption', label: t('importExport.elem.fuel') },
  ];

  const sortedElementOptions = [...elementOptions].sort((a, b) => a.label.localeCompare(b.label));
  const elementAllOption = { value: '__all__', label: t('importExport.selectAllElements') };
  const optionsWithAllElements = [elementAllOption, ...sortedElementOptions];

  const imageOptions = [
    { value: 'photos', label: t('importExport.imgPhotos') },
    { value: 'fuel_receipts', label: t('importExport.imgFuelReceipts') },
    { value: 'service_receipts', label: t('importExport.imgServiceReceipts') },
    { value: 'consumable_receipts', label: t('importExport.imgConsumableReceipts') },
    { value: 'attachments', label: t('importExport.imgAttachments') },
    { value: 'pdfs', label: t('importExport.imgPDFs') },
    { value: 'documents', label: t('importExport.imgDocuments') },
    { value: 'invoices', label: t('importExport.imgInvoices') },
    { value: 'mot_certificates', label: t('importExport.imgMotCertificates') },
    { value: 'other', label: t('importExport.imgOther') },
  ];

  async function exportJson(mode = 'select', filters = {}, options = {}) {
    try {
      const { reuseModal = false } = options || {};
      const { status = 'all', vehicleIds = [], elements = [], images = false, receipts = false, imageTypes = [] } = filters || {};
      const isAll = mode === 'all';
      if (!reuseModal) {
        setExportModalOpen(true);
      }
      setExportStatus('processing');
      setExportProgress(10);
      setExportMessage(isAll
        ? (t('importExport.modal.exportingAll') || 'Preparing full export...')
        : (t('importExport.modal.exportingJson') || 'Preparing export...'));
      setOpStatus(isAll ? t('importExport.op.downloadingAll') : t('importExport.op.downloadingJson'));
      let url;
      if (isAll) {
        url = buildUrl('/vehicles/export', { all: 1 });
      } else {
        url = buildUrl('/vehicles/export', {
          status: (status && status !== 'all') ? status : undefined,
          vehicleIds: vehicleIds && vehicleIds.length ? vehicleIds : undefined,
          elements: elements && elements.length ? elements : undefined,
          images: images ? true : undefined,
          receipts: receipts ? true : undefined,
          imageTypes: imageTypes && imageTypes.length ? imageTypes : undefined,
        });
      }
      const resp = await fetch(url, { headers: auth() });
      if (!resp.ok) throw new Error('Export failed');
      setExportProgress(60);
      const json = await parseJsonResponse(resp, 'Export response was not valid JSON');
      setExportProgress(85);
      const filename = isAll
        ? `vehicles_export_all_${new Date().toISOString().split('T')[0]}.json`
        : `vehicles_export_${new Date().toISOString().split('T')[0]}.json`;
      downloadJsonObject(json, filename.replace(/\.json$/, ''));
      setExportProgress(100);
      setExportStatus('success');
      setExportMessage(isAll
        ? (t('importExport.modal.exportSuccessAll') || 'Full export downloaded')
        : (t('importExport.modal.exportSuccess') || 'Export downloaded'));
      setTimeout(() => setExportModalOpen(false), 1000);
      setOpStatus(isAll ? t('importExport.op.downloadedAll') : t('importExport.op.downloadedJson'));
    } catch (err) {
      setExportProgress(0);
      setExportStatus('error');
      setExportError(err.message || 'Export failed');
      setOpStatus(t('importExport.op.error') + ': ' + err.message);
    }
  }

  async function downloadFullExport() {
    try {
      setExportModalOpen(true);
      setExportStatus('processing');
      setExportProgress(5);
      setExportMessage(t('importExport.modal.exportingZip') || 'Preparing full export...');
      setExportStageHistory([]);
      setActiveExportJobId('');
      setActiveExportStatusUrl('');
      setIsCancellingExport(false);
      cancelExportRef.current = false;
      setOpStatus(t('importExport.op.downloadingAll'));

      const enqueueUrl = buildUrl('/vehicles/export-zip-async', { all: 1 });
      const enqueueResp = await fetch(enqueueUrl, { method: 'POST', headers: auth() });
      if (!enqueueResp.ok) {
        const enqueueError = await getApiErrorMessage(enqueueResp, 'Failed to queue export');
        throw new Error(enqueueError);
      }

      const queued = await parseJsonResponse(enqueueResp, 'Export queue response was not valid JSON');
      setActiveExportJobId(String(queued?.jobId || ''));
      setActiveExportStatusUrl(String(queued?.statusUrl || ''));
      const statusUrl = queued?.statusUrl;
      const initialDownloadUrl = queued?.downloadUrl;
      const pollIntervalMs = Number(queued?.pollIntervalMs) > 0 ? Number(queued.pollIntervalMs) : 2000;

      if (!statusUrl) {
        throw new Error('Export queue response missing status URL');
      }

      setExportStatus('processing');
      setExportProgress(15);
      setExportMessage('Export queued. Waiting for worker...');
      pushStageHistory('Queued', 'Export queued. Waiting for worker...', new Date().toISOString());

      const maxPollAttempts = 300;
      let readyDownloadUrl = initialDownloadUrl;
      const startedAt = Date.now();

      for (let attempt = 0; attempt < maxPollAttempts; attempt += 1) {
        if (cancelExportRef.current) {
          throw new Error('EXPORT_CANCELLED_BY_USER');
        }

        const statusResp = await fetch(statusUrl, { headers: auth() });
        if (!statusResp.ok) {
          const statusError = await getApiErrorMessage(statusResp, 'Failed to check export status');
          throw new Error(statusError);
        }

        const statusData = await parseJsonResponse(statusResp, 'Export status response was not valid JSON');
        const status = String(statusData?.status || 'unknown');
        const progress = Number(statusData?.progress);
        const cappedProgress = Number.isFinite(progress) ? Math.max(10, Math.min(progress, 95)) : Math.min(95, 15 + attempt);
        const elapsedSeconds = Math.floor((Date.now() - startedAt) / 1000);

        let backendAgeSeconds = null;
        if (statusData?.updatedAt) {
          const updatedTime = Date.parse(String(statusData.updatedAt));
          if (!Number.isNaN(updatedTime)) {
            backendAgeSeconds = Math.max(0, Math.floor((Date.now() - updatedTime) / 1000));
          }
        }

        const stageText = getStageLabel(statusData?.stage, status);
        const heartbeatText = backendAgeSeconds === null
          ? `Elapsed ${formatDuration(elapsedSeconds)}`
          : `Elapsed ${formatDuration(elapsedSeconds)} - backend updated ${formatDuration(backendAgeSeconds)} ago`;
        const transitionMessage = statusData?.message || 'Building archive...';
        pushStageHistory(stageText, transitionMessage, statusData?.updatedAt || new Date().toISOString());

        if (status === 'completed') {
          readyDownloadUrl = statusData?.downloadUrl || readyDownloadUrl;
          setExportProgress(92);
          setExportStatus('downloading');
          setExportMessage(`Archive ready after ${formatDuration(elapsedSeconds)}. Downloading...`);
          pushStageHistory('Completed', 'Archive ready. Starting download.', statusData?.updatedAt || new Date().toISOString());
          break;
        }

        if (status === 'failed') {
          const statusError = statusData?.error || statusData?.message || 'Export failed';
          throw new Error(String(statusError));
        }

        if (status === 'cancelled') {
          throw new Error('EXPORT_CANCELLED_BY_USER');
        }

        setExportProgress(cappedProgress);
        setExportMessage(`${stageText}: ${transitionMessage} (${heartbeatText})`);
        await sleep(pollIntervalMs);
      }

      if (!readyDownloadUrl) {
        throw new Error('Export completed without a download URL');
      }

      if (cancelExportRef.current) {
        throw new Error('EXPORT_CANCELLED_BY_USER');
      }

      const blob = await fetchBlobWithRetry(readyDownloadUrl, auth(), 3);
      setExportProgress(98);
      saveBlob(blob, `vehicles_full_export_${new Date().toISOString().split('T')[0]}.zip`);
      pushStageHistory('Downloading', 'Download completed in browser.', new Date().toISOString());

      setExportProgress(100);
      setExportStatus('success');
      setExportMessage(t('importExport.modal.exportSuccessAll') || 'Full export downloaded');
      setActiveExportJobId('');
      setActiveExportStatusUrl('');
      setTimeout(() => setExportModalOpen(false), 1000);
      setOpStatus(t('importExport.op.downloadedAll'));
    } catch (err) {
      if (String(err?.message || '') === 'EXPORT_CANCELLED_BY_USER') {
        setExportProgress(0);
        setExportStatus('error');
        setExportError('Export cancelled by user.');
        setExportMessage('Export cancelled by user.');
        setOpStatus('Export cancelled');
        pushStageHistory('Cancelled', 'Export cancelled by user.', new Date().toISOString());
        setActiveExportJobId('');
        setActiveExportStatusUrl('');
        return;
      }

      setExportProgress(0);
      setExportStatus('error');
      const exportErr = normalizeExportError(err, 'Full export failed');
      setExportError(exportErr);
      setOpStatus(t('importExport.op.error') + ': ' + exportErr);
      setActiveExportJobId('');
      setActiveExportStatusUrl('');
    }
  }

  async function downloadStockExport() {
    try {
      const url = buildUrl('/vehicles/export-stock');
      const resp = await fetch(url, { headers: auth() });
      if (!resp.ok) {
        throw new Error(`Export failed: ${resp.status}`);
      }
      const data = await parseJsonResponse(resp, 'Stock export response was not valid JSON');
      const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
      saveBlob(blob, `stock_export_${new Date().toISOString().split('T')[0]}.json`);
    } catch (err) {
      setOpStatus((t('importExport.op.error') || 'Error') + ': ' + err.message);
    }
  }

  // Import handlers: JSON preview + ZIP auto-upload
  const handleJsonFileSelected = (file) => {
    if (!file) return;
    const reader = new FileReader();
    reader.onload = (ev) => {
      try {
        const parsed = JSON.parse(ev.target.result);
        const vehicles = Array.isArray(parsed) ? parsed : (parsed.vehicles || parsed.data || []);
        const count = Array.isArray(vehicles) ? vehicles.length : 0;
        const sample = (Array.isArray(vehicles) ? vehicles.slice(0, 20) : []).map(v => ({
          name: v.name || v.title || v.registrationNumber || v.registration || t('common.noName'),
          registration: v.registrationNumber || v.registration || '',
          make: v.make || v.manufacturer || '',
          model: v.model || '',
        }));
        setImportPreviewData({ parsed, vehicles, count, sample });
        setImportFileName(file.name);
        setImportPreviewOpen(true);
      } catch (err) {
        setOpStatus(t('common.importFailed'));
      }
    };
    reader.readAsText(file);
  };

  const confirmJsonImport = async (data) => {
    if (demoGuard(t)) return;
    setImportPreviewOpen(false);
    setImportModalOpen(true);
    setImportStatus('processing');
    setImportProgress(30);
    setImportMessage(t('common.importing') || 'Importing...');
    setImportError('');

    try {
      const url = buildUrl('/vehicles/import');
      setImportProgress(50);
      const resp = await fetch(url, { method: 'POST', headers: { ...auth(), 'Content-Type': 'application/json' }, body: JSON.stringify(data) });
      
      if (!resp.ok) {
        const errorMessage = await getApiErrorMessage(resp, 'Import failed');
        throw new Error(errorMessage);
      }
      
      setImportProgress(80);
      const json = await parseJsonResponse(resp, 'Image manifest response was not valid JSON');
      const importedCount = json ? (json.imported ?? json.count ?? json.total ?? 0) : 0;
      const successMsg = t('common.importSuccess', { count: importedCount });
      
      setImportProgress(100);
      setImportStatus('success');
      setImportMessage(successMsg);
      setOpStatus(successMsg);
      
      // dispatch global notification for Layout to display
      try {
        window.dispatchEvent(new CustomEvent('app-notification', { detail: { message: successMsg, severity: 'success' } }));
      } catch (e) {
        // ignore
      }
      
      await refreshVehicles();
      
      // Wait 1 second then redirect to dashboard
      setTimeout(() => {
        setImportModalOpen(false);
        navigate('/');
      }, 1000);
    } catch (err) {
      setImportProgress(0);
      setImportStatus('error');
      setImportError(err.message || t('common.importFailed'));
      setOpStatus(t('common.importFailed'));
    }
  };

  const handleZipFileSelected = async (file) => {
    if (!file) return;
    if (demoGuard(t)) return;
    
    setImportModalOpen(true);
    setImportStatus('uploading');
    setImportProgress(0);
    setImportMessage(t('importExport.op.uploading') || 'Uploading...');
    setImportError('');

    try {
      const url = buildUrl('/vehicles/import-zip');
      const fd = new FormData();
      fd.append('file', file, file.name);
      
      // Simulate upload progress
      setImportProgress(20);
      setImportMessage(t('importExport.op.uploadingZip') || 'Uploading ZIP file...');
      
      const resp = await fetch(url, { method: 'POST', headers: auth(), body: fd });
      
      if (!resp.ok) {
        const errorMessage = await getApiErrorMessage(resp, 'Zip import failed');
        throw new Error(errorMessage);
      }
      
      setImportProgress(60);
      setImportStatus('processing');
      setImportMessage(t('importExport.op.processing') || 'Processing import...');
      
      const json = await parseJsonResponse(resp, 'Import response was not valid JSON');
      
      setImportProgress(100);
      setImportStatus('success');
      const successMsg = t('common.importSuccess', { count: (json && json.imported) || 0 });
      setImportMessage(successMsg);
      setOpStatus(successMsg);
      
      try {
        window.dispatchEvent(new CustomEvent('app-notification', { detail: { message: successMsg, severity: 'success' } }));
      } catch (e) {
        // ignore
      }
      
      await refreshVehicles();
      
      // Wait 1 second then redirect to dashboard
      setTimeout(() => {
        setImportModalOpen(false);
        navigate('/');
      }, 1000);
    } catch (err) {
      setImportProgress(0);
      setImportStatus('error');
      setImportError(err.message || t('common.importFailed'));
      setOpStatus(t('common.importFailed'));
    }
  };

  async function downloadImagesManifest(filters = {}) {
    try {
      const { status = 'all', vehicleIds = [], elements = [], imageTypes = [] } = filters || {};
      setOpStatus(t('importExport.op.downloadingJson'));
      const url = buildUrl('/vehicles/export', {
        images: true,
        status: (status && status !== 'all') ? status : undefined,
        vehicleIds: vehicleIds && vehicleIds.length ? vehicleIds : undefined,
        elements: elements && elements.length ? elements : undefined,
        imageTypes: imageTypes && imageTypes.length ? imageTypes : undefined,
      });
      const resp = await fetch(url, { headers: auth() });
      if (!resp.ok) throw new Error('Export failed');
      const json = await parseJsonResponse(resp, 'ZIP import response was not valid JSON');
      const filename = `vehicles_images_manifest_${new Date().toISOString().split('T')[0]}.json`;
      downloadJsonObject(json, filename.replace(/\.json$/, ''));
      setOpStatus(t('importExport.op.downloadedJson'));
    } catch (err) {
      setOpStatus(t('importExport.op.error') + ': ' + err.message);
    }
  }

  async function downloadImagesZip(filters = {}) {
    try {
      const { status = 'all', vehicleIds = [], elements = [], imageTypes = [] } = filters || {};
      setOpStatus(t('importExport.op.downloadingAll'));
      const url = buildUrl('/vehicles/export-zip', {
        status: (status && status !== 'all') ? status : undefined,
        vehicleIds: vehicleIds && vehicleIds.length ? vehicleIds : undefined,
        elements: elements && elements.length ? elements : undefined,
        imageTypes: imageTypes && imageTypes.length ? imageTypes : undefined,
      });
      const resp = await fetch(url, { headers: auth() });
      if (!resp.ok) throw new Error('Export failed');
      const blob = await resp.blob();
      saveBlob(blob, `vehicles_images_${new Date().toISOString().split('T')[0]}.zip`);
      setOpStatus(t('importExport.op.downloadedAll'));
    } catch (err) {
      setOpStatus(t('importExport.op.error') + ': ' + err.message);
    }
  }

  return (
    <Box>
      <Box display="flex" justifyContent="space-between" alignItems="center" mb={3}>
        <Typography variant="h4">{t('nav.importExport')}</Typography>
      </Box>

      <Paper variant="outlined" sx={{ p: 2, mb: 2 }}>
        <Typography variant="h5" sx={{ mb: 2 }}>{t('importExport.importTitle') || 'Import'}</Typography>
        <Grid container spacing={2} alignItems="center">
          <Grid item xs={12} sm={6}>
            <Box sx={{ display: 'flex', gap: 1, alignItems: 'center' }}>
              <input
                accept="application/json"
                style={{ display: 'none' }}
                id="import-json-file-top"
                type="file"
                onChange={(e) => handleJsonFileSelected(e.target.files && e.target.files[0])}
              />
              <label htmlFor="import-json-file-top">
                <Tooltip title={t('importExport.tooltip.importJson') || 'Upload a JSON export file'}>
                  <span>
                    <Button startIcon={<CloudUpload />} variant="outlined" component="span">{t('importExport.importJson') || 'Import JSON'}</Button>
                  </span>
                </Tooltip>
              </label>

              <input
                accept=".zip,application/zip,application/x-zip-compressed"
                style={{ display: 'none' }}
                id="import-zip-file-top"
                type="file"
                onChange={(e) => handleZipFileSelected(e.target.files && e.target.files[0])}
              />
              <label htmlFor="import-zip-file-top">
                <Tooltip title={t('importExport.tooltip.importZip') || 'Upload a ZIP containing attachments'}>
                  <span>
                    <Button startIcon={<CloudUpload />} variant="outlined" component="span">{t('importExport.importZip') || 'Import ZIP'}</Button>
                  </span>
                </Tooltip>
              </label>
            </Box>
          </Grid>
        </Grid>
      </Paper>
      <ImportPreview open={importPreviewOpen} data={importPreviewData} fileName={importFileName} onClose={() => setImportPreviewOpen(false)} onConfirm={confirmJsonImport} />

      {/* Import Progress Modal */}
      <Dialog
        open={importModalOpen}
        onClose={(event, reason) => {
          // Only allow closing on error status
          if (importStatus === 'error') {
            setImportModalOpen(false);
          }
        }}
        maxWidth="sm"
        fullWidth
        disableEscapeKeyDown={importStatus !== 'error'}
      >
        <DialogTitle>
          <Box display="flex" alignItems="center" gap={1}>
            {importStatus === 'success' && <CheckCircle color="success" />}
            {importStatus === 'error' && <ErrorIcon color="error" />}
            <Typography variant="h6">
              {importStatus === 'uploading' && (t('importExport.modal.uploading') || 'Uploading')}
              {importStatus === 'processing' && (t('importExport.modal.processing') || 'Processing')}
              {importStatus === 'success' && (t('importExport.modal.success') || 'Import Successful')}
              {importStatus === 'error' && (t('importExport.modal.error') || 'Import Failed')}
            </Typography>
          </Box>
        </DialogTitle>
        <DialogContent>
          <Box sx={{ width: '100%', mt: 2 }}>
            {importStatus !== 'error' && (
              <>
                <LinearProgress
                  variant="determinate"
                  value={importProgress}
                  sx={{ mb: 2, height: 8, borderRadius: 4 }}
                />
                <Typography variant="body2" color="text.secondary" align="center">
                  {importMessage}
                </Typography>
              </>
            )}
            {importStatus === 'success' && (
              <Alert severity="success" sx={{ mt: 2 }}>
                {importMessage}
                <br />
                <Typography variant="caption" color="text.secondary" sx={{ mt: 1, display: 'block' }}>
                  {t('importExport.modal.redirecting') || 'Redirecting to dashboard...'}
                </Typography>
              </Alert>
            )}
            {importStatus === 'error' && (
              <>
                <Alert severity="error" sx={{ mt: 2 }}>
                  {importError}
                </Alert>
                <Box sx={{ mt: 3, display: 'flex', justifyContent: 'flex-end' }}>
                  <Button
                    variant="contained"
                    onClick={() => setImportModalOpen(false)}
                  >
                    {t('common.close') || 'Close'}
                  </Button>
                </Box>
              </>
            )}
          </Box>
        </DialogContent>
      </Dialog>

      {/* Export Progress Modal */}
      <Dialog
        open={exportModalOpen}
        onClose={(event, reason) => {
          if (exportStatus === 'error' || exportStatus === 'success') {
            setExportModalOpen(false);
          }
        }}
        maxWidth="sm"
        fullWidth
        disableEscapeKeyDown={exportStatus === 'processing' || exportStatus === 'downloading'}
      >
        <DialogTitle>
          <Box display="flex" alignItems="center" gap={1}>
            {exportStatus === 'success' && <CheckCircle color="success" />}
            {exportStatus === 'error' && <ErrorIcon color="error" />}
            <Typography variant="h6">
              {(exportStatus === 'processing' || exportStatus === 'downloading') && (t('importExport.modal.exporting') || 'Exporting')}
              {exportStatus === 'success' && (t('importExport.modal.exportSuccess') || 'Export Successful')}
              {exportStatus === 'error' && (t('importExport.modal.exportError') || 'Export Failed')}
            </Typography>
          </Box>
        </DialogTitle>
        <DialogContent>
          <Box sx={{ width: '100%', mt: 2 }}>
            {exportStatus !== 'error' && (
              <>
                <LinearProgress
                  variant="determinate"
                  value={exportProgress}
                  sx={{ mb: 2, height: 8, borderRadius: 4 }}
                />
                <Typography variant="body2" color="text.secondary" align="center">
                  {exportMessage}
                </Typography>
                {exportStageHistory.length > 0 && (
                  <Box sx={{ mt: 2, p: 1.5, border: 1, borderColor: 'divider', borderRadius: 1 }}>
                    <Typography variant="caption" color="text.secondary" sx={{ display: 'block', mb: 0.75 }}>
                      {t('importExport.modal.recentStages') || 'Recent stages'}
                    </Typography>
                    <Box component="ul" sx={{ m: 0, pl: 2 }}>
                      {exportStageHistory.map((entry) => (
                        <li key={entry.id}>
                          <Typography variant="caption" color="text.secondary">
                            [{entry.time}] {entry.stage}: {entry.message}
                          </Typography>
                        </li>
                      ))}
                    </Box>
                  </Box>
                )}
                {(exportStatus === 'processing' || exportStatus === 'downloading') && (
                  <Box sx={{ mt: 2, display: 'flex', justifyContent: 'flex-end' }}>
                    <Button
                      color="warning"
                      variant="outlined"
                      onClick={cancelFullExport}
                      disabled={isCancellingExport}
                    >
                      {isCancellingExport ? 'Cancelling...' : (t('common.cancel') || 'Cancel')}
                    </Button>
                  </Box>
                )}
              </>
            )}
            {exportStatus === 'success' && (
              <Alert severity="success" sx={{ mt: 2 }}>
                {exportMessage}
              </Alert>
            )}
            {exportStatus === 'error' && (
              <>
                <Alert severity="error" sx={{ mt: 2 }}>
                  {exportError}
                </Alert>
                <Box sx={{ mt: 3, display: 'flex', justifyContent: 'flex-end' }}>
                  <Button
                    variant="contained"
                    onClick={() => setExportModalOpen(false)}
                  >
                    {t('common.close') || 'Close'}
                  </Button>
                </Box>
              </>
            )}
          </Box>
        </DialogContent>
      </Dialog>

      <Paper variant="outlined" sx={{ p: 2, mb: 2 }}>
          <Box display="flex" justifyContent="space-between" alignItems="center" mb={2}>
          <Typography variant="h5">{t('importExport.exportTitle') || 'Export'}</Typography>
          <Tooltip title={t('importExport.tooltip.fullExport') || 'Download a full export (ZIP)'}>
            <span>
              <Button variant="contained" color="primary" onClick={downloadFullExport} startIcon={<Download />}>
                {t('importExport.fullExport')}
              </Button>
            </span>
          </Tooltip>
        </Box>
        <Grid container spacing={2} alignItems="center">
          {/* JSON row */}
          <Grid item xs={12}>
            <Typography variant="h6" sx={{ mb: 1 }}>{t('importExport.jsonExport')}</Typography>
            <Grid container spacing={2} alignItems="center">
              <Grid item xs={12} sm={2}>
                <Tooltip title={t('importExport.tooltip.statusFilter') || 'Filter vehicles by status'}>
                  <span>
                    <FormControl fullWidth size="small">
                      <InputLabel id="status-filter">{t('importExport.statusFilter')}</InputLabel>
                      <Select
                        labelId="status-filter"
                        value={status}
                        label={t('importExport.statusFilter')}
                        onChange={(e) => setStatus(e.target.value)}
                      >
                        <MenuItem value="all">{t('importExport.statusAll')}</MenuItem>
                        <MenuItem value="Live">{t('vehicle.status.live')}</MenuItem>
                        <MenuItem value="Sold">{t('vehicle.status.sold')}</MenuItem>
                        <MenuItem value="Scrapped">{t('vehicle.status.scrapped')}</MenuItem>
                      </Select>
                    </FormControl>
                  </span>
                </Tooltip>
              </Grid>

              <Grid item xs={12} sm={10}>
                <Tooltip title={t('importExport.tooltip.selectVehicles') || 'Select vehicles to include'}>
                  <span>
                    <Autocomplete
                  multiple
                  options={[{ id: '__all__', name: t('importExport.selectAllVehicles') }, ...vehicles]}
                  getOptionLabel={(opt) => opt.name || ''}
                  value={
                    vehicles.length > 0 && selectedVehicles.length === vehicles.length
                      ? [{ id: '__all__', name: t('importExport.selectAllVehicles') }, ...vehicles]
                      : vehicles.filter(v => selectedVehicles.includes(v.id))
                  }
                  onChange={(e, v) => {
                    const hasAll = v.find(item => item && item.id === '__all__');
                    if (hasAll) {
                      setSelectedVehicles(vehicles.map(x => x.id));
                    } else {
                      setSelectedVehicles(v.map(x => x.id));
                    }
                  }}
                  renderOption={(props, option) => (
                    <li {...props} key={option.id}>{option.name}{option.registrationNumber ? ` (${option.registrationNumber})` : ''}</li>
                  )}
                  renderInput={(params) => <TextField {...params} label={t('importExport.selectVehicles')} size="small" />}
                    />
                  </span>
                </Tooltip>
              </Grid>

              {/* Element selectors row (moved above buttons) */}
              <Grid item xs={12}>
                <Tooltip title={t('importExport.tooltip.selectElements') || 'Select which elements to export'}>
                  <span>
                    <Autocomplete
                  multiple
                  options={optionsWithAllElements}
                  getOptionLabel={(o) => o.label}
                  value={
                    elements.length === sortedElementOptions.length
                      ? optionsWithAllElements
                      : sortedElementOptions.filter(e => elements.includes(e.value))
                  }
                  onChange={(e, v) => {
                    const hasAll = v.find(item => item && item.value === '__all__');
                    if (hasAll) {
                      setElements(sortedElementOptions.map(o => o.value));
                    } else {
                      setElements(v.map(i => i.value));
                    }
                  }}
                  renderInput={(params) => <TextField {...params} label={t('importExport.selectElements')} size="small" />}
                    />
                  </span>
                </Tooltip>
              </Grid>

              {/* Buttons moved to bottom row for consistent alignment */}
              <Grid item xs={12}>
                <Box sx={{ display: 'flex', gap: 1, mt: 1, justifyContent: 'flex-end' }}>
                  <Button
                    startIcon={<Download />}
                    variant="contained"
                    size="medium"
                    sx={{ minWidth: 160 }}
                    onClick={() => exportJson('select', {
                      status: status || 'all',
                      vehicleIds: selectedVehicles,
                      elements,
                      images: imageTypes && imageTypes.length ? true : undefined,
                      receipts: imageTypes && imageTypes.some(i => /receipt/i.test(i) || i === 'invoices') ? true : undefined,
                      imageTypes,
                    })}
                  >
                    {t('importExport.downloadJson')}
                  </Button>
                  <Button startIcon={<Download />} variant="outlined" size="medium" sx={{ minWidth: 160 }} onClick={() => exportJson('all')}>{t('importExport.downloadAll')}</Button>
                </Box>
              </Grid>
            </Grid>
          </Grid>

          {/* Images / ZIP row */}
          <Grid item xs={12} sx={{ mt: 2 }}>
            <Typography variant="h6" sx={{ mb: 1 }}>{t('importExport.imagesExport')}</Typography>
            <Grid container spacing={2} alignItems="center">
              <Grid item xs={12} sm={2}>
                <Tooltip title={t('importExport.tooltip.statusFilter') || 'Filter vehicles by status'}>
                  <span>
                    <FormControl fullWidth size="small">
                      <InputLabel id="status-filter-2">{t('importExport.statusFilter')}</InputLabel>
                      <Select
                        labelId="status-filter-2"
                        value={status}
                        label={t('importExport.statusFilter')}
                        onChange={(e) => setStatus(e.target.value)}
                      >
                        <MenuItem value="all">{t('importExport.statusAll')}</MenuItem>
                        <MenuItem value="Live">{t('vehicle.status.live')}</MenuItem>
                        <MenuItem value="Sold">{t('vehicle.status.sold')}</MenuItem>
                        <MenuItem value="Scrapped">{t('vehicle.status.scrapped')}</MenuItem>
                      </Select>
                    </FormControl>
                  </span>
                </Tooltip>
              </Grid>

              <Grid item xs={12} sm={10}>
                <Tooltip title={t('importExport.tooltip.selectVehicles') || 'Select vehicles to include'}>
                  <span>
                    <Autocomplete
                  multiple
                  options={[{ id: '__all__', name: t('importExport.selectAllVehicles') }, ...vehicles]}
                  getOptionLabel={(opt) => opt.name || ''}
                  value={
                    vehicles.length > 0 && selectedVehicles.length === vehicles.length
                      ? [{ id: '__all__', name: t('importExport.selectAllVehicles') }, ...vehicles]
                      : vehicles.filter(v => selectedVehicles.includes(v.id))
                  }
                  onChange={(e, v) => {
                    const hasAll = v.find(item => item && item.id === '__all__');
                    if (hasAll) {
                      setSelectedVehicles(vehicles.map(x => x.id));
                    } else {
                      setSelectedVehicles(v.map(x => x.id));
                    }
                  }}
                  renderOption={(props, option) => (
                    <li {...props} key={option.id}>{option.name}{option.registrationNumber ? ` (${option.registrationNumber})` : ''}</li>
                  )}
                  renderInput={(params) => <TextField {...params} label={t('importExport.selectVehicles')} size="small" />}
                    />
                  </span>
                </Tooltip>
              </Grid>

              <Grid item xs={12}>
                <Tooltip title={t('importExport.tooltip.selectImages') || 'Select image types to include'}>
                  <span>
                    <Autocomplete
                  multiple
                  options={imageOptions}
                  getOptionLabel={(o) => o.label}
                  value={imageOptions.filter(i => imageTypes.includes(i.value))}
                  onChange={(e, v) => setImageTypes(v.map(i => i.value))}
                  renderInput={(params) => <TextField {...params} label={t('importExport.selectImages')} size="small" />}
                  sx={{ mt: 1 }}
                />
                  </span>
                </Tooltip>
              </Grid>

              {/* Buttons moved to bottom row for consistent alignment (below image types) */}
              <Grid item xs={12}>
                <Box sx={{ display: 'flex', gap: 1, mt: 1, justifyContent: 'flex-end' }}>
                  <Tooltip title={t('importExport.tooltip.downloadImagesManifest') || 'Download a manifest describing selected images'}>
                    <span>
                      <Button startIcon={<Download />} variant="outlined" onClick={() => downloadImagesManifest({ status: status || 'all', vehicleIds: selectedVehicles, elements, imageTypes })}>{t('importExport.downloadImagesManifest')}</Button>
                    </span>
                  </Tooltip>
                  <Tooltip title={t('importExport.tooltip.downloadZip') || 'Download selected images and attachments as a ZIP'}>
                    <span>
                      <Button startIcon={<Download />} variant="contained" onClick={() => downloadImagesZip({ status: status || 'all', vehicleIds: selectedVehicles, elements, imageTypes })}>{t('importExport.downloadZip')}</Button>
                    </span>
                  </Tooltip>
                </Box>
              </Grid>
            </Grid>
          </Grid>
        </Grid>
      </Paper>

      <Paper variant="outlined" sx={{ p: 2, mb: 2 }}>
        <Typography variant="h5" sx={{ mb: 2 }}>
          {t('importExport.stockTitle')}
        </Typography>
        <Typography variant="body2" color="text.secondary" sx={{ mb: 2 }}>
          {t('importExport.stockDescription')}
        </Typography>
        <Box display="flex" justifyContent="flex-end">
          <Button variant="outlined" startIcon={<Download />} onClick={downloadStockExport}>
            {t('importExport.downloadStock')}
          </Button>
        </Box>
      </Paper>

    </Box>
  );
};

export default ImportExport;
