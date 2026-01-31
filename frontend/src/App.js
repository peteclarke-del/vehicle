import React, { useState, useEffect } from 'react';
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import { ThemeProvider } from './contexts/ThemeContext';
import { AuthProvider, useAuth } from './contexts/AuthContext';
import { UserPreferencesProvider } from './contexts/UserPreferencesContext';
import { VehiclesProvider } from './contexts/VehiclesContext';
import Layout from './components/Layout';
import Login from './pages/Login';
import Register from './pages/Register';
import Dashboard from './pages/Dashboard';
import Vehicles from './pages/Vehicles';
import VehicleDetails from './pages/VehicleDetails';
import FuelRecords from './pages/FuelRecords';
import Parts from './pages/Parts';
import Consumables from './pages/Consumables';
import Insurance from './pages/Insurance';
import MotRecords from './pages/MotRecords';
import ServiceRecords from './pages/ServiceRecords';
import RoadTax from './pages/RoadTax';
import Profile from './pages/Profile';
import Todo from './pages/Todo';
import Reports from './pages/Reports';
import PasswordChangeDialog from './components/PasswordChangeDialog';
import SessionTimeoutWarning from './components/SessionTimeoutWarning';
import ImportExport from './pages/ImportExport';
import { Box } from '@mui/material';
import { useTranslation } from 'react-i18next';
import KnightRiderLoader from './components/KnightRiderLoader';
import ErrorBoundary from './components/ErrorBoundary';

const PrivateRoute = ({ children }) => {
  const { user, loading } = useAuth();

  if (loading) {
    return (
      <Box display="flex" justifyContent="center" alignItems="center" minHeight="100vh">
        <KnightRiderLoader size={32} />
      </Box>
    );
  }

  return user ? children : <Navigate to="/login" />;
};

function AppRoutes() {
  const { user } = useAuth();
  const { api } = useAuth();
  const [showPasswordChange, setShowPasswordChange] = useState(false);
  const [systemStatus, setSystemStatus] = useState(null);
  const [checkingSystem, setCheckingSystem] = useState(true);
  const { t } = useTranslation();

  useEffect(() => {
    let mounted = true;
    let timeoutId;
    const runCheck = async () => {
      try {
        const resp = await Promise.race([
          api.get('/system-check'),
          new Promise((_, reject) => {
            timeoutId = setTimeout(() => reject(new Error('System check timed out')), 6000);
          }),
        ]);
        if (!mounted) return;
        setSystemStatus(resp.data);
      } catch (err) {
        if (!mounted) return;
        setSystemStatus(err.response?.data || { error: 'System check failed' });
      } finally {
        if (mounted) setCheckingSystem(false);
      }
    };
    runCheck();
    return () => {
      mounted = false;
      if (timeoutId) clearTimeout(timeoutId);
    };
  }, [api]);

  useEffect(() => {
    if (user && user.passwordChangeRequired) {
      setShowPasswordChange(true);
    } else {
      setShowPasswordChange(false);
    }
  }, [user]);

  if (checkingSystem) {
    return (
      <Box display="flex" flexDirection="column" alignItems="center" justifyContent="center" minHeight="100vh">
        <KnightRiderLoader size={32} />
        <Box mt={2}>{t('app.systemChecks')}</Box>
      </Box>
    );
  }

  return (
    <>
      <ErrorBoundary>
        <Routes>
          <Route path="/login" element={<Login />} />
          <Route path="/register" element={<Register />} />
          <Route
            path="/"
            element={
              <PrivateRoute>
                <Layout />
              </PrivateRoute>
            }
          >
            <Route index element={<Dashboard />} />
            <Route path="vehicles" element={<Vehicles />} />
            <Route path="vehicles/:id" element={<VehicleDetails />} />
            <Route path="fuel" element={<FuelRecords />} />
            <Route path="parts" element={<Parts />} />
            <Route path="consumables" element={<Consumables />} />
            <Route path="insurance" element={<Insurance />} />
            <Route path="mot-records" element={<MotRecords />} />
            <Route path="service-records" element={<ServiceRecords />} />
            <Route path="road-tax" element={<RoadTax />} />
            <Route path="todo" element={<Todo />} />
            <Route path="reports" element={<Reports />} />
            <Route path="profile" element={<Profile />} />
            <Route path="tools/import-export" element={<ImportExport />} />
          </Route>
        </Routes>
      </ErrorBoundary>
      <PasswordChangeDialog 
        open={showPasswordChange} 
        onClose={() => {}} 
        required={true} 
      />
      {user && <SessionTimeoutWarning />}
    </>
  );
}

function App() {
  return (
    <BrowserRouter>
      <AuthProvider>
        <ThemeProvider>
          <UserPreferencesProvider>
            <VehiclesProvider>
              <AppRoutes />
            </VehiclesProvider>
          </UserPreferencesProvider>
        </ThemeProvider>
      </AuthProvider>
    </BrowserRouter>
  );
}

export default App;
