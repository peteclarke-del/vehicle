import React from 'react';
import { Outlet } from 'react-router-dom';
import {
  AppBar,
  Box,
  Button,
  Drawer,
  IconButton,
  List,
  ListItem,
  ListItemButton,
  ListItemIcon,
  ListItemText,
  Toolbar,
  Typography,
  useMediaQuery,
  useTheme as useMuiTheme,
} from '@mui/material';
import {
  Menu as MenuIcon,
  Dashboard as DashboardIcon,
  DirectionsCar,
  LocalGasStation,
  Build,
  Inventory,
  AccountCircle,
  Logout,
  Security,
  AssignmentTurnedIn,
  HomeRepairService,
  Assessment,
  AdminPanelSettings,
} from '@mui/icons-material';
import { PushPin, PushPinOutlined } from '@mui/icons-material';
import { DragIndicator } from '@mui/icons-material';
import {
  DndContext,
  closestCenter,
  PointerSensor,
  useSensor,
  useSensors,
} from '@dnd-kit/core';
import {
  arrayMove,
  SortableContext,
  verticalListSortingStrategy,
  useSortable,
} from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { ImportExport } from '@mui/icons-material';
import {
  LightMode,
  DarkMode,
} from '@mui/icons-material';
import { useNavigate, useLocation, Link } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';
import { useTheme } from '../contexts/ThemeContext';
import { usePermissions } from '../contexts/PermissionsContext';
import { useTranslation } from 'react-i18next';
import NotificationMenu from './NotificationMenu';
import { Tooltip, IconButton as MuiIconButton, Snackbar, Alert } from '@mui/material';
import PreferencesDialog from '../components/PreferencesDialog';
import { Settings as SettingsIcon } from '@mui/icons-material';
import { useNotifications } from '../hooks/useNotifications';
import logger from '../utils/logger';

const drawerWidth = 240;
const miniRailWidth = 48;

const Layout = () => {
  const [tempOpen, setTempOpen] = React.useState(false);
  const [drawerLocked, setDrawerLocked] = React.useState(false);
  const navigate = useNavigate();
  const { logout, user, updateProfile } = useAuth();
  const { api } = useAuth();
  const { mode, toggleTheme } = useTheme();
  const { t } = useTranslation();
  const muiTheme = useMuiTheme();
  const isMobile = useMediaQuery(muiTheme.breakpoints.down('sm'));
  const location = useLocation();
  const [preferencesOpen, setPreferencesOpen] = React.useState(false);
  const [snack, setSnack] = React.useState({ open: false, message: '', severity: 'error' });
  const { notifications, dismissNotification, snoozeNotification, clearAllNotifications } = useNotifications();
  const { isAdmin, can } = usePermissions();

  // Listen for global notifications dispatched from other components
  React.useEffect(() => {
    const handler = (ev) => {
      try {
        const d = ev && ev.detail ? ev.detail : null;
        if (d && d.message) {
          setSnack({ open: true, message: d.message, severity: d.severity || 'info' });
        }
      } catch (e) {
        // ignore
      }
    };
    window.addEventListener('app-notification', handler);
    return () => window.removeEventListener('app-notification', handler);
  }, []);

  const isActive = (path) => {
    if (!location || !location.pathname) return false;
    if (path === '/') return location.pathname === '/';
    return location.pathname.startsWith(path);
  };

  const defaultMenu = [
    { key: 'dashboard', text: t('nav.dashboard'), icon: <DashboardIcon />, path: '/', featureKey: 'dashboard.view' },
    { key: 'vehicles', text: t('nav.vehicles'), icon: <DirectionsCar />, path: '/vehicles', featureKey: 'vehicles.view' },
    { key: 'insurance', text: t('nav.insurance'), icon: <Security />, path: '/insurance', featureKey: 'insurance.view' },
    { key: 'policies', text: t('nav.policies', 'Policies'), icon: <Security />, path: '/policies', featureKey: 'insurance.view' },
    { key: 'roadTax', text: t('nav.roadTax'), icon: <AssignmentTurnedIn />, path: '/road-tax', featureKey: 'tax.view' },
    { key: 'motRecords', text: t('nav.motRecords'), icon: <AssignmentTurnedIn />, path: '/mot-records', featureKey: 'mot.view' },
    { key: 'serviceRecords', text: t('nav.serviceRecords'), icon: <HomeRepairService />, path: '/service-records', featureKey: 'services.view' },
    { key: 'parts', text: t('nav.parts'), icon: <Build />, path: '/parts', featureKey: 'parts.view' },
    { key: 'consumables', text: t('nav.consumables'), icon: <Inventory />, path: '/consumables', featureKey: 'consumables.view' },
    { key: 'fuel', text: t('nav.fuel'), icon: <LocalGasStation />, path: '/fuel', featureKey: 'fuel.view' },
    { key: 'importExport', text: t('nav.importExport') || 'Import / Export', icon: <ImportExport />, path: '/tools/import-export', featureKey: 'import_export.export' },
    { key: 'todo', text: t('nav.todo') || 'TODO', icon: <Assessment />, path: '/todo', featureKey: 'todos.view' },
    { key: 'reports', text: t('nav.reports'), icon: <Assessment />, path: '/reports', featureKey: 'reports.generate' },
    { key: 'profile', text: t('nav.profile'), icon: <AccountCircle />, path: '/profile' },
    ...(isAdmin ? [{ key: 'admin', text: t('nav.admin', 'Admin'), icon: <AdminPanelSettings />, path: '/admin/users' }] : []),
  ].filter(item => !item.featureKey || can(item.featureKey));

  const [menuItems, setMenuItems] = React.useState(defaultMenu);

  // Rebuild menu items when language changes so labels update while preserving order
  React.useEffect(() => {
    setMenuItems((prev) => {
      const byKey = {};
      defaultMenu.forEach((m) => { byKey[m.key] = m; byKey[m.path] = m; });
      if (prev && prev.length) {
        return prev.map((p) => {
          const found = byKey[p.key] || byKey[p.path];
          return found ? { ...found } : p;
        });
      }
      return defaultMenu;
    });
  }, [t]);

  const sensors = useSensors(
    useSensor(PointerSensor, { activationConstraint: { distance: 6 } }),
  );

  function SortableItem({ item, index }) {
    const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({ id: item.key });
    const style = {
      transform: CSS.Transform.toString(transform),
      transition,
      zIndex: isDragging ? 1400 : 'auto',
      opacity: isDragging ? 0.98 : 1,
      boxShadow: isDragging ? undefined : undefined,
    };

    return (
      <ListItem ref={setNodeRef} key={item.key} disablePadding style={style}>
        <ListItemButton
          onClick={() => handleNavigation(item.path)}
          selected={isActive(item.path)}
          sx={{ display: 'flex', alignItems: 'center' }}
        >
          <ListItemIcon>{item.icon}</ListItemIcon>
          <ListItemText primary={item.text} />
          <Box sx={{ flexGrow: 1 }} />
          <MuiIconButton
            size="small"
            edge="end"
            aria-label={`drag-handle-${item.key}`}
            sx={{ cursor: 'grab', color: 'text.secondary', opacity: 0.65, '&:hover': { opacity: 0.9 } }}
            {...attributes}
            {...listeners}
            onMouseDown={(e) => { e.stopPropagation(); }}
          >
            <DragIndicator fontSize="small" />
          </MuiIconButton>
        </ListItemButton>
      </ListItem>
    );
  }

  React.useEffect(() => {
    let mounted = true;
    (async () => {
      if (!user || !api) return;
      try {
        try {
          const lockResp = await api.get('/user/preferences?key=nav.drawerLocked');
          if (lockResp?.data && typeof lockResp.data.value !== 'undefined') {
            setDrawerLocked(Boolean(lockResp.data.value));
          } 
        } catch (e) {
          // ignore drawer lock failures
        }

        // fetch saved menu order explicitly
        try {
          const orderResp = await api.get('/user/preferences?key=nav.menuOrder');
          const orderVal = orderResp?.data?.value ?? null;
          if (mounted && orderVal && Array.isArray(orderVal)) {
            const byKey = {};
            defaultMenu.forEach((m) => { byKey[m.path] = m; byKey[m.key] = m; });
            const ordered = [];
            orderVal.forEach((o) => {
              const found = byKey[o];
              if (found) ordered.push(found);
            });
            // Append any missing items
            defaultMenu.forEach((m) => { if (!ordered.includes(m)) ordered.push(m); });
            setMenuItems(ordered);
          }
        } catch (e) {
          // ignore order fetch failures
        }
      } catch (err) {
        // ignore preference load failures
      }
    })();
    return () => { mounted = false; };
  }, [user, api, t]);

  const handleDrawerToggle = () => {
    // toggle temporary drawer (mobile or desktop) - pin/unpin is controlled by the pin button
    setTempOpen((s) => !s);
  };

  const handleNavigation = (path) => {
    navigate(path);
    if (isMobile || !drawerLocked) {
      setTempOpen(false);
    }
  };

  const saveMenuOrder = async (items) => {
    if (!user || !api) return;
    try {
      const order = items.map((i) => i.path || i.key);
      await api.post('/user/preferences', { key: 'nav.menuOrder', value: order });
    } catch (err) {
      // show non-blocking feedback
      setSnack({ open: true, message: t('errors.failedToSavePreferences') || 'Failed to save menu order', severity: 'error' });
    }
  };

  const drawer = (
    <Box>
      <Toolbar>
          <Typography variant="h6" noWrap component="div" sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
          {t('nav.navigation')}
          <Box sx={{ flexGrow: 1 }} />
          <IconButton
            size="small"
            aria-label={drawerLocked ? t('nav.unpin') : t('nav.pin')}
            onClick={(e) => {
              e.stopPropagation();
              const newLocked = !drawerLocked;
              setDrawerLocked(newLocked);
              setTempOpen(false);
              if (user && api) api.post('/user/preferences', { key: 'nav.drawerLocked', value: newLocked }).catch((err) => logger.warn('Failed to save drawer lock preference:', err));
            }}
          >
            {drawerLocked ? <PushPin fontSize="small" /> : <PushPinOutlined fontSize="small" />}
          </IconButton>
        </Typography>
      </Toolbar>
      <List>
      <DndContext
        sensors={sensors}
        collisionDetection={closestCenter}
        onDragEnd={(event) => {
          const { active, over } = event;
          if (!over) return;
          const oldIndex = menuItems.findIndex((m) => m.key === active.id);
          const newIndex = menuItems.findIndex((m) => m.key === over.id);
          if (oldIndex !== -1 && newIndex !== -1 && oldIndex !== newIndex) {
            const newItems = arrayMove(menuItems, oldIndex, newIndex);
            setMenuItems(newItems);
            saveMenuOrder(newItems);
          }
        }}
      >
        <SortableContext items={menuItems.map((m) => m.key)} strategy={verticalListSortingStrategy}>
          {menuItems.map((item, idx) => (
            <SortableItem key={item.key} item={item} index={idx} />
          ))}
        </SortableContext>
      </DndContext>
        <ListItem disablePadding>
          <ListItemButton onClick={logout}>
            <ListItemIcon><Logout /></ListItemIcon>
            <ListItemText primary={t('auth.logout')} />
          </ListItemButton>
        </ListItem>
      </List>
    </Box>
  );

  return (
    <Box sx={{ display: 'flex' }}>
      <AppBar
        position="fixed"
        sx={{
          width: { 
            xs: '100%',
            sm: drawerLocked 
              ? `calc(100% - ${drawerWidth}px)` 
              : `calc(100% - ${miniRailWidth}px)` 
          },
          ml: { 
            xs: 0,
            sm: drawerLocked 
              ? `${drawerWidth}px` 
              : `${miniRailWidth}px` 
          },
        }}
      >
        <Toolbar>
          {(!drawerLocked && isMobile) && (
            <IconButton
              color="inherit"
              edge="start"
              onClick={handleDrawerToggle}
              sx={{ mr: 2 }}
            >
              <MenuIcon />
            </IconButton>
          )}
          <Box sx={{ display: 'flex', alignItems: 'center', flexGrow: 1 }}>
            <Typography
              variant="h6"
              noWrap
              component={Link}
              to="/"
              sx={{ textAlign: 'left', textDecoration: 'none', color: 'inherit' }}
            >
              {t('app.title')}
            </Typography>
          </Box>

          <NotificationMenu
            notifications={notifications}
            dismissNotification={dismissNotification}
            snoozeNotification={snoozeNotification}
            clearAllNotifications={clearAllNotifications}
          />
          <IconButton
            color="inherit"
            onClick={async () => {
              const newMode = mode === 'light' ? 'dark' : 'light';
              try {
                toggleTheme();
                if (user && api) {
                  // best-effort persist; don't block UI
                  await api.post('/user/preferences', { key: 'theme', value: newMode });
                }
              } catch (err) {
                // show UI feedback
                setSnack({ open: true, message: t('errors.failedToSavePreferences') || 'Failed to persist theme preference', severity: 'error' });
                // eslint-disable-next-line no-console
                logger.warn('Failed to persist theme preference', err);
              }
            }}
          >
            {mode === 'dark' ? <LightMode /> : <DarkMode />}
          </IconButton>
          <Tooltip title={t('preferences.title')}>
            <IconButton color="inherit" onClick={() => setPreferencesOpen(true)}>
              <SettingsIcon />
            </IconButton>
          </Tooltip>
          <PreferencesDialog open={preferencesOpen} onClose={() => setPreferencesOpen(false)} />
          <Snackbar
            open={snack.open}
            autoHideDuration={4000}
            onClose={() => setSnack((s) => ({ ...s, open: false }))}
            anchorOrigin={{ vertical: 'bottom', horizontal: 'center' }}
          >
            <Alert onClose={() => setSnack((s) => ({ ...s, open: false }))} severity={snack.severity} sx={{ width: '100%' }}>
              {snack.message}
            </Alert>
          </Snackbar>
        </Toolbar>
      </AppBar>
      <Box
        component="nav"
        sx={{ width: { sm: drawerLocked ? drawerWidth : 0 }, flexShrink: { sm: 0 } }}
      >
        {/* Temporary drawer used for mobile and when not pinned on desktop */}
        <Drawer
          variant="temporary"
          open={tempOpen}
          onClose={() => setTempOpen(false)}
          ModalProps={{ keepMounted: true }}
          sx={{
            display: { xs: 'block', sm: drawerLocked ? 'none' : 'block' },
            '& .MuiDrawer-paper': { 
              boxSizing: 'border-box', 
              width: drawerWidth,
              background: `
                linear-gradient(90deg, rgba(92,33,33,0.04) 0%, rgba(92,33,33,0) 50%, rgba(92,33,33,0.04) 100%),
                linear-gradient(0deg, rgba(139,35,10,0.06) 0%, rgba(139,35,10,0) 100%),
                repeating-linear-gradient(
                  90deg,
                  rgba(160,50,10,0.12) 0px,
                  rgba(192,64,0,0.08) 35px,
                  rgba(139,35,10,0.10) 70px,
                  rgba(120,30,10,0.08) 105px
                ),
                ${mode === 'dark' ? '#1e1e1e' : '#f5f5f5'}
              `,
              backdropFilter: 'brightness(0.98)',
            },
          }}
        >
          {drawer}
        </Drawer>
        {/* Persistent/pinned drawer on desktop */}
        {drawerLocked && (
          <Drawer
            variant="persistent"
            open
            onClose={() => { setDrawerLocked(false); if (user && api) api.post('/user/preferences', { key: 'nav.drawerLocked', value: false }).catch((err) => logger.warn('Failed to save drawer unlock preference:', err)); }}
            sx={{
              display: { xs: 'none', sm: 'block' },
              '& .MuiDrawer-paper': { 
                boxSizing: 'border-box', 
                width: drawerWidth,
                background: `
                  linear-gradient(90deg, rgba(92,33,33,0.04) 0%, rgba(92,33,33,0) 50%, rgba(92,33,33,0.04) 100%),
                  linear-gradient(0deg, rgba(139,35,10,0.06) 0%, rgba(139,35,10,0) 100%),
                  repeating-linear-gradient(
                    90deg,
                    rgba(160,50,10,0.12) 0px,
                    rgba(192,64,0,0.08) 35px,
                    rgba(139,35,10,0.10) 70px,
                    rgba(120,30,10,0.08) 105px
                  ),
                  ${mode === 'dark' ? '#1e1e1e' : '#f5f5f5'}
                `,
                backdropFilter: 'brightness(0.98)',
              },
            }}
          >
            {drawer}
          </Drawer>
        )}
      </Box>
      {/* overlay to dim background when temporary drawer is open */}
      {tempOpen && (
        <Box
          onClick={() => setTempOpen(false)}
          sx={{
            position: 'fixed',
            top: 0,
            left: 0,
            width: '100%',
            height: '100%',
            backgroundColor: 'rgba(0,0,0,0.45)',
            zIndex: (muiTheme && muiTheme.zIndex && muiTheme.zIndex.drawer) ? muiTheme.zIndex.drawer - 1 : 1200,
          }}
        />
      )}
      {/* Mini navigation rail when drawer is collapsed (desktop only) */}
      {!drawerLocked && !isMobile && (
        <Box
          sx={{
            position: 'fixed',
            left: 0,
            top: 0,
            bottom: 0,
            width: miniRailWidth,
            backgroundColor: mode === 'dark' ? 'rgba(30,30,30,0.95)' : 'rgba(250,250,250,0.95)',
            borderRight: 1,
            borderColor: 'divider',
            display: 'flex',
            flexDirection: 'column',
            alignItems: 'center',
            zIndex: (muiTheme && muiTheme.zIndex && muiTheme.zIndex.appBar) ? muiTheme.zIndex.appBar + 1 : 1201,
            overflowY: 'auto',
            overflowX: 'hidden',
            '&::-webkit-scrollbar': { width: 4 },
            '&::-webkit-scrollbar-thumb': { backgroundColor: 'rgba(128,128,128,0.3)', borderRadius: 2 },
          }}
        >
          {/* Menu button aligned with AppBar header */}
          <Box
            sx={{
              height: 64, // AppBar height
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'center',
              width: '100%',
              borderBottom: 1,
              borderColor: 'divider',
            }}
          >
            <Tooltip title={t('nav.navigation')} placement="right" arrow>
              <IconButton
                onClick={handleDrawerToggle}
                sx={{
                  color: 'text.secondary',
                  '&:hover': {
                    backgroundColor: 'action.hover',
                  },
                }}
              >
                <MenuIcon />
              </IconButton>
            </Tooltip>
          </Box>
          {/* Nav items below the header */}
          <Box sx={{ py: 1, display: 'flex', flexDirection: 'column', alignItems: 'center' }}>
            {/* Show nav items in user's custom order, excluding profile and import/export */}
            {menuItems.filter(item => !['profile', 'importExport'].includes(item.key)).map((item) => (
              <Tooltip key={item.key} title={item.text} placement="right" arrow>
                <IconButton
                  onClick={() => navigate(item.path)}
                  sx={{
                    my: 0.5,
                    color: isActive(item.path) ? 'primary.main' : 'text.secondary',
                    backgroundColor: isActive(item.path) ? 'action.selected' : 'transparent',
                    '&:hover': {
                      backgroundColor: 'action.hover',
                    },
                  }}
                >
                  {item.icon}
                </IconButton>
              </Tooltip>
            ))}
          </Box>
        </Box>
      )}
      <Box
        component="main"
        sx={{
          flexGrow: 1,
          p: 3,
          width: { sm: drawerLocked ? `calc(100% - ${drawerWidth}px)` : '100%' },
          ml: { sm: !drawerLocked && !isMobile ? `${miniRailWidth}px` : 0 },
        }}
      >
        <Toolbar />
        <Outlet />
      </Box>
    </Box>
  );
};

export default Layout;
