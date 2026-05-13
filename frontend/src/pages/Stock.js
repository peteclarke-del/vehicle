import React, { useCallback, useEffect, useMemo, useState } from 'react';
import {
  Box,
  Paper,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableRow,
  Typography,
  TextField,
  Button,
  Stack,
  TableSortLabel,
} from '@mui/material';
import { Add } from '@mui/icons-material';
import { useTranslation } from 'react-i18next';
import { useAuth } from '../contexts/AuthContext';
import { fetchArrayData } from '../hooks/useApiData';
import useTablePagination from '../hooks/useTablePagination';
import TablePaginationBar from '../components/TablePaginationBar';
import StockDialog from '../components/StockDialog';
import KnightRiderLoader from '../components/KnightRiderLoader';
import ViewAttachmentIconButton from '../components/ViewAttachmentIconButton';
import SafeStorage from '../utils/SafeStorage';

const Stock = () => {
  const { t } = useTranslation();
  const { api } = useAuth();
  const [items, setItems] = useState([]);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [dialogOpen, setDialogOpen] = useState(false);
  const [selectedItem, setSelectedItem] = useState(null);
  const [order, setOrder] = useState('desc');
  const [orderBy, setOrderBy] = useState('updatedAt');
  const [searchTerm, setSearchTerm] = useState('');
  const [vehicleTypeId, setVehicleTypeId] = useState(() => {
    const storedVehicleTypeId = SafeStorage.get('stock.vehicleTypeId', null);
    if (storedVehicleTypeId === null || storedVehicleTypeId === '') return null;
    const parsedVehicleTypeId = parseInt(storedVehicleTypeId, 10);
    return Number.isNaN(parsedVehicleTypeId) ? null : parsedVehicleTypeId;
  });
  const [vehicleTypes, setVehicleTypes] = useState([]);

  const loadStock = useCallback(async () => {
    const data = await fetchArrayData(api, '/stock-items');
    setItems(Array.isArray(data) ? data : []);
    setLoading(false);
  }, [api]);

  useEffect(() => {
    loadStock();
  }, [loadStock]);

  useEffect(() => {
    const loadVehicleTypes = async () => {
      try {
        const resp = await api.get('/vehicle-types');
        setVehicleTypes(Array.isArray(resp.data) ? resp.data : []);
      } catch {
        setVehicleTypes([]);
      }
    };

    loadVehicleTypes();
  }, [api]);

  useEffect(() => {
    if (vehicleTypeId === null) {
      SafeStorage.remove('stock.vehicleTypeId');
      return;
    }

    SafeStorage.set('stock.vehicleTypeId', vehicleTypeId);
  }, [vehicleTypeId]);

  const handleAdjust = async (item, direction, amount = 1) => {
    if (!(amount > 0)) return;

    setSaving(true);
    try {
      await api.post('/stock-items/adjust', {
        stockItemId: item.id,
        delta: direction === 'add' ? amount : -amount,
      });
      await loadStock();
    } finally {
      setSaving(false);
    }
  };

  const handleRequestSort = (property) => {
    const isAsc = orderBy === property && order === 'asc';
    setOrder(isAsc ? 'desc' : 'asc');
    setOrderBy(property);
  };

  const sortedItems = useMemo(() => {
    const getSortValue = (item, field) => {
      if (field === 'quantity') {
        return parseFloat(item.quantity || 0);
      }
      if (field === 'price') {
        return parseFloat(item.price || 0);
      }
      if (field === 'purchaseDate' || field === 'updatedAt') {
        return item[field] ? new Date(item[field]).getTime() : 0;
      }

      return (item[field] || '').toString().toLowerCase();
    };

    return [...items].sort((a, b) => {
      const aValue = getSortValue(a, orderBy);
      const bValue = getSortValue(b, orderBy);

      if (aValue < bValue) return order === 'asc' ? -1 : 1;
      if (aValue > bValue) return order === 'asc' ? 1 : -1;
      return 0;
    });
  }, [items, order, orderBy]);

  const visibleItems = useMemo(() => {
    const needle = searchTerm.trim().toLowerCase();
    if (!needle) {
      return sortedItems;
    }

    return sortedItems.filter((item) => {
      const haystack = [
        item.itemType,
        item.category,
        item.partNumber,
        item.description,
        item.supplier,
        item.manufacturer,
        item.notes,
        item.price,
        item.quantity,
        item.purchaseDate,
      ]
        .map((v) => (v ?? '').toString().toLowerCase())
        .join(' ');

      return haystack.includes(needle);
    });
  }, [sortedItems, searchTerm]);

  const {
    page,
    rowsPerPage,
    paginatedRows: paginatedItems,
    handleChangePage,
    handleChangeRowsPerPage,
  } = useTablePagination(visibleItems);

  const handleAdd = () => {
    setSelectedItem(null);
    setDialogOpen(true);
  };

  const handleRowClick = (item) => {
    setSelectedItem(item);
    setDialogOpen(true);
  };

  const handleDialogClose = (reload) => {
    setDialogOpen(false);
    setSelectedItem(null);

    if (reload) {
      loadStock();
    }
  };

  if (loading) {
    return (
      <Box display="flex" justifyContent="center" alignItems="center" minHeight="60vh">
        <KnightRiderLoader size={32} />
      </Box>
    );
  }

  return (
    <Box>
      <Box display="flex" justifyContent="space-between" alignItems="center" mb={3}>
        <Typography variant="h4">{t('stock.title', 'Stock')}</Typography>
        <Box display="flex" gap={2}>
          <TextField
            size="small"
            placeholder={t('common.search', 'Search')}
            value={searchTerm}
            onChange={(e) => setSearchTerm(e.target.value)}
            sx={{ minWidth: 260 }}
          />
          <Button
            variant="contained"
            startIcon={<Add />}
            onClick={handleAdd}
          >
            {t('common.add', 'Add')}
          </Button>
        </Box>
      </Box>

      <TablePaginationBar
        count={visibleItems.length}
        page={page}
        rowsPerPage={rowsPerPage}
        onPageChange={handleChangePage}
        onRowsPerPageChange={handleChangeRowsPerPage}
      />

      <TableContainer component={Paper} sx={{ maxHeight: 'calc(100vh - 180px)', overflow: 'auto' }}>
        <Table stickyHeader>
          <TableHead>
            <TableRow>
              <TableCell>
                <TableSortLabel
                  active={orderBy === 'itemType'}
                  direction={orderBy === 'itemType' ? order : 'asc'}
                  onClick={() => handleRequestSort('itemType')}
                >
                  {t('stock.type', 'Type')}
                </TableSortLabel>
              </TableCell>
              <TableCell>
                <TableSortLabel
                  active={orderBy === 'category'}
                  direction={orderBy === 'category' ? order : 'asc'}
                  onClick={() => handleRequestSort('category')}
                >
                  {t('stock.category', 'Category')}
                </TableSortLabel>
              </TableCell>
              <TableCell>
                <TableSortLabel
                  active={orderBy === 'partNumber'}
                  direction={orderBy === 'partNumber' ? order : 'asc'}
                  onClick={() => handleRequestSort('partNumber')}
                >
                  {t('stock.partNumber', 'Part Number')}
                </TableSortLabel>
              </TableCell>
              <TableCell>
                <TableSortLabel
                  active={orderBy === 'description'}
                  direction={orderBy === 'description' ? order : 'asc'}
                  onClick={() => handleRequestSort('description')}
                >
                  {t('stock.description', 'Description')}
                </TableSortLabel>
              </TableCell>
              <TableCell>
                <TableSortLabel
                  active={orderBy === 'quantity'}
                  direction={orderBy === 'quantity' ? order : 'asc'}
                  onClick={() => handleRequestSort('quantity')}
                >
                  {t('stock.quantity', 'Qty')}
                </TableSortLabel>
              </TableCell>
              <TableCell>
                <TableSortLabel
                  active={orderBy === 'price'}
                  direction={orderBy === 'price' ? order : 'asc'}
                  onClick={() => handleRequestSort('price')}
                >
                  {t('stock.price', 'Price')}
                </TableSortLabel>
              </TableCell>
              <TableCell>
                <TableSortLabel
                  active={orderBy === 'supplier'}
                  direction={orderBy === 'supplier' ? order : 'asc'}
                  onClick={() => handleRequestSort('supplier')}
                >
                  {t('stock.supplier', 'Supplier')}
                </TableSortLabel>
              </TableCell>
              <TableCell>
                <TableSortLabel
                  active={orderBy === 'purchaseDate'}
                  direction={orderBy === 'purchaseDate' ? order : 'asc'}
                  onClick={() => handleRequestSort('purchaseDate')}
                >
                  {t('stock.purchaseDate', 'Purchase Date')}
                </TableSortLabel>
              </TableCell>
              <TableCell>
                <TableSortLabel
                  active={orderBy === 'updatedAt'}
                  direction={orderBy === 'updatedAt' ? order : 'desc'}
                  onClick={() => handleRequestSort('updatedAt')}
                >
                  {t('stock.updatedAt', 'Updated')}
                </TableSortLabel>
              </TableCell>
              <TableCell>{t('stock.receipt', 'Receipt')}</TableCell>
              <TableCell>{t('stock.adjust', 'Adjust')}</TableCell>
            </TableRow>
          </TableHead>
          <TableBody>
            {visibleItems.length === 0 ? (
              <TableRow>
                <TableCell colSpan={11} align="center">{t('stock.empty', 'No stock records')}</TableCell>
              </TableRow>
            ) : paginatedItems.map((item) => (
              <TableRow
                key={item.id}
                hover
                sx={{
                  cursor: 'pointer',
                  '&:nth-of-type(odd)': { backgroundColor: 'action.hover' },
                }}
                onClick={() => handleRowClick(item)}
              >
                <TableCell>{item.itemType}</TableCell>
                <TableCell>{item.category}</TableCell>
                <TableCell>{item.partNumber || '-'}</TableCell>
                <TableCell>{item.description || '-'}</TableCell>
                <TableCell>{item.quantity}</TableCell>
                <TableCell>{item.price || '-'}</TableCell>
                <TableCell>{item.supplier || '-'}</TableCell>
                <TableCell>{item.purchaseDate ? new Date(item.purchaseDate).toLocaleDateString() : '-'}</TableCell>
                <TableCell>{item.updatedAt ? new Date(item.updatedAt).toLocaleString() : '-'}</TableCell>
                <TableCell>
                  <ViewAttachmentIconButton record={item} />
                </TableCell>
                <TableCell>
                  <Stack direction="row" spacing={1} alignItems="center">
                    <Button
                      size="small"
                      variant="outlined"
                      type="button"
                      onMouseDown={(e) => e.stopPropagation()}
                      onClick={(e) => {
                        e.preventDefault();
                        e.stopPropagation();
                        handleAdjust(item, 'add', 1);
                      }}
                      disabled={saving}
                    >
                      +
                    </Button>
                    <Button
                      size="small"
                      variant="outlined"
                      color="warning"
                      type="button"
                      onMouseDown={(e) => e.stopPropagation()}
                      onClick={(e) => {
                        e.preventDefault();
                        e.stopPropagation();
                        handleAdjust(item, 'remove', 1);
                      }}
                      disabled={saving || parseFloat(item.quantity || 0) <= 0}
                    >
                      -
                    </Button>
                  </Stack>
                </TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
      </TableContainer>

      <TablePaginationBar
        count={visibleItems.length}
        page={page}
        rowsPerPage={rowsPerPage}
        onPageChange={handleChangePage}
        onRowsPerPageChange={handleChangeRowsPerPage}
      />

      <StockDialog
        open={dialogOpen}
        onClose={handleDialogClose}
        item={selectedItem}
        defaultVehicleTypeId={vehicleTypeId}
        vehicleTypes={vehicleTypes}
      />
    </Box>
  );
};

export default Stock;
