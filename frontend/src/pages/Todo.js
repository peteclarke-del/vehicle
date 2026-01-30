import React, { useEffect, useState, useMemo, useCallback } from 'react';
import {
  Box,
  Button,
  Typography,
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
  Checkbox,
  Tooltip,
  TableSortLabel,
} from '@mui/material';
import { Add, Edit, Delete } from '@mui/icons-material';
import { useAuth } from '../contexts/AuthContext';
import { useTranslation } from 'react-i18next';
import TodoDialog from '../components/TodoDialog';
import { fetchArrayData } from '../hooks/useApiData';
import useTablePagination from '../hooks/useTablePagination';
import TablePaginationBar from '../components/TablePaginationBar';
import VehicleSelector from '../components/VehicleSelector';

const Todo = () => {
  const { api } = useAuth();
  const { t } = useTranslation();
  const registrationLabelText = t('common.registrationNumber');
  const regWords = (registrationLabelText || '').split(/\s+/).filter(Boolean);
  const regLast = regWords.length > 0 ? regWords[regWords.length - 1] : '';
  const regFirst = regWords.length > 1 ? regWords.slice(0, regWords.length - 1).join(' ') : (regWords[0] || 'Registration');
  const [vehicles, setVehicles] = useState([]);
  const [selectedVehicle, setSelectedVehicle] = useState('');
  const [todos, setTodos] = useState([]);
  const [loading, setLoading] = useState(true);
  const [dialogOpen, setDialogOpen] = useState(false);
  const [editing, setEditing] = useState(null);
  const [orderBy, setOrderBy] = useState(() => localStorage.getItem('todoSortBy') || 'dueDate');
  const [order, setOrder] = useState(() => localStorage.getItem('todoSortOrder') || 'desc');

  const handleRequestSort = (property) => {
    const isAsc = orderBy === property && order === 'asc';
    const newOrder = isAsc ? 'desc' : 'asc';
    setOrder(newOrder);
    setOrderBy(property);
    localStorage.setItem('todoSortBy', property);
    localStorage.setItem('todoSortOrder', newOrder);
  };

  const sortedTodos = useMemo(() => {
    const comparator = (a, b) => {
      if (orderBy === 'registration') {
        const aReg = vehicles.find(v => String(v.id) === String(a.vehicleId))?.registrationNumber || '';
        const bReg = vehicles.find(v => String(v.id) === String(b.vehicleId))?.registrationNumber || '';
        if (aReg === bReg) return 0;
        return order === 'asc' ? (aReg > bReg ? 1 : -1) : (aReg < bReg ? 1 : -1);
      }
      let aValue = a[orderBy];
      let bValue = b[orderBy];

      if (orderBy === 'links') {
        aValue = (a.parts?.length || 0) + (a.consumables?.length || 0);
        bValue = (b.parts?.length || 0) + (b.consumables?.length || 0);
      }

      if (orderBy === 'dueDate' || orderBy === 'completedBy') {
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
    return [...todos].sort(comparator);
  }, [todos, order, orderBy]);

  const { page, rowsPerPage, paginatedRows: paginatedTodos, handleChangePage, handleChangeRowsPerPage } = useTablePagination(sortedTodos);

  const fetchVehicles = useCallback(async () => {
    const data = await fetchArrayData(api, '/vehicles');
    setVehicles(data);
    if (data.length > 0) setSelectedVehicle((prev) => prev || data[0].id);
    setLoading(false);
  }, [api]);

  const loadTodos = useCallback(async () => {
    try {
      const url = !selectedVehicle || selectedVehicle === '__all__' ? '/todos' : `/todos?vehicleId=${selectedVehicle}`;
      const response = await api.get(url);
      setTodos(response.data);
    } catch (err) {
      console.error('Error loading todos', err);
    }
  }, [api, selectedVehicle]);

  useEffect(() => {
    fetchVehicles();
  }, [fetchVehicles]);

  useEffect(() => {
    loadTodos();
  }, [selectedVehicle, loadTodos]);

  const handleAdd = () => {
    setEditing(null);
    setDialogOpen(true);
  };

  const handleEdit = (todo) => {
    setEditing(todo);
    setDialogOpen(true);
  };

  const handleDelete = async (id) => {
    if (!window.confirm(t('common.confirmDelete'))) return;
    try {
      await api.delete(`/todos/${id}`);
      loadTodos();
    } catch (err) {
      console.error('Failed to delete todo', err);
    }
  };

  const handleToggleDone = async (todo) => {
    try {
      const updated = { ...todo, done: !todo.done, completedBy: !todo.done ? new Date().toISOString() : null };
      await api.put(`/todos/${todo.id}`, updated);
      loadTodos();
    } catch (err) {
      console.error('Failed to update todo', err);
    }
  };

  return (
    <Box>
      <Box display="flex" justifyContent="space-between" alignItems="center" mb={3}>
        <Typography variant="h4">{t('todo.title') || 'Vehicle TODOs'}</Typography>
          <Box display="flex" gap={2}>
            <VehicleSelector
              vehicles={vehicles}
              value={selectedVehicle}
              onChange={(v) => setSelectedVehicle(v)}
              minWidth={300}
              includeViewAll={true}
            />
            <Button variant="contained" startIcon={<Add />} onClick={handleAdd} disabled={!selectedVehicle || selectedVehicle === '__all__'}>
              {t('todo.add') || 'Add TODO'}
            </Button>
          </Box>
      </Box>

      <TablePaginationBar
        count={sortedTodos.length}
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
                    active={orderBy === 'registration'}
                    direction={orderBy === 'registration' ? order : 'asc'}
                    onClick={() => handleRequestSort('registration')}
                  >
                    <div style={{ display: 'flex', flexDirection: 'column', lineHeight: 1 }}>
                      <span>{regFirst}</span>
                      <span>{regLast}</span>
                    </div>
                  </TableSortLabel>
                </TableCell>
              <TableCell>
                <TableSortLabel
                  active={orderBy === 'title'}
                  direction={orderBy === 'title' ? order : 'asc'}
                  onClick={() => handleRequestSort('title')}
                >
                  {t('todo.titleLabel') || 'Title'}
                </TableSortLabel>
              </TableCell>
              <TableCell>
                <TableSortLabel
                  active={orderBy === 'links'}
                  direction={orderBy === 'links' ? order : 'asc'}
                  onClick={() => handleRequestSort('links')}
                >
                  {t('todo.links') || 'Linked'}
                </TableSortLabel>
              </TableCell>
              <TableCell>
                <TableSortLabel
                  active={orderBy === 'dueDate'}
                  direction={orderBy === 'dueDate' ? order : 'desc'}
                  onClick={() => handleRequestSort('dueDate')}
                >
                  {t('todo.due') || 'Due'}
                </TableSortLabel>
              </TableCell>
              <TableCell>
                <TableSortLabel
                  active={orderBy === 'completedBy'}
                  direction={orderBy === 'completedBy' ? order : 'desc'}
                  onClick={() => handleRequestSort('completedBy')}
                >
                  {t('todo.completedBy') || 'Completed By'}
                </TableSortLabel>
              </TableCell>
              <TableCell align="center">{t('common.actions')}</TableCell>
            </TableRow>
          </TableHead>
          <TableBody>
            {sortedTodos.length === 0 ? (
              <TableRow>
                <TableCell colSpan={6} align="center">{t('common.noRecords')}</TableCell>
              </TableRow>
            ) : (
              paginatedTodos.map((todo) => (
                <TableRow key={todo.id} sx={{ '&:nth-of-type(odd)': { backgroundColor: 'action.hover' } }}>
                  <TableCell>{vehicles.find(v => String(v.id) === String(todo.vehicleId))?.registrationNumber || '-'}</TableCell>
                  <TableCell>{todo.title}</TableCell>
                  <TableCell>{(todo.parts?.length || 0) + (todo.consumables?.length || 0)}</TableCell>
                  <TableCell>{todo.dueDate ? new Date(todo.dueDate).toLocaleDateString() : '-'}</TableCell>
                  <TableCell>{todo.completedBy ? new Date(todo.completedBy).toLocaleString() : '-'}</TableCell>
                  <TableCell align="center">
                    <Tooltip title={t('todo.toggleDone') || 'Mark as done'}>
                      <Checkbox
                        checked={!!todo.done}
                        onChange={() => handleToggleDone(todo)}
                        inputProps={{ 'aria-label': t('todo.toggleDone') || 'Mark as done' }}
                        size="small"
                      />
                    </Tooltip>
                    <Tooltip title={t('common.edit')}>
                      <IconButton size="small" onClick={() => handleEdit(todo)}><Edit /></IconButton>
                    </Tooltip>
                    <Tooltip title={t('common.delete')}>
                      <IconButton size="small" onClick={() => handleDelete(todo.id)}><Delete /></IconButton>
                    </Tooltip>
                  </TableCell>
                </TableRow>
              ))
            )}
          </TableBody>
        </Table>
      </TableContainer>
      <TablePaginationBar
        count={sortedTodos.length}
        page={page}
        rowsPerPage={rowsPerPage}
        onPageChange={handleChangePage}
        onRowsPerPageChange={handleChangeRowsPerPage}
      />

      <TodoDialog
        open={dialogOpen}
        onClose={(saved) => { setDialogOpen(false); setEditing(null); if (saved) loadTodos(); }}
        vehicleId={selectedVehicle}
        todo={editing}
      />
    </Box>
  );
};

export default Todo;