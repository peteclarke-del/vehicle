import React, { useEffect, useState } from 'react';
import {
  Box,
  Button,
  Typography,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableRow,
  Paper,
  IconButton,
  MenuItem,
  TextField,
  CircularProgress,
  Chip,
  FormControl,
  InputLabel,
  Select,
  Tooltip,
  TableSortLabel,
} from '@mui/material';
import { Add, Edit, Delete } from '@mui/icons-material';
import { useAuth } from '../contexts/AuthContext';
import { useTranslation } from 'react-i18next';
import { fetchArrayData } from '../hooks/useApiData';
import { useDistance } from '../hooks/useDistance';
import PartDialog from '../components/PartDialog';

const Parts = () => {
  const [parts, setParts] = useState([]);
  const [vehicles, setVehicles] = useState([]);
  const [selectedVehicle, setSelectedVehicle] = useState('');
  const [loading, setLoading] = useState(true);
  const [dialogOpen, setDialogOpen] = useState(false);
  const [selectedPart, setSelectedPart] = useState(null);
  const [orderBy, setOrderBy] = useState(() => localStorage.getItem('partsSortBy') || 'description');
  const [order, setOrder] = useState(() => localStorage.getItem('partsSortOrder') || 'asc');
  const { api } = useAuth();
  const { t } = useTranslation();
  const { convert, format, getLabel } = useDistance();

  useEffect(() => {
    loadVehicles();
  }, []);

  useEffect(() => {
    if (selectedVehicle) {
      loadParts();
    } else {
      setParts([]);
    }
  }, [selectedVehicle]);

  const loadVehicles = async () => {
    const data = await fetchArrayData(api, '/vehicles');
    setVehicles(data);
    if (data.length > 0) {
      setSelectedVehicle(data[0].id);
    }
    setLoading(false);
  };

  const loadParts = async () => {
    try {
      const response = await api.get(`/parts?vehicleId=${selectedVehicle}`);
      setParts(response.data);
    } catch (error) {
      console.error('Error loading parts:', error);
    }
  };

  const handleAdd = () => {
    setSelectedPart(null);
    setDialogOpen(true);
  };

  const handleEdit = (part) => {
    setSelectedPart(part);
    setDialogOpen(true);
  };

  const handleDelete = async (id) => {
    if (window.confirm(t('common.confirmDelete') || 'Are you sure you want to delete this part?')) {
      try {
        await api.delete(`/parts/${id}`);
        loadParts();
      } catch (error) {
        console.error('Error deleting part:', error);
      }
    }
  };

  const handleDialogClose = (reload) => {
    setDialogOpen(false);
    setSelectedPart(null);
    if (reload) {
      loadParts();
    }
  };

  const handleRequestSort = (property) => {
    const isAsc = orderBy === property && order === 'asc';
    const newOrder = isAsc ? 'desc' : 'asc';
    setOrder(newOrder);
    setOrderBy(property);
    localStorage.setItem('partsSortBy', property);
    localStorage.setItem('partsSortOrder', newOrder);
  };

  const sortedParts = React.useMemo(() => {
    const comparator = (a, b) => {
      let aValue = a[orderBy];
      let bValue = b[orderBy];

      // Numeric conversions for cost fields
      if (['cost'].includes(orderBy)) {
        aValue = parseFloat(aValue) || 0;
        bValue = parseFloat(bValue) || 0;
      }
      
      if (!aValue) aValue = '';
      if (!bValue) bValue = '';

      if (bValue < aValue) {
        return order === 'asc' ? 1 : -1;
      }
      if (bValue > aValue) {
        return order === 'asc' ? -1 : 1;
      }
      return 0;
    };

    return [...parts].sort(comparator);
  }, [parts, order, orderBy]);

  const calculateTotalCost = () => {
    return parts.reduce((sum, part) => sum + (parseFloat(part.cost) || 0), 0).toFixed(2);
  };

  if (loading) {
    return (
      <Box display="flex" justifyContent="center" alignItems="center" minHeight="60vh">
        <CircularProgress />
      </Box>
    );
  }

  if (vehicles.length === 0) {
    return (
      <Box>
        <Typography variant="h4" gutterBottom>
          {t('parts.title')}
        </Typography>
        <Typography color="textSecondary">
          {t('common.noVehicles')}
        </Typography>
      </Box>
    );
  }

  return (
    <Box>
      <Box display="flex" justifyContent="space-between" alignItems="center" mb={3}>
        <Typography variant="h4">{t('parts.title')}</Typography>
        <Box display="flex" gap={2}>
                <FormControl size="small" sx={{ width: 240 }}>
                  <InputLabel>{t('common.selectVehicle')}</InputLabel>
                  <Select
                    value={selectedVehicle}
                    label={t('common.selectVehicle')}
                    onChange={(e) => setSelectedVehicle(e.target.value)}
                  >
              {vehicles.map((vehicle) => (
                <MenuItem key={vehicle.id} value={vehicle.id}>
                  {vehicle.name}
                </MenuItem>
              ))}
            </Select>
          </FormControl>
          <Button
            variant="contained"
            startIcon={<Add />}
            onClick={handleAdd}
            disabled={!selectedVehicle}
          >
            {t('parts.addPart')}
          </Button>
        </Box>
      </Box>

      {parts.length > 0 && (
        <Box mb={2}>
          <Typography variant="h6">
            {t('parts.totalCost')}: £{calculateTotalCost()}
          </Typography>
        </Box>
      )}

      <TableContainer component={Paper} sx={{ maxHeight: 'calc(100vh - 180px)', overflow: 'auto' }}>
        <Table stickyHeader>
          <TableHead>
            <TableRow>
              <TableCell>
                <TableSortLabel
                  active={orderBy === 'description'}
                  direction={orderBy === 'description' ? order : 'asc'}
                  onClick={() => handleRequestSort('description')}
                >
                  {t('parts.description')}
                </TableSortLabel>
              </TableCell>
              <TableCell>
                <TableSortLabel
                  active={orderBy === 'partNumber'}
                  direction={orderBy === 'partNumber' ? order : 'asc'}
                  onClick={() => handleRequestSort('partNumber')}
                >
                  {t('parts.partNumber')}
                </TableSortLabel>
              </TableCell>
              <TableCell>
                <TableSortLabel
                  active={orderBy === 'category'}
                  direction={orderBy === 'category' ? order : 'asc'}
                  onClick={() => handleRequestSort('category')}
                >
                  {t('parts.category')}
                </TableSortLabel>
              </TableCell>
              <TableCell>
                <TableSortLabel
                  active={orderBy === 'manufacturer'}
                  direction={orderBy === 'manufacturer' ? order : 'asc'}
                  onClick={() => handleRequestSort('manufacturer')}
                >
                  {t('parts.manufacturer')}
                </TableSortLabel>
              </TableCell>
              <TableCell>
                <TableSortLabel
                  active={orderBy === 'cost'}
                  direction={orderBy === 'cost' ? order : 'asc'}
                  onClick={() => handleRequestSort('cost')}
                >
                  {t('parts.cost')}
                </TableSortLabel>
              </TableCell>
              <TableCell>
                <TableSortLabel
                  active={orderBy === 'purchaseDate'}
                  direction={orderBy === 'purchaseDate' ? order : 'asc'}
                  onClick={() => handleRequestSort('purchaseDate')}
                >
                  {t('parts.purchaseDate')}
                </TableSortLabel>
              </TableCell>
              <TableCell>
                <TableSortLabel
                  active={orderBy === 'installationDate'}
                  direction={orderBy === 'installationDate' ? order : 'asc'}
                  onClick={() => handleRequestSort('installationDate')}
                >
                  {t('parts.installationDate')}
                </TableSortLabel>
              </TableCell>
              <TableCell>{t('common.actions')}</TableCell>
            </TableRow>
          </TableHead>
          <TableBody>
            {sortedParts.length === 0 ? (
              <TableRow>
                <TableCell colSpan={8} align="center">
                  <Typography color="textSecondary">{t('common.noRecords')}</Typography>
                </TableCell>
              </TableRow>
            ) : (
              sortedParts.map((part) => (
                <TableRow key={part.id} sx={{ '&:nth-of-type(odd)': { backgroundColor: 'action.hover' } }}>
                  <TableCell>{part.description}</TableCell>
                  <TableCell>{part.partNumber || '-'}</TableCell>
                  <TableCell>
                    {part.category ? (
                      <Chip label={part.category} size="small" />
                    ) : (
                      '-'
                    )}
                  </TableCell>
                  <TableCell>{part.manufacturer || '-'}</TableCell>
                  <TableCell>£{parseFloat(part.cost).toFixed(2)}</TableCell>
                  <TableCell>{part.purchaseDate || '-'}</TableCell>
                  <TableCell>{part.installationDate || '-'}</TableCell>
                  <TableCell>
                        <Tooltip title={t('edit')}>
                      <IconButton size="small" onClick={() => handleEdit(part)}>
                        <Edit />
                      </IconButton>
                    </Tooltip>
                        <Tooltip title={t('delete')}>
                      <IconButton size="small" onClick={() => handleDelete(part.id)}>
                        <Delete />
                      </IconButton>
                    </Tooltip>
                  </TableCell>
                </TableRow>
              ))
            )}
          </TableBody>
        </Table>
      </TableContainer>

      <PartDialog
        open={dialogOpen}
        onClose={handleDialogClose}
        part={selectedPart}
        vehicleId={selectedVehicle}
      />
    </Box>
  );
};

export default Parts;
