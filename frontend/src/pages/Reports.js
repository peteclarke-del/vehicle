import React, { useEffect, useState, useMemo, useCallback } from 'react';
import logger from '../utils/logger';
import SafeStorage from '../utils/SafeStorage';
import {
  Box,
  Typography,
  Button,
  FormControl,
  InputLabel,
  Select,
  MenuItem,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableRow,
  Paper,
  IconButton,
  Tooltip,
  TableSortLabel,
  Dialog,
  DialogTitle,
  DialogContent,
  DialogActions,
} from '@mui/material';
import { Download as DownloadIcon, Delete as DeleteIcon } from '@mui/icons-material';
import logger from '../utils/logger';
import { useAuth } from '../contexts/AuthContext';
import { useTranslation } from 'react-i18next';
import VehicleSelector from '../components/VehicleSelector';
import { saveBlob } from '../components/DownloadHelpers';
import logger from '../utils/logger';
import KnightRiderLoader from '../components/KnightRiderLoader';
import logger from '../utils/logger';
import useTablePagination from '../hooks/useTablePagination';
import logger from '../utils/logger';
import TablePaginationBar from '../components/TablePaginationBar';
import logger from '../utils/logger';

const Reports = () => {
  const { api } = useAuth();
  const { t } = useTranslation();

  const [vehicles, setVehicles] = useState([]);
  const [selectedVehicle, setSelectedVehicle] = useState('__all__');
  const [reports, setReports] = useState([]);
  const [templates, setTemplates] = useState([]);
  const [selectedTemplateKey, setSelectedTemplateKey] = useState(null);
  const [selectedPeriodIndex, setSelectedPeriodIndex] = useState(null);
  const [loading, setLoading] = useState(true);
  const [generating, setGenerating] = useState(false);
  const [orderBy, setOrderBy] = useState(() => SafeStorage.get('reportsSortBy', 'generatedAt'));
  const [order, setOrder] = useState(() => SafeStorage.get('reportsSortOrder', 'desc'));

  const [viewerOpen, setViewerOpen] = useState(false);
  const [viewerUrl, setViewerUrl] = useState(null);
  const [viewerMime, setViewerMime] = useState(null);
  const [viewerFileName, setViewerFileName] = useState(null);
  const [viewerBlob, setViewerBlob] = useState(null);
  const [viewerPreview, setViewerPreview] = useState(null);

  const fetchVehicles = useCallback(async () => {
    try {
      const resp = await api.get('/vehicles');
      setVehicles(resp.data || []);
      if ((resp.data || []).length > 0) setSelectedVehicle('__all__');
    } catch (err) {
      logger.error('Failed to load vehicles', err);
    }
  }, [api]);

  const loadReports = useCallback(async () => {
    setLoading(true);
    try {
      const url = selectedVehicle && selectedVehicle !== '__all__' ? `/reports?vehicleId=${selectedVehicle}` : '/reports';
      const resp = await api.get(url);
      setReports(resp.data || []);
    } catch (err) {
      logger.error('Failed to load reports', err);
      setReports([]);
    } finally {
      setLoading(false);
    }
  }, [api, selectedVehicle]);

  useEffect(() => {
    fetchVehicles();
  }, [fetchVehicles]);

  useEffect(() => {
    // Use build-time discovery via webpack's require.context (templates in frontend/src/reports)
    const loadRemoteTemplates = async () => {
      try {
        let tpls = [];
        // try webpack require.context (build-time discovery)
        try {
          // eslint-disable-next-line global-require
          const req = require.context('../reports', false, /\.json$/);
          const keys = req.keys();
          tpls = keys.map((k) => {
            try {
              const obj = req(k);
              return { ...(obj || {}), filename: k.replace('./', '') };
            } catch (e) {
              logger.warn('Failed to require template', k, e);
              return null;
            }
          }).filter(Boolean);
        } catch (e) {
          // require.context not available (not built with webpack), fall back to backend API
          logger.debug('require.context not available; falling back to backend', e);
        }

        if (tpls.length === 0) {
          // fallback to backend API which requires JWT
          const apiResp = await api.get('/reports/templates');
          const backendTemplates = apiResp.data;
          if (Array.isArray(backendTemplates)) {
            tpls = backendTemplates.map((m) => ({ ...(m || {}) }));
          }
        }

        tpls.sort((a, b) => ((a.name || '') + '').localeCompare((b.name || '') + ''));
        setTemplates(tpls);
        if (tpls.length > 0) {
          const first = tpls[0];
          setSelectedTemplateKey(first.key || first.filename);
          setSelectedPeriodIndex(first.defaultPeriodIndex ?? 0);
        }
      } catch (e) {
        logger.warn('Report template discovery failed', e);
        setTemplates([]);
      }
    };
    loadRemoteTemplates();
  }, []);

  useEffect(() => {
    loadReports();
  }, [selectedVehicle, loadReports]);

  const handleRequestSort = (property) => {
    const isAsc = orderBy === property && order === 'asc';
    const newOrder = isAsc ? 'desc' : 'asc';
    setOrder(newOrder);
    setOrderBy(property);
    SafeStorage.set('reportsSortBy', property);
    SafeStorage.set('reportsSortOrder', newOrder);
  };

  const sortedReports = useMemo(() => {
    const comparator = (a, b) => {
      let aValue = a[orderBy];
      let bValue = b[orderBy];
      if (orderBy === 'generatedAt') {
        const aTime = aValue ? new Date(aValue).getTime() : 0;
        const bTime = bValue ? new Date(bValue).getTime() : 0;
        if (aTime === bTime) return 0;
        return order === 'asc' ? (aTime - bTime) : (bTime - aTime);
      }
      aValue = aValue || '';
      bValue = bValue || '';
      if (aValue === bValue) return 0;
      return order === 'asc' ? (aValue > bValue ? 1 : -1) : (aValue < bValue ? 1 : -1);
    };
    return [...(reports || [])].sort(comparator);
  }, [reports, order, orderBy]);

  const { page: reportsPage, rowsPerPage: reportsRowsPerPage, paginatedRows: paginatedReports, handleChangePage: handleReportsPageChange, handleChangeRowsPerPage: handleReportsRowsPerPageChange } = useTablePagination(sortedReports);
  const previewRows = useMemo(() => viewerPreview?.rows || [], [viewerPreview]);
  const { page: previewPage, rowsPerPage: previewRowsPerPage, paginatedRows: paginatedPreviewRows, handleChangePage: handlePreviewPageChange, handleChangeRowsPerPage: handlePreviewRowsPerPageChange } = useTablePagination(previewRows);

  // viewer: fetch blob and show
  const handleView = async (report) => {
    try {
      const resp = await api.get(`/reports/${report.id}/download`, { responseType: 'blob' });
      const blob = resp.data;
      const url = window.URL.createObjectURL(blob);
      setViewerBlob(blob);
      setViewerUrl(url);
      setViewerMime(blob.type || 'application/octet-stream');
      setViewerFileName(`${report.name || report.type || 'report'}_${new Date(report.generatedAt || Date.now()).toISOString().split('T')[0]}`);
      setViewerPreview(null);

      // Attempt quick previews for CSV and XLSX
      try {
        const mime = blob.type || '';
        if (mime.includes('csv') || mime === 'text/plain') {
          const text = await blob.text();
          const lines = text.split(/\r?\n/).filter(Boolean);
          const parsed = lines.map((ln) => {
            // naive CSV split (handles simple quoted values)
            const cols = ln.match(/(?:"([^"]*)")|([^,]+)/g) || [];
            return cols.map(c => (c || '').replace(/^"|"$/g, ''));
          });
          if (parsed.length > 0) {
            const columns = parsed[0] || [];
            const rows = parsed.slice(1);
            setViewerPreview({ type: 'csv', columns, rows });
          }
        } else if (mime.includes('sheet') || mime.includes('excel') || mime.includes('spreadsheet') || mime === 'application/vnd.ms-excel' || mime.includes('vnd.openxmlformats')) {
          // try to dynamically import SheetJS if available
          try {
            const XLSX = await import('xlsx');
            const ab = await blob.arrayBuffer();
            const wb = XLSX.read(ab, { type: 'array' });
            const sheetName = wb.SheetNames && wb.SheetNames[0];
            if (sheetName) {
              const sheet = wb.Sheets[sheetName];
              const json = XLSX.utils.sheet_to_json(sheet, { header: 1 });
              const columns = json[0] || [];
              const rows = json.slice(1);
              setViewerPreview({ type: 'xlsx', columns, rows });
            }
          } catch (e) {
            logger.warn('XLSX preview not available (xlsx lib missing)', e);
          }
        }
      } catch (e) {
        logger.warn('Preview parse failed', e);
      }

      setViewerOpen(true);
    } catch (err) {
      logger.error('Failed to fetch report for viewing', err);
      alert(t('common.downloadFailed') || 'Failed to fetch report');
    }
  };

  const closeViewer = () => {
    if (viewerUrl) {
      window.URL.revokeObjectURL(viewerUrl);
    }
    setViewerUrl(null);
    setViewerMime(null);
    setViewerFileName(null);
    setViewerBlob(null);
    setViewerPreview(null);
    setViewerOpen(false);
  };

  const handleGenerate = async () => {
    setGenerating(true);
    try {
      const tpl = templates.find((t) => (t.key || t.filename) === selectedTemplateKey);
      const period = tpl && tpl.periods && tpl.periods[selectedPeriodIndex] ? tpl.periods[selectedPeriodIndex] : null;
      const payload = {
        template: tpl?.key ?? tpl?.filename,
        vehicleId: selectedVehicle === '__all__' ? null : selectedVehicle,
        period: period,
        templateContent: tpl,
      };
      await api.post('/reports', payload);
      loadReports();
    } catch (err) {
      logger.error('Failed to generate report', err);
    } finally {
      setGenerating(false);
    }
  };

  const handleDownload = async (report) => {
    try {
      const resp = await api.get(`/reports/${report.id}/download`, { responseType: 'blob' });
      saveBlob(resp.data, `${report.name || 'report'}_${new Date(report.generatedAt || Date.now()).toISOString().split('T')[0]}.pdf`);
    } catch (err) {
      logger.error('Failed to download report', err);
      alert(t('common.downloadFailed') || 'Failed to download file');
    }
  };

  const handleDelete = async (report) => {
    if (!window.confirm(t('common.confirmDelete'))) return;
    try {
      await api.delete(`/reports/${report.id}`);
      loadReports();
    } catch (err) {
      logger.error('Failed to delete report', err);
    }
  };

  return (
    <Box>
      <Box display="flex" justifyContent="space-between" alignItems="center" mb={3}>
        <Typography variant="h4">{t('reports.title') || 'Reports'}</Typography>
        <Box display="flex" gap={2} alignItems="center">
          <FormControl size="small" sx={{ minWidth: 220 }}>
            <InputLabel id="report-template-label">{t('reports.selectTemplate') || 'Report'}</InputLabel>
            <Select
              labelId="report-template-label"
              value={selectedTemplateKey || ''}
              label={t('reports.selectTemplate') || 'Report'}
              onChange={(e) => {
                setSelectedTemplateKey(e.target.value);
                const tpl = templates.find((t) => (t.key || t.filename) === e.target.value);
                setSelectedPeriodIndex(tpl?.defaultPeriodIndex ?? 0);
              }}
            >
              {templates.map((t) => (
                <MenuItem key={t.key ?? t.filename} value={t.key ?? t.filename}>{t.name || t.filename}</MenuItem>
              ))}
            </Select>
          </FormControl>

          {/* Period selector (driven by template) */}
          <FormControl size="small" sx={{ minWidth: 180 }}>
            <InputLabel id="report-period-label">{t('reports.period') || 'Period'}</InputLabel>
            <Select
              labelId="report-period-label"
              value={selectedPeriodIndex ?? ''}
              label={t('reports.period') || 'Period'}
              onChange={(e) => setSelectedPeriodIndex(Number(e.target.value))}
            >
              {(templates.find((t) => (t.key || t.filename) === selectedTemplateKey)?.periods || []).map((p, idx) => (
                <MenuItem key={idx} value={idx}>{p.label}</MenuItem>
              ))}
            </Select>
          </FormControl>

          <VehicleSelector vehicles={vehicles} value={selectedVehicle} onChange={setSelectedVehicle} includeViewAll={true} />
          <Button variant="contained" onClick={handleGenerate} disabled={generating || !selectedTemplateKey} startIcon={generating ? <KnightRiderLoader size={12} /> : null}>
            {t('reports.generate') || 'Generate Report'}
          </Button>
        </Box>
      </Box>

      <TablePaginationBar
        count={sortedReports.length}
        page={reportsPage}
        rowsPerPage={reportsRowsPerPage}
        onPageChange={handleReportsPageChange}
        onRowsPerPageChange={handleReportsRowsPerPageChange}
      />
      <TableContainer component={Paper} sx={{ maxHeight: 'calc(100vh - 220px)', overflow: 'auto' }}>
        <Table stickyHeader>
          <TableHead>
            <TableRow>
              <TableCell>
                <TableSortLabel
                  active={orderBy === 'name'}
                  direction={orderBy === 'name' ? order : 'asc'}
                  onClick={() => handleRequestSort('name')}
                >
                  {t('reports.name') || 'Name'}
                </TableSortLabel>
              </TableCell>
              <TableCell>
                <TableSortLabel
                  active={orderBy === 'type'}
                  direction={orderBy === 'type' ? order : 'asc'}
                  onClick={() => handleRequestSort('type')}
                >
                  {t('reports.type') || 'Type'}
                </TableSortLabel>
              </TableCell>
              <TableCell>{t('reports.period') || 'Period'}</TableCell>
              <TableCell>
                <TableSortLabel
                  active={orderBy === 'generatedAt'}
                  direction={orderBy === 'generatedAt' ? order : 'desc'}
                  onClick={() => handleRequestSort('generatedAt')}
                >
                  {t('reports.generatedAt') || 'Generated'}
                </TableSortLabel>
              </TableCell>
              <TableCell align="center">{t('common.actions')}</TableCell>
            </TableRow>
          </TableHead>
          <TableBody>
            {loading ? (
              <TableRow>
                <TableCell colSpan={5} align="center"><KnightRiderLoader size={24} /></TableCell>
              </TableRow>
            ) : sortedReports.length === 0 ? (
              <TableRow>
                <TableCell colSpan={5} align="center">{t('common.noRecords')}</TableCell>
              </TableRow>
            ) : (
              paginatedReports.map((r) => (
                <TableRow key={r.id} sx={{ '&:nth-of-type(odd)': { backgroundColor: 'action.hover' } }}>
                  <TableCell>
                    <Button variant="text" onClick={() => handleView(r)}>{r.name || r.type}</Button>
                  </TableCell>
                  <TableCell>{r.type || '-'}</TableCell>
                  <TableCell>{r.periodMonths ? `${r.periodMonths} months` : '-'}</TableCell>
                  <TableCell>{r.generatedAt ? new Date(r.generatedAt).toLocaleString() : '-'}</TableCell>
                  <TableCell align="center">
                    <Tooltip title={t('common.download') || 'Download'}>
                      <IconButton size="small" onClick={() => handleDownload(r)}><DownloadIcon /></IconButton>
                    </Tooltip>
                    <Tooltip title={t('common.delete') || 'Delete'}>
                      <IconButton size="small" onClick={() => handleDelete(r)}><DeleteIcon /></IconButton>
                    </Tooltip>
                  </TableCell>
                </TableRow>
              ))
            )}
          </TableBody>
        </Table>
      </TableContainer>
      <TablePaginationBar
        count={sortedReports.length}
        page={reportsPage}
        rowsPerPage={reportsRowsPerPage}
        onPageChange={handleReportsPageChange}
        onRowsPerPageChange={handleReportsRowsPerPageChange}
      />

      <Dialog open={viewerOpen} onClose={closeViewer} maxWidth="lg" fullWidth>
        <DialogTitle>{viewerFileName || t('reports.name')}</DialogTitle>
        <DialogContent sx={{ minHeight: 400 }}>
          {viewerUrl && viewerMime && viewerMime.indexOf('pdf') !== -1 ? (
            <iframe title="report-preview" src={viewerUrl} style={{ width: '100%', height: '80vh', border: 'none' }} />
          ) : viewerPreview ? (
            <Box>
              <TablePaginationBar
                count={previewRows.length}
                page={previewPage}
                rowsPerPage={previewRowsPerPage}
                onPageChange={handlePreviewPageChange}
                onRowsPerPageChange={handlePreviewRowsPerPageChange}
              />
              <TableContainer component={Paper} sx={{ maxHeight: '60vh' }}>
                <Table size="small">
                  <TableHead>
                    <TableRow>
                      {(viewerPreview.columns || []).map((c, i) => (
                        <TableCell key={i}>{c}</TableCell>
                      ))}
                    </TableRow>
                  </TableHead>
                  <TableBody>
                    {paginatedPreviewRows.map((r, ri) => (
                      <TableRow key={ri} sx={{ '&:nth-of-type(odd)': { backgroundColor: 'action.hover' } }}>
                        {(r || []).map((cell, ci) => <TableCell key={ci}>{String(cell ?? '')}</TableCell>)}
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </TableContainer>
              <TablePaginationBar
                count={previewRows.length}
                page={previewPage}
                rowsPerPage={previewRowsPerPage}
                onPageChange={handlePreviewPageChange}
                onRowsPerPageChange={handlePreviewRowsPerPageChange}
              />
            </Box>
          ) : viewerUrl ? (
            <Box>
              <Typography>{t('reports.previewUnavailable') || 'Preview not available for this file type.'}</Typography>
              <Button
                variant="contained"
                sx={{ mt: 2 }}
                onClick={() => {
                  const ext = (() => {
                    const m = viewerMime || '';
                    if (m.includes('pdf')) return '.pdf';
                    if (m.includes('csv') || m === 'text/plain') return '.csv';
                    if (m.includes('openxmlformats') || m.includes('sheet') || m.includes('excel')) return '.xlsx';
                    return '';
                  })();
                  if (viewerBlob) {
                    saveBlob(viewerBlob, `${viewerFileName || 'report'}${ext}`);
                  } else if (viewerUrl) {
                    // fallback
                    saveBlob(new Blob([viewerUrl]), `${viewerFileName || 'report'}${ext}`);
                  }
                }}
              >
                {t('common.download') || 'Download'}
              </Button>
            </Box>
          ) : (
            <Typography>{t('common.loading') || 'Loading...'}</Typography>
          )}
        </DialogContent>
        <DialogActions>
          <Button onClick={closeViewer}>{t('common.close') || 'Close'}</Button>
        </DialogActions>
      </Dialog>
    </Box>
  );
};

export default Reports;
