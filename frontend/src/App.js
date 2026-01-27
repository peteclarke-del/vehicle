import React, { useState, useEffect } from 'react';
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import { ThemeProvider } from './contexts/ThemeContext';
import { AuthProvider, useAuth } from './contexts/AuthContext';
import { UserPreferencesProvider } from './contexts/UserPreferencesContext';
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
import { CircularProgress, Box } from '@mui/material';
import i18next from 'i18next';

const PrivateRoute = ({ children }) => {
  const { user, loading } = useAuth();

  if (loading) {
    return (
      <Box display="flex" justifyContent="center" alignItems="center" minHeight="100vh">
        <CircularProgress />
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

  useEffect(() => {
    let mounted = true;
    const runCheck = async () => {
      try {
        const resp = await api.get('/system-check');
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
    return () => { mounted = false; };
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
        <CircularProgress />
        <Box mt={2}>{i18next.t('app.systemChecks')}</Box>
      </Box>
    );
  }

  return (
    <>
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
            <AppRoutes />
          </UserPreferencesProvider>
        </ThemeProvider>
      </AuthProvider>
    </BrowserRouter>
  );
}

export default App;
