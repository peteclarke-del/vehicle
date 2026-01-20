import React from 'react';
import { Outlet } from 'react-router-dom';
import {
  AppBar,
  Box,
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
  Brightness4,
  Brightness7,
  Security,
  AssignmentTurnedIn,
  HomeRepairService,
  Assessment,
} from '@mui/icons-material';
import { useNavigate, useLocation } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';
import { useTheme } from '../contexts/ThemeContext';
import { useTranslation } from 'react-i18next';
import NotificationMenu from './NotificationMenu';
import { Tooltip, IconButton as MuiIconButton } from '@mui/material';
import { getAvailableLanguages } from '../i18n';

const drawerWidth = 240;

const Layout = () => {
  const [mobileOpen, setMobileOpen] = React.useState(false);
  const navigate = useNavigate();
  const { logout, user, updateProfile } = useAuth();
  const { mode, toggleTheme } = useTheme();
  const { t, i18n } = useTranslation();
  const muiTheme = useMuiTheme();
  const isMobile = useMediaQuery(muiTheme.breakpoints.down('sm'));
  const location = useLocation();
  const [languages, setLanguages] = React.useState([]);

  React.useEffect(() => {
    let mounted = true;
    getAvailableLanguages().then((langs) => {
      if (mounted) setLanguages(langs);
    }).catch(() => {
      setLanguages([
        { code: 'en', nativeName: 'English' },
        { code: 'es', nativeName: 'Español' },
        { code: 'fr', nativeName: 'Français' },
      ]);
    });
    return () => { mounted = false; };
  }, []);

  const currentLangBase = (i18n.language || '').split('-')[0];

  const isActive = (path) => {
    if (!location || !location.pathname) return false;
    if (path === '/') return location.pathname === '/';
    return location.pathname.startsWith(path);
  };

  const menuItems = [
    { text: t('nav.dashboard'), icon: <DashboardIcon />, path: '/' },
    { text: t('nav.vehicles'), icon: <DirectionsCar />, path: '/vehicles' },
    { text: t('nav.fuel'), icon: <LocalGasStation />, path: '/fuel' },
    { text: t('nav.parts'), icon: <Build />, path: '/parts' },
    { text: t('nav.consumables'), icon: <Inventory />, path: '/consumables' },
    { text: t('nav.insurance'), icon: <Security />, path: '/insurance' },
    { text: t('nav.motRecords'), icon: <AssignmentTurnedIn />, path: '/mot-records' },
    { text: t('nav.roadTax'), icon: <AssignmentTurnedIn />, path: '/road-tax' },
    { text: t('nav.serviceRecords'), icon: <HomeRepairService />, path: '/service-records' },
    { text: t('nav.reports'), icon: <Assessment />, path: '/reports' },
    { text: t('nav.profile'), icon: <AccountCircle />, path: '/profile' },
  ];

  const handleDrawerToggle = () => {
    setMobileOpen(!mobileOpen);
  };

  const handleNavigation = (path) => {
    navigate(path);
    if (isMobile) {
      setMobileOpen(false);
    }
  };

  const drawer = (
    <Box>
      <Toolbar>
        <Typography variant="h6" noWrap component="div">
          Vehicle Manager
        </Typography>
      </Toolbar>
      <List>
        {menuItems.map((item) => (
          <ListItem key={item.text} disablePadding>
            <ListItemButton
              onClick={() => handleNavigation(item.path)}
              selected={isActive(item.path)}
              sx={{
                '&:hover': { backgroundColor: (theme) => theme.palette.action.hover },
                '&.Mui-selected': {
                  backgroundColor: (theme) => theme.palette.action.selected,
                  '& .MuiListItemIcon-root, & .MuiListItemText-primary': {
                    color: (theme) => theme.palette.primary.main,
                    fontWeight: 700,
                  },
                },
              }}
            >
              <ListItemIcon>{item.icon}</ListItemIcon>
              <ListItemText primary={item.text} />
            </ListItemButton>
          </ListItem>
        ))}
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
          width: { sm: `calc(100% - ${drawerWidth}px)` },
          ml: { sm: `${drawerWidth}px` },
        }}
      >
        <Toolbar>
          <IconButton
            color="inherit"
            edge="start"
            onClick={handleDrawerToggle}
            sx={{ mr: 2, display: { sm: 'none' } }}
          >
            <MenuIcon />
          </IconButton>
          <Box sx={{ display: 'flex', alignItems: 'center', flexGrow: 1 }}>
            <Typography variant="h6" noWrap component="div" sx={{ textAlign: 'left' }}>
              Vehicle Management System
            </Typography>
          </Box>

          <Box sx={{ position: 'absolute', left: '50%', transform: 'translateX(-50%)', display: 'flex', gap: 1, zIndex: 2000 }}>
            {languages.map((lang) => {
              const selected = currentLangBase === lang.code;
              const imgStyle = {
                width: 22,
                height: 16,
                display: 'block',
                borderRadius: 3,
                boxShadow: selected ? '0 0 0 3px rgba(25,118,210,0.24)' : 'none',
              };

              return (
                <Tooltip key={lang.code} title={lang.nativeName} arrow>
                  <MuiIconButton
                    size="small"
                    color="inherit"
                    aria-label={`Change language to ${lang.nativeName}`}
                    onClick={async () => {
                      // eslint-disable-next-line no-console
                      console.log('Language flag clicked:', lang.code, 'current=', i18n.language);
                      try {
                        const url = `/locales/${lang.code}/translation.json`;
                        const resp = await fetch(url, { cache: 'no-store' });
                        if (resp.ok) {
                          const bundle = await resp.json();
                          i18n.addResourceBundle(lang.code, 'translation', bundle, true, true);
                          // eslint-disable-next-line no-console
                          console.log('Added/updated resource bundle for', lang.code);
                        } else {
                          // eslint-disable-next-line no-console
                          console.warn('Locale file not found at', url, 'status', resp.status);
                        }

                        await i18n.changeLanguage(lang.code);
                        // Persist choice to user profile when logged in
                        if (user && updateProfile) {
                          try {
                            await updateProfile({ language: lang.code });
                          } catch (err) {
                            // eslint-disable-next-line no-console
                            console.warn('Failed to persist language preference', err);
                          }
                        }
                        // eslint-disable-next-line no-console
                        console.log('Language changed to', i18n.language);
                      } catch (err) {
                        // eslint-disable-next-line no-console
                        console.error('Language switch error', err);
                      }
                    }}
                  >
                    <img src={`/locales/${lang.code}/flag.svg`} alt={lang.nativeName} style={imgStyle} />
                  </MuiIconButton>
                </Tooltip>
              );
            })}
          </Box>
          <NotificationMenu />
          <IconButton color="inherit" onClick={toggleTheme}>
            {mode === 'dark' ? <Brightness7 /> : <Brightness4 />}
          </IconButton>
        </Toolbar>
      </AppBar>
      <Box
        component="nav"
        sx={{ width: { sm: drawerWidth }, flexShrink: { sm: 0 } }}
      >
        <Drawer
          variant="temporary"
          open={mobileOpen}
          onClose={handleDrawerToggle}
          ModalProps={{ keepMounted: true }}
          sx={{
            display: { xs: 'block', sm: 'none' },
            '& .MuiDrawer-paper': { boxSizing: 'border-box', width: drawerWidth },
          }}
        >
          {drawer}
        </Drawer>
        <Drawer
          variant="permanent"
          sx={{
            display: { xs: 'none', sm: 'block' },
            '& .MuiDrawer-paper': { boxSizing: 'border-box', width: drawerWidth },
          }}
          open
        >
          {drawer}
        </Drawer>
      </Box>
      <Box
        component="main"
        sx={{
          flexGrow: 1,
          p: 3,
          width: { sm: `calc(100% - ${drawerWidth}px)` },
        }}
      >
        <Toolbar />
        <Outlet />
      </Box>
    </Box>
  );
};

export default Layout;
