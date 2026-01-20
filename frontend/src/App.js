import React, { useState, useEffect } from 'react';
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import { ThemeProvider } from './contexts/ThemeContext';
import { AuthProvider, useAuth } from './contexts/AuthContext';
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
import RoadTax from './pages/RoadTax';
import ServiceRecords from './pages/ServiceRecords';
import Reports from './pages/Reports';
import Profile from './pages/Profile';
import PasswordChangeDialog from './components/PasswordChangeDialog';
import SessionTimeoutWarning from './components/SessionTimeoutWarning';
import { CircularProgress, Box } from '@mui/material';

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
  const [showPasswordChange, setShowPasswordChange] = useState(false);

  useEffect(() => {
    if (user && user.passwordChangeRequired) {
      setShowPasswordChange(true);
    } else {
      setShowPasswordChange(false);
    }
  }, [user]);

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
          <Route path="road-tax" element={<RoadTax />} />
          <Route path="service-records" element={<ServiceRecords />} />
          <Route path="reports" element={<Reports />} />
          <Route path="profile" element={<Profile />} />
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
      <ThemeProvider>
        <AuthProvider>
          <AppRoutes />
        </AuthProvider>
      </ThemeProvider>
    </BrowserRouter>
  );
}

export default App;
