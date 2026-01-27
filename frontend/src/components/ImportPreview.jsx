import React, { useMemo } from 'react';
import { Dialog, DialogTitle, DialogContent, DialogActions, Button, Typography, Table, TableHead, TableRow, TableCell, TableBody, TableContainer, Paper, Tooltip } from '@mui/material';
import { useTranslation } from 'react-i18next';

const isArrayOfObjects = (d) => Array.isArray(d) && d.length > 0 && d.every(i => i && typeof i === 'object' && !Array.isArray(i));

const ImportPreview = ({ open, data, fileName, onConfirm, onClose }) => {
  const { t } = useTranslation();
  // support legacy preview shape: { parsed, vehicles, count, sample }
  const vehiclesPreview = (data && data.vehicles) ? data.vehicles : (Array.isArray(data) ? data : null);
  const validTable = useMemo(() => isArrayOfObjects(vehiclesPreview), [vehiclesPreview]);

  const isLegacy = !!(data && data.vehicles);

  const columns = useMemo(() => {
    if (!validTable) return [];
    if (isLegacy) return ['Name', 'Make', 'Model', 'Year', 'Registration'];
    const cols = new Set();
    vehiclesPreview.slice(0, 20).forEach(row => Object.keys(row).forEach(k => cols.add(k)));
    return Array.from(cols);
  }, [vehiclesPreview, validTable, isLegacy]);

  return (
    <Dialog open={!!open} onClose={onClose} maxWidth="lg" fullWidth>
      <DialogTitle>{t('importExport.previewTitle')}{fileName ? ` — ${fileName}` : ''}</DialogTitle>
      <DialogContent dividers>
        {validTable ? (
          <>
            <TableContainer component={Paper} sx={{ maxHeight: 360, overflow: 'auto' }}>
              <Table stickyHeader size="small">
                <TableHead>
                  <TableRow>
                    {columns.map(col => (
                      <TableCell key={col} sx={{ py: 0.5, px: 1, fontSize: '0.85rem' }}>
                        <Tooltip title={col}>
                          <span><strong>{col}</strong></span>
                        </Tooltip>
                      </TableCell>
                    ))}
                  </TableRow>
                </TableHead>
                <TableBody>
                  {vehiclesPreview.slice(0, 20).map((row, idx) => (
                    <TableRow key={idx} hover>
                      {isLegacy ? (
                        <>
                          <TableCell sx={{ py: 0.5, px: 1, fontSize: '0.85rem' }}>{row.name || row.title || row.registrationNumber || row.registration || t('common.noName') }</TableCell>
                          <TableCell sx={{ py: 0.5, px: 1, fontSize: '0.85rem' }}>{row.make || row.manufacturer || ''}</TableCell>
                          <TableCell sx={{ py: 0.5, px: 1, fontSize: '0.85rem' }}>{row.model || ''}</TableCell>
                          <TableCell sx={{ py: 0.5, px: 1, fontSize: '0.85rem' }}>{row.year || row.manufactureYear || row.registrationYear || row.yearOfManufacture || ''}</TableCell>
                          <TableCell sx={{ py: 0.5, px: 1, fontSize: '0.85rem' }}>{row.registrationNumber || row.registration || ''}</TableCell>
                        </>
                      ) : (
                        columns.map(col => (
                          <TableCell key={col} sx={{ py: 0.5, px: 1, fontSize: '0.85rem' }}>{row[col] === undefined ? '' : (typeof row[col] === 'object' ? JSON.stringify(row[col]) : String(row[col]))}</TableCell>
                        ))
                      )}
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </TableContainer>
            <Typography variant="caption" sx={{ display: 'block', mt: 1 }}>
              {t('importExport.showingRows', { shown: Math.min(20, vehiclesPreview.length), total: vehiclesPreview.length })}
            </Typography>
          </>
        ) : (
          <Typography component="pre" sx={{ whiteSpace: 'pre-wrap', wordBreak: 'break-word', fontFamily: 'monospace', fontSize: 12 }}>
            {data ? JSON.stringify(data, null, 2) : t('importExport.noPreview')}
          </Typography>
        )}
      </DialogContent>
      <DialogActions>
        <Tooltip title={t('importExport.tooltip.previewClose') || 'Close preview without importing'}>
          <span>
            <Button onClick={onClose}>{t('common.cancel') || 'Cancel'}</Button>
          </span>
        </Tooltip>
        <Tooltip title={t('importExport.tooltip.previewConfirm') || 'Confirm and upload the displayed import data'}>
          <span>
            <Button variant="contained" onClick={() => onConfirm(data)} disabled={!data}>{t('importExport.import') || 'Import'}</Button>
          </span>
        </Tooltip>
      </DialogActions>
    </Dialog>
  );
};

export default ImportPreview;
