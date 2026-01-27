import React, { useState, useEffect } from 'react';
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
} from '@mui/material';
import { Download, CloudUpload } from '@mui/icons-material';
import { useTranslation } from 'react-i18next';
import ImportPreview from '../components/ImportPreview';
import { buildUrl as helpersBuildUrl, authHeaders } from '../components/ImportHelpers';
import { saveBlob, downloadJsonObject } from '../components/DownloadHelpers';

const ImportExport = () => {
  const { t } = useTranslation();
  const [status, setStatus] = useState('all');
  const [selectedVehicles, setSelectedVehicles] = useState([]);
  const [elements, setElements] = useState([]);
  const [imageTypes, setImageTypes] = useState([]);
  const [vehicles, setVehicles] = useState([]);
  const [opStatus, setOpStatus] = useState('');
  const [importPreviewOpen, setImportPreviewOpen] = useState(false);
  const [importPreviewData, setImportPreviewData] = useState(null);
  const [importFileName, setImportFileName] = useState('');

  const apiBase = process.env.REACT_APP_API_URL || '';
  const auth = authHeaders;

  // Helper: build full URL (apiBase + path) with provided params object.
  const buildUrl = (path, params) => helpersBuildUrl(apiBase, path, params);

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

  async function exportJson(mode = 'select', filters = {}) {
    try {
      const { status = 'all', vehicleIds = [], elements = [], images = false, receipts = false, imageTypes = [] } = filters || {};
      const isAll = mode === 'all';
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
      const json = await resp.json();
      const filename = isAll
        ? `vehicles_export_all_${new Date().toISOString().split('T')[0]}.json`
        : `vehicles_export_${new Date().toISOString().split('T')[0]}.json`;
      downloadJsonObject(json, filename.replace(/\.json$/, ''));
      setOpStatus(isAll ? t('importExport.op.downloadedAll') : t('importExport.op.downloadedJson'));
    } catch (err) {
      setOpStatus(t('importExport.op.error') + ': ' + err.message);
    }
  }

  async function downloadFullExport() {
    try {
      setOpStatus(t('importExport.op.downloadingAll'));
      const url = buildUrl('/vehicles/export-zip', { all: 1 });
      const resp = await fetch(url, { headers: auth() });
      if (resp.ok) {
        const blob = await resp.blob();
        saveBlob(blob, `vehicles_full_export_${new Date().toISOString().split('T')[0]}.zip`);
        setOpStatus(t('importExport.op.downloadedAll'));
        return;
      }
      // fallback to JSON export if ZIP endpoint not available
      await exportJson('all');
    } catch (err) {
      setOpStatus(t('importExport.op.error') + ': ' + err.message);
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
    try {
      setOpStatus(t('common.importing') || 'Importing...');
      const url = buildUrl('/vehicles/import');
      const resp = await fetch(url, { method: 'POST', headers: { ...auth(), 'Content-Type': 'application/json' }, body: JSON.stringify(data) });
      if (!resp.ok) throw new Error('Import failed');
      const json = await resp.json();
      const importedCount = json ? (json.imported ?? json.count ?? json.total ?? 0) : 0;
      const successMsg = t('common.importSuccess', { count: importedCount });
      setOpStatus(successMsg);
      // dispatch global notification for Layout to display
      try {
        window.dispatchEvent(new CustomEvent('app-notification', { detail: { message: successMsg, severity: 'success' } }));
      } catch (e) {
        // ignore
      }
      setImportPreviewOpen(false);
    } catch (err) {
      setOpStatus(t('common.importFailed'));
    }
  };

  const handleZipFileSelected = async (file) => {
    if (!file) return;
    try {
      setOpStatus(t('importExport.op.downloadingAll'));
      const url = buildUrl('/vehicles/import-zip');
      const fd = new FormData();
      fd.append('file', file, file.name);
      const resp = await fetch(url, { method: 'POST', headers: auth(), body: fd });
      if (!resp.ok) throw new Error('Zip import failed');
      const json = await resp.json();
      const successMsg2 = t('common.importSuccess', { count: (json && json.imported) || 0 });
      setOpStatus(successMsg2);
      try {
        window.dispatchEvent(new CustomEvent('app-notification', { detail: { message: successMsg2, severity: 'success' } }));
      } catch (e) {
        // ignore
      }
    } catch (err) {
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
      const json = await resp.json();
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

 

    </Box>
  );
};

export default ImportExport;
