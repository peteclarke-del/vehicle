import React, { useEffect, useState, useCallback } from 'react';
import {
  Box,
  Typography,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableRow,
  Paper,
  Chip,
  IconButton,
  Tooltip,
  Alert,
  Snackbar,
  Button,
  Dialog,
  DialogTitle,
  DialogContent,
  DialogActions,
  TextField,
  FormControlLabel,
  Switch,
  Menu,
  MenuItem,
  ListItemIcon,
  ListItemText,
  Checkbox,
} from '@mui/material';
import {
  Edit as EditIcon,
  AdminPanelSettings,
  Person,
  CheckCircle,
  Cancel,
  LockReset,
  PersonAdd,
} from '@mui/icons-material';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';
import { usePermissions } from '../contexts/PermissionsContext';
import { useTranslation } from 'react-i18next';
import KnightRiderLoader from '../components/KnightRiderLoader';

const AdminUsers = () => {
  const [users, setUsers] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const { api } = useAuth();
  const { isAdmin } = usePermissions();
  const navigate = useNavigate();
  const { t } = useTranslation();

  const [snack, setSnack] = useState({ open: false, message: '', severity: 'success' });
  const [createOpen, setCreateOpen] = useState(false);
  const [creating, setCreating] = useState(false);
  const [newUser, setNewUser] = useState({ firstName: '', lastName: '', email: '', password: '', isAdmin: false, forcePasswordChange: true });

  const handleCreateUser = async () => {
    if (!newUser.email || !newUser.password || !newUser.firstName || !newUser.lastName) {
      setSnack({ open: true, message: 'All fields are required', severity: 'error' });
      return;
    }
    try {
      setCreating(true);
      const resp = await api.post('/admin/users', newUser);
      setUsers(prev => [...prev, resp.data.user]);
      setSnack({ open: true, message: resp.data.message, severity: 'success' });
      setCreateOpen(false);
      setNewUser({ firstName: '', lastName: '', email: '', password: '', isAdmin: false, forcePasswordChange: true });
    } catch (err) {
      setSnack({ open: true, message: err.response?.data?.error || 'Failed to create user', severity: 'error' });
    } finally {
      setCreating(false);
    }
  };

  const loadUsers = useCallback(async () => {
    try {
      setLoading(true);
      const resp = await api.get('/admin/users');
      setUsers(resp.data);
      setError(null);
    } catch (err) {
      setError(err.response?.data?.error || 'Failed to load users');
    } finally {
      setLoading(false);
    }
  }, [api]);

  const toggleActive = async (userId) => {
    try {
      const resp = await api.patch(`/admin/users/${userId}/toggle-active`);
      setUsers(prev => prev.map(u => u.id === userId ? { ...u, isActive: resp.data.isActive } : u));
      setSnack({ open: true, message: resp.data.message, severity: 'success' });
    } catch (err) {
      setSnack({ open: true, message: err.response?.data?.error || 'Failed to toggle status', severity: 'error' });
    }
  };

  const forcePasswordChange = async (userId) => {
    try {
      const resp = await api.patch(`/admin/users/${userId}/force-password-change`);
      setUsers(prev => prev.map(u => u.id === userId ? { ...u, passwordChangeRequired: true } : u));
      setSnack({ open: true, message: resp.data.message, severity: 'success' });
    } catch (err) {
      setSnack({ open: true, message: err.response?.data?.error || 'Failed', severity: 'error' });
    }
  };

  const [rolesAnchor, setRolesAnchor] = useState(null);
  const [rolesUserId, setRolesUserId] = useState(null);
  const availableRoles = ['ROLE_USER', 'ROLE_ADMIN'];

  const handleRolesClick = (e, userId) => {
    e.stopPropagation();
    setRolesAnchor(e.currentTarget);
    setRolesUserId(userId);
  };

  const toggleRole = async (role) => {
    const user = users.find(u => u.id === rolesUserId);
    if (!user) return;
    const currentRoles = user.roles || [];
    const hasRole = currentRoles.includes(role);
    const newRoles = hasRole ? currentRoles.filter(r => r !== role) : [...currentRoles, role];
    try {
      const resp = await api.patch(`/admin/users/${rolesUserId}/roles`, { roles: newRoles });
      setUsers(prev => prev.map(u => u.id === rolesUserId ? { ...u, roles: resp.data.roles } : u));
      setSnack({ open: true, message: resp.data.message, severity: 'success' });
    } catch (err) {
      setSnack({ open: true, message: err.response?.data?.error || 'Failed to update roles', severity: 'error' });
    }
  };

  useEffect(() => {
    if (isAdmin) {
      loadUsers();
    }
  }, [isAdmin, loadUsers]);

  if (!isAdmin) {
    return (
      <Box sx={{ p: 3 }}>
        <Alert severity="error">{t('admin.accessDenied', 'Admin access required')}</Alert>
      </Box>
    );
  }

  if (loading) {
    return (
      <Box display="flex" justifyContent="center" alignItems="center" minHeight="60vh">
        <KnightRiderLoader size={32} />
      </Box>
    );
  }

  return (
    <Box sx={{ p: { xs: 1, sm: 2, md: 3 } }}>
      <Box display="flex" alignItems="center" gap={1} mb={3}>
        <AdminPanelSettings color="primary" />
        <Typography variant="h5" sx={{ flex: 1 }}>{t('admin.userManagement', 'User Management')}</Typography>
        <Button variant="contained" size="small" startIcon={<PersonAdd />} onClick={() => setCreateOpen(true)}>
          {t('admin.createUser', 'Create User')}
        </Button>
      </Box>

      {error && <Alert severity="error" sx={{ mb: 2 }}>{error}</Alert>}

      <TableContainer component={Paper}>
        <Table>
          <TableHead>
            <TableRow>
              <TableCell>{t('admin.name', 'Name')}</TableCell>
              <TableCell>{t('admin.email', 'Email')}</TableCell>
              <TableCell>{t('admin.roles', 'Roles')}</TableCell>
              <TableCell>{t('admin.status', 'Status')}</TableCell>
              <TableCell>{t('admin.lastLogin', 'Last Login')}</TableCell>
              <TableCell align="right">{t('admin.actions', 'Actions')}</TableCell>
            </TableRow>
          </TableHead>
          <TableBody>
            {users.map((u) => {
              const isSelf = u.roles?.includes('ROLE_ADMIN');
              return (
                <TableRow key={u.id} hover sx={{ cursor: 'pointer' }} onClick={() => navigate(`/admin/users/${u.id}`)}>
                  <TableCell>
                    <Box display="flex" alignItems="center" gap={1}>
                      {u.roles?.includes('ROLE_ADMIN') ? (
                        <AdminPanelSettings fontSize="small" color="primary" />
                      ) : (
                        <Person fontSize="small" color="action" />
                      )}
                      {u.firstName} {u.lastName}
                    </Box>
                  </TableCell>
                  <TableCell>{u.email}</TableCell>
                  <TableCell>
                    <Box display="flex" alignItems="center" gap={0.5} onClick={(e) => handleRolesClick(e, u.id)} sx={{ cursor: 'pointer' }}>
                      {u.roles?.map((role) => (
                        <Chip
                          key={role}
                          label={role.replace('ROLE_', '')}
                          size="small"
                          color={role === 'ROLE_ADMIN' ? 'primary' : 'default'}
                        />
                      ))}
                    </Box>
                  </TableCell>
                  <TableCell>
                    <Tooltip title={isSelf ? '' : (u.isActive ? t('admin.clickToDeactivate', 'Click to deactivate') : t('admin.clickToActivate', 'Click to activate'))}>
                      <Chip
                        icon={u.isActive ? <CheckCircle /> : <Cancel />}
                        label={u.isActive ? t('admin.active', 'Active') : t('admin.inactive', 'Inactive')}
                        size="small"
                        color={u.isActive ? 'success' : 'error'}
                        variant="outlined"
                        onClick={isSelf ? undefined : (e) => { e.stopPropagation(); toggleActive(u.id); }}
                        sx={isSelf ? {} : { cursor: 'pointer' }}
                      />
                    </Tooltip>
                    {u.passwordChangeRequired && (
                      <Chip label={t('admin.pwReset', 'PW Reset')} size="small" color="warning" sx={{ ml: 0.5 }} />
                    )}
                  </TableCell>
                  <TableCell>
                    {u.lastLoginAt
                      ? new Date(u.lastLoginAt).toLocaleDateString()
                      : t('admin.never', 'Never')}
                  </TableCell>
                  <TableCell align="right">
                    <Box display="flex" justifyContent="flex-end" gap={0.5}>
                      <Tooltip title={t('admin.forcePasswordChange', 'Force password change')}>
                        <span>
                          <IconButton
                            size="small"
                            color={u.passwordChangeRequired ? 'warning' : 'default'}
                            onClick={(e) => { e.stopPropagation(); forcePasswordChange(u.id); }}
                            disabled={u.passwordChangeRequired}
                          >
                            <LockReset fontSize="small" />
                          </IconButton>
                        </span>
                      </Tooltip>
                      <Tooltip title={t('admin.manageUser', 'Manage User')}>
                        <IconButton size="small" onClick={(e) => { e.stopPropagation(); navigate(`/admin/users/${u.id}`); }}>
                          <EditIcon fontSize="small" />
                        </IconButton>
                      </Tooltip>
                    </Box>
                  </TableCell>
                </TableRow>
              );
            })}
            {users.length === 0 && (
              <TableRow>
                <TableCell colSpan={6} align="center">
                  {t('admin.noUsers', 'No users found')}
                </TableCell>
              </TableRow>
            )}
          </TableBody>
        </Table>
      </TableContainer>

      <Menu
        anchorEl={rolesAnchor}
        open={Boolean(rolesAnchor)}
        onClose={() => setRolesAnchor(null)}
      >
        {availableRoles.map((role) => {
          const user = users.find(u => u.id === rolesUserId);
          const checked = user?.roles?.includes(role) || false;
          const isSelf = user?.roles?.includes('ROLE_ADMIN') && rolesUserId === users.find(u2 => u2.roles?.includes('ROLE_ADMIN'))?.id;
          return (
            <MenuItem
              key={role}
              onClick={() => toggleRole(role)}
              disabled={role === 'ROLE_USER'}
              dense
            >
              <ListItemIcon>
                <Checkbox size="small" checked={checked} disableRipple sx={{ p: 0 }} />
              </ListItemIcon>
              <ListItemText primary={role.replace('ROLE_', '')} />
            </MenuItem>
          );
        })}
      </Menu>

      <Dialog open={createOpen} onClose={() => setCreateOpen(false)} maxWidth="sm" fullWidth>
        <DialogTitle>{t('admin.createUser', 'Create User')}</DialogTitle>
        <DialogContent sx={{ display: 'flex', flexDirection: 'column', gap: 2, pt: '8px !important' }}>
          <Box sx={{ display: 'flex', gap: 2 }}>
            <TextField
              label={t('admin.firstName', 'First Name')}
              value={newUser.firstName}
              onChange={(e) => setNewUser(prev => ({ ...prev, firstName: e.target.value }))}
              fullWidth
              size="small"
              required
              autoFocus
            />
            <TextField
              label={t('admin.lastName', 'Last Name')}
              value={newUser.lastName}
              onChange={(e) => setNewUser(prev => ({ ...prev, lastName: e.target.value }))}
              fullWidth
              size="small"
              required
            />
          </Box>
          <TextField
            label={t('admin.email', 'Email')}
            type="email"
            value={newUser.email}
            onChange={(e) => setNewUser(prev => ({ ...prev, email: e.target.value }))}
            fullWidth
            size="small"
            required
          />
          <TextField
            label={t('admin.password', 'Password')}
            type="password"
            value={newUser.password}
            onChange={(e) => setNewUser(prev => ({ ...prev, password: e.target.value }))}
            fullWidth
            size="small"
            required
          />
          <Box sx={{ display: 'flex', gap: 2 }}>
            <FormControlLabel
              control={<Switch checked={newUser.isAdmin} onChange={(e) => setNewUser(prev => ({ ...prev, isAdmin: e.target.checked }))} size="small" />}
              label={t('admin.adminRole', 'Admin role')}
            />
            <FormControlLabel
              control={<Switch checked={newUser.forcePasswordChange} onChange={(e) => setNewUser(prev => ({ ...prev, forcePasswordChange: e.target.checked }))} size="small" />}
              label={t('admin.forcePasswordChangeOnLogin', 'Force password change on login')}
            />
          </Box>
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setCreateOpen(false)}>{t('common.cancel', 'Cancel')}</Button>
          <Button variant="contained" onClick={handleCreateUser} disabled={creating}>
            {creating ? t('common.creating', 'Creating...') : t('admin.createUser', 'Create User')}
          </Button>
        </DialogActions>
      </Dialog>

      <Snackbar
        open={snack.open}
        autoHideDuration={3000}
        onClose={() => setSnack(prev => ({ ...prev, open: false }))}
      >
        <Alert
          onClose={() => setSnack(prev => ({ ...prev, open: false }))}
          severity={snack.severity}
          sx={{ width: '100%' }}
        >
          {snack.message}
        </Alert>
      </Snackbar>
    </Box>
  );
};

export default AdminUsers;
