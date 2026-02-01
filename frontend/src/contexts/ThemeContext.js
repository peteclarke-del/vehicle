import React, { createContext, useContext, useState, useEffect } from 'react';
import { ThemeProvider as MuiThemeProvider, createTheme } from '@mui/material/styles';
import CssBaseline from '@mui/material/CssBaseline';
import { useAuth } from './AuthContext';
import SafeStorage from '../utils/SafeStorage';

const ThemeContext = createContext();

export const useTheme = () => {
  const context = useContext(ThemeContext);
  if (!context) {
    throw new Error('useTheme must be used within ThemeProvider');
  }
  return context;
};

export const ThemeProvider = ({ children }) => {
  const { user, api } = useAuth();
  // initialize from localStorage, fallback to light
  const [mode, setMode] = useState(() => {
    const stored = SafeStorage.get('theme', 'light');
    return stored === 'dark' ? 'dark' : 'light';
  });

  // if user has a server preference, prefer that when available
  useEffect(() => {
    if (user && user.theme && user.theme !== mode) {
      setMode(user.theme);
    }
  }, [user]);

  // persist mode to server when it changes
  useEffect(() => {
    // persist to localStorage always so theme survives logout/reset
    SafeStorage.set('theme', mode);

    if (!api || !user) return;
    (async () => {
      try {
        await api.post('/user/preferences', { key: 'theme', value: mode });
      } catch (e) {
        // ignore failures; UI already updated optimistically
      }
    })();
  }, [mode, api, user]);

  const toggleTheme = () => setMode(prevMode => prevMode === 'light' ? 'dark' : 'light');

  const theme = createTheme({
    palette: {
      mode,
      primary: {
        main: mode === 'light' ? '#1976d2' : '#90caf9',
      },
      secondary: {
        main: mode === 'light' ? '#dc004e' : '#f48fb1',
      },
      background: {
        default: mode === 'light' ? '#f5f5f5' : '#121212',
        paper: mode === 'light' ? '#ffffff' : '#1e1e1e',
      },
    },
    typography: {
      fontFamily: '"Roboto", "Helvetica", "Arial", sans-serif',
    },
    components: {
      MuiCard: {
        styleOverrides: {
          root: {
            borderRadius: 8,
            boxShadow: mode === 'light' 
              ? '0 2px 4px rgba(0,0,0,0.1)' 
              : '0 2px 4px rgba(0,0,0,0.3)',
          },
        },
      },
      MuiButton: {
        styleOverrides: {
          root: {
            textTransform: 'none',
            borderRadius: 6,
          },
        },
      },
    },
  });

  return (
    <ThemeContext.Provider value={{ mode, toggleTheme }}>
      <MuiThemeProvider theme={theme}>
        <CssBaseline />
        {children}
      </MuiThemeProvider>
    </ThemeContext.Provider>
  );
};
