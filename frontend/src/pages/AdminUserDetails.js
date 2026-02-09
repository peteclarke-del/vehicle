import React, { useEffect, useState, useCallback } from 'react';
import {
  Box,
  Typography,
  Paper,
  Tabs,
  Tab,
  Switch,
  Button,
  Alert,
  Snackbar,
  Divider,
  Chip,
  Checkbox,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableRow,
  IconButton,
  Tooltip,
} from '@mui/material';
import {
  ArrowBack,
  Save,
  RestartAlt,
  Person,
  AdminPanelSettings,
} from '@mui/icons-material';
import { useParams, useNavigate } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';
import { usePermissions } from '../contexts/PermissionsContext';
import { useTranslation } from 'react-i18next';
import KnightRiderLoader from '../components/KnightRiderLoader';

const AdminUserDetails = () => {
  const { id } = useParams();
  const navigate = useNavigate();
  const { api } = useAuth();
  const { isAdmin } = usePermissions();
  const { t } = useTranslation();

  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState(null);
  const [snack, setSnack] = useState({ open: false, message: '', severity: 'success' });
  const [tabIndex, setTabIndex] = useState(0);

  // User data
  const [userData, setUserData] = useState(null);

  // Feature flags state
  const [allFlags, setAllFlags] = useState({});
  const [features, setFeatures] = useState({});

  // Vehicle assignments state
  const [assignments, setAssignments] = useState([]);
  const [availableVehicles, setAvailableVehicles] = useState([]);

  const loadUserData = useCallback(async () => {
    try {
      setLoading(true);
      const [userResp, featuresResp, assignmentsResp] = await Promise.all([
        api.get(`/admin/users/${id}`),
        api.get(`/admin/users/${id}/features`),
        api.get(`/admin/users/${id}/assignments`),
      ]);

      setUserData(userResp.data);
      setAllFlags(featuresResp.data.allFlags || {});
      setFeatures(featuresResp.data.features || {});
      setAssignments(assignmentsResp.data.assignments || []);
      setAvailableVehicles(assignmentsResp.data.availableVehicles || []);
      setError(null);
    } catch (err) {
      setError(err.response?.data?.error || 'Failed to load user data');
    } finally {
      setLoading(false);
    }
  }, [api, id]);

  useEffect(() => {
    if (isAdmin) {
      loadUserData();
    }
  }, [isAdmin, loadUserData]);

  const handleFeatureToggle = (featureKey) => {
    setFeatures(prev => ({
      ...prev,
      [featureKey]: !prev[featureKey],
    }));
  };

  const handleToggleCategory = (category, enabled) => {
    const flagsInCategory = allFlags[category] || [];
    const updates = {};
    flagsInCategory.forEach(flag => {
      updates[flag.featureKey] = enabled;
    });
    setFeatures(prev => ({ ...prev, ...updates }));
  };

  const saveFeatures = async () => {
    try {
      setSaving(true);
      await api.put(`/admin/users/${id}/features`, { features });
      setSnack({ open: true, message: t('admin.featuresSaved', 'Feature flags saved successfully'), severity: 'success' });
    } catch (err) {
      setSnack({ open: true, message: err.response?.data?.error || 'Failed to save', severity: 'error' });
    } finally {
      setSaving(false);
    }
  };

  const resetFeatures = async () => {
    try {
      setSaving(true);
      const resp = await api.post(`/admin/users/${id}/features/reset`);
      setFeatures(resp.data.features || {});
      setSnack({ open: true, message: t('admin.featuresReset', 'Feature flags reset to defaults'), severity: 'success' });
    } catch (err) {
      setSnack({ open: true, message: err.response?.data?.error || 'Failed to reset', severity: 'error' });
    } finally {
      setSaving(false);
    }
  };

  const handleAssignmentToggle = (vehicleId) => {
    setAssignments(prev => {
      const exists = prev.find(a => a.vehicleId === vehicleId);
      if (exists) {
        return prev.filter(a => a.vehicleId !== vehicleId);
      } else {
        return [...prev, {
          vehicleId,
          canView: true,
          canEdit: true,
          canAddRecords: true,
          canDelete: false,
        }];
      }
    });
  };

  const handleAssignmentPermission = (vehicleId, permission, value) => {
    setAssignments(prev =>
      prev.map(a =>
        a.vehicleId === vehicleId ? { ...a, [permission]: value } : a
      )
    );
  };

  const saveAssignments = async () => {
    try {
      setSaving(true);
      await api.put(`/admin/users/${id}/assignments`, { assignments });
      setSnack({ open: true, message: t('admin.assignmentsSaved', 'Vehicle assignments saved'), severity: 'success' });
    } catch (err) {
      setSnack({ open: true, message: err.response?.data?.error || 'Failed to save', severity: 'error' });
    } finally {
      setSaving(false);
    }
  };

  const clearAssignments = async () => {
    try {
      setSaving(true);
      await api.delete(`/admin/users/${id}/assignments`);
      setAssignments([]);
      setSnack({ open: true, message: t('admin.assignmentsCleared', 'All assignments removed'), severity: 'success' });
    } catch (err) {
      setSnack({ open: true, message: err.response?.data?.error || 'Failed to clear', severity: 'error' });
    } finally {
      setSaving(false);
    }
  };

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

  if (error) {
    return (
      <Box sx={{ p: 3 }}>
        <Alert severity="error">{error}</Alert>
      </Box>
    );
  }

  const isTargetAdmin = userData?.roles?.includes('ROLE_ADMIN');

  return (
    <Box sx={{ p: { xs: 1, sm: 2, md: 3 } }}>
      {/* Header */}
      <Box display="flex" alignItems="center" gap={1} mb={2}>
        <IconButton onClick={() => navigate('/admin/users')}>
          <ArrowBack />
        </IconButton>
        {isTargetAdmin ? (
          <AdminPanelSettings color="primary" />
        ) : (
          <Person color="action" />
        )}
        <Typography variant="h5">
          {userData?.firstName} {userData?.lastName}
        </Typography>
        <Chip
          label={userData?.email}
          size="small"
          variant="outlined"
          sx={{ ml: 1 }}
        />
        {isTargetAdmin && (
          <Chip label="ADMIN" size="small" color="primary" sx={{ ml: 1 }} />
        )}
      </Box>

      {isTargetAdmin && (
        <Alert severity="info" sx={{ mb: 1, py: 0 }}>
          {t('admin.adminUserNote', 'Admin users have all features enabled and can access all vehicles. Feature flags and assignments have no effect on admin accounts.')}
        </Alert>
      )}

      {/* Tabs */}
      <Paper sx={{ mb: 1 }}>
        <Tabs value={tabIndex} onChange={(_, v) => setTabIndex(v)}>
          <Tab label={t('admin.featureFlags', 'Feature Flags')} />
          <Tab label={t('admin.vehicleAssignments', 'Vehicle Assignments')} />
        </Tabs>
      </Paper>

      {/* Feature Flags Tab */}
      {tabIndex === 0 && (
        <Box>
          <Box display="flex" gap={1} mb={1}>
            <Button
              variant="contained"
              size="small"
              startIcon={<Save />}
              onClick={saveFeatures}
              disabled={saving || isTargetAdmin}
            >
              {t('admin.saveFeatures', 'Save Features')}
            </Button>
            <Button
              variant="outlined"
              size="small"
              startIcon={<RestartAlt />}
              onClick={resetFeatures}
              disabled={saving || isTargetAdmin}
            >
              {t('admin.resetDefaults', 'Reset to Defaults')}
            </Button>
          </Box>

          <Box sx={{ display: 'grid', gridTemplateColumns: { xs: '1fr', md: '1fr 1fr' }, gap: 1 }}>
            {Object.entries(allFlags).map(([category, flags]) => {
              const enabledCount = flags.filter(f => features[f.featureKey] !== false).length;
              const allEnabled = enabledCount === flags.length;
              const noneEnabled = enabledCount === 0;

              return (
                <Paper
                  key={category}
                  variant="outlined"
                  sx={{
                    display: 'flex',
                    alignItems: 'center',
                    px: 1.5,
                    py: 0.5,
                    minHeight: 38,
                    '&:hover': { bgcolor: 'action.hover' },
                  }}
                >
                  <Typography variant="body2" fontWeight="bold" sx={{ minWidth: 110, flexShrink: 0, fontSize: '0.875rem' }}>
                    {category}
                  </Typography>
                  <Box sx={{ display: 'flex', flexWrap: 'wrap', alignItems: 'center', flex: 1, mx: 0.5 }}>
                    {flags.map((flag) => {
                      const shortLabel = flag.label
                        .replace(new RegExp(`\\s*${category.replace(/[/ ]/g, '\\s*')}\\s*`, 'i'), '')
                        .replace(/^(View|Create|Edit|Delete|Upload|Generate|Import|Export)\s*/i, '$1 ')
                        .trim() || flag.label;
                      return (
                        <Tooltip key={flag.featureKey} title={flag.featureKey} placement="top" arrow>
                          <Box sx={{ display: 'flex', alignItems: 'center', mr: 1 }}>
                            <Switch
                              size="small"
                              checked={features[flag.featureKey] !== false}
                              onChange={() => handleFeatureToggle(flag.featureKey)}
                              disabled={isTargetAdmin}
                              sx={{ mr: 0.25 }}
                            />
                            <Typography variant="body2" sx={{ fontSize: '0.84rem', whiteSpace: 'nowrap', userSelect: 'none' }}>
                              {shortLabel}
                            </Typography>
                          </Box>
                        </Tooltip>
                      );
                    })}
                  </Box>
                  <Chip
                    label={`${enabledCount}/${flags.length}`}
                    size="small"
                    color={allEnabled ? 'success' : noneEnabled ? 'error' : 'warning'}
                    variant="outlined"
                    sx={{ height: 22, '& .MuiChip-label': { px: 0.75, fontSize: '0.75rem' }, flexShrink: 0 }}
                  />
                  <Tooltip title={allEnabled ? t('admin.disableAll', 'Disable All') : t('admin.enableAll', 'Enable All')}>
                    <Switch
                      size="small"
                      checked={allEnabled}
                      onChange={() => handleToggleCategory(category, !allEnabled)}
                      disabled={isTargetAdmin}
                      sx={{ flexShrink: 0, ml: 0.5 }}
                    />
                  </Tooltip>
                </Paper>
              );
            })}
          </Box>
        </Box>
      )}

      {/* Vehicle Assignments Tab */}
      {tabIndex === 1 && (
        <Box>
          <Alert severity="info" sx={{ mb: 2 }}>
            {t('admin.assignmentsInfo', 'Assign vehicles to this user. Users always see their own vehicles. Assignments grant access to vehicles owned by other users.')}
          </Alert>

          <Box display="flex" gap={1} mb={2}>
            <Button
              variant="contained"
              startIcon={<Save />}
              onClick={saveAssignments}
              disabled={saving || isTargetAdmin}
            >
              {t('admin.saveAssignments', 'Save Assignments')}
            </Button>
            <Button
              variant="outlined"
              color="error"
              startIcon={<RestartAlt />}
              onClick={clearAssignments}
              disabled={saving || isTargetAdmin || assignments.length === 0}
            >
              {t('admin.clearAll', 'Clear All')}
            </Button>
          </Box>

          <TableContainer component={Paper}>
            <Table size="small">
              <TableHead>
                <TableRow>
                  <TableCell padding="checkbox">
                    <Checkbox
                      indeterminate={assignments.length > 0 && assignments.length < availableVehicles.length}
                      checked={assignments.length === availableVehicles.length && availableVehicles.length > 0}
                      onChange={(e) => {
                        if (e.target.checked) {
                          setAssignments(availableVehicles.map(v => ({
                            vehicleId: v.id,
                            canView: true,
                            canEdit: true,
                            canAddRecords: true,
                            canDelete: false,
                          })));
                        } else {
                          setAssignments([]);
                        }
                      }}
                      disabled={isTargetAdmin}
                    />
                  </TableCell>
                  <TableCell>{t('admin.vehicle', 'Vehicle')}</TableCell>
                  <TableCell>{t('admin.owner', 'Owner')}</TableCell>
                  <TableCell align="center">{t('admin.canView', 'View')}</TableCell>
                  <TableCell align="center">{t('admin.canEdit', 'Edit')}</TableCell>
                  <TableCell align="center">{t('admin.canAddRecords', 'Add Records')}</TableCell>
                  <TableCell align="center">{t('admin.canDelete', 'Delete')}</TableCell>
                </TableRow>
              </TableHead>
              <TableBody>
                {availableVehicles.map((vehicle) => {
                  const assignment = assignments.find(a => a.vehicleId === vehicle.id);
                  const isAssigned = !!assignment;
                  const isOwnVehicle = vehicle.ownerId === parseInt(id);

                  return (
                    <TableRow
                      key={vehicle.id}
                      sx={{
                        bgcolor: isOwnVehicle ? 'action.hover' : undefined,
                        opacity: isTargetAdmin ? 0.6 : 1,
                      }}
                    >
                      <TableCell padding="checkbox">
                        <Checkbox
                          checked={isAssigned}
                          onChange={() => handleAssignmentToggle(vehicle.id)}
                          disabled={isTargetAdmin}
                        />
                      </TableCell>
                      <TableCell>
                        <Typography variant="body2" fontWeight={isOwnVehicle ? 'bold' : 'normal'}>
                          {vehicle.name || `${vehicle.make} ${vehicle.model}`}
                          {isOwnVehicle && (
                            <Chip label={t('admin.owned', 'Owned')} size="small" color="info" sx={{ ml: 1 }} />
                          )}
                        </Typography>
                        <Typography variant="caption" color="text.secondary">
                          {vehicle.registrationNumber || ''} {vehicle.year ? `(${vehicle.year})` : ''}
                        </Typography>
                      </TableCell>
                      <TableCell>
                        <Typography variant="body2" color="text.secondary">
                          {vehicle.ownerName || '—'}
                        </Typography>
                      </TableCell>
                      <TableCell align="center">
                        <Checkbox
                          size="small"
                          checked={assignment?.canView ?? false}
                          onChange={(e) => handleAssignmentPermission(vehicle.id, 'canView', e.target.checked)}
                          disabled={!isAssigned || isTargetAdmin}
                        />
                      </TableCell>
                      <TableCell align="center">
                        <Checkbox
                          size="small"
                          checked={assignment?.canEdit ?? false}
                          onChange={(e) => handleAssignmentPermission(vehicle.id, 'canEdit', e.target.checked)}
                          disabled={!isAssigned || isTargetAdmin}
                        />
                      </TableCell>
                      <TableCell align="center">
                        <Checkbox
                          size="small"
                          checked={assignment?.canAddRecords ?? false}
                          onChange={(e) => handleAssignmentPermission(vehicle.id, 'canAddRecords', e.target.checked)}
                          disabled={!isAssigned || isTargetAdmin}
                        />
                      </TableCell>
                      <TableCell align="center">
                        <Checkbox
                          size="small"
                          checked={assignment?.canDelete ?? false}
                          onChange={(e) => handleAssignmentPermission(vehicle.id, 'canDelete', e.target.checked)}
                          disabled={!isAssigned || isTargetAdmin}
                        />
                      </TableCell>
                    </TableRow>
                  );
                })}
                {availableVehicles.length === 0 && (
                  <TableRow>
                    <TableCell colSpan={7} align="center">
                      {t('admin.noVehicles', 'No vehicles available')}
                    </TableCell>
                  </TableRow>
                )}
              </TableBody>
            </Table>
          </TableContainer>
        </Box>
      )}

      <Snackbar
        open={snack.open}
        autoHideDuration={4000}
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

export default AdminUserDetails;
