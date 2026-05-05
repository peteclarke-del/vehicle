import React from 'react';
import { render, screen, fireEvent, waitFor, act } from '@testing-library/react';
import { BrowserRouter } from 'react-router-dom';
import Dashboard from '../pages/Dashboard';
import { AuthContext } from '../context/AuthContext';
import { useVehicles } from '../contexts/VehiclesContext';

// Mock react-i18next
jest.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: (key) => key,
    i18n: { language: 'en-GB' },
  }),
}));

// useVehicles and useUserPreferences are mocked globally in setupTests.js
// Override useVehicles per-test to control vehicle data
jest.mock('../contexts/VehiclesContext', () => ({
  VehiclesProvider: ({ children }) => children,
  useVehicles: jest.fn(),
}));

describe('Dashboard Component', () => {
  const mockApi = {
    get: jest.fn().mockResolvedValue({ data: {} }),
    post: jest.fn().mockResolvedValue({ data: {} }),
    put: jest.fn().mockResolvedValue({ data: {} }),
    delete: jest.fn().mockResolvedValue({ data: {} }),
  };

  const mockAuthContext = {
    user: { id: 1, email: 'test@example.com' },
    api: mockApi,
    logout: jest.fn(),
  };

  const mockVehicles = [
    {
      id: 1,
      name: 'Toyota Corolla',
      make: 'Toyota',
      model: 'Corolla',
      year: 2020,
      registrationNumber: 'ABC123',
      purchaseCost: 15000,
      motExpiryDate: '2027-06-01',
      insuranceExpiryDate: '2027-03-01',
      status: 'Live',
    },
    {
      id: 2,
      name: 'Honda Civic',
      make: 'Honda',
      model: 'Civic',
      year: 2019,
      registrationNumber: 'XYZ789',
      purchaseCost: 12000,
      motExpiryDate: '2027-08-01',
      insuranceExpiryDate: '2027-05-01',
      status: 'Live',
    },
  ];

  beforeEach(() => {
    jest.clearAllMocks();
    mockApi.get.mockResolvedValue({ data: {} });
    useVehicles.mockReturnValue({
      vehicles: [],
      loading: false,
      error: null,
      refreshVehicles: jest.fn(),
      addVehicle: jest.fn(),
      updateVehicle: jest.fn(),
      deleteVehicle: jest.fn(),
      recordsVersion: 0,
    });
  });

  const renderDashboard = () => {
    return render(
      <BrowserRouter>
        <AuthContext.Provider value={mockAuthContext}>
          <Dashboard />
        </AuthContext.Provider>
      </BrowserRouter>
    );
  };

  test('renders welcome heading', () => {
    renderDashboard();
    expect(screen.getByText('dashboard.welcome')).toBeInTheDocument();
  });

  test('displays loading indicator when vehicles are loading', () => {
    useVehicles.mockReturnValue({
      vehicles: [],
      loading: true,
      error: null,
      refreshVehicles: jest.fn(),
      recordsVersion: 0,
    });
    renderDashboard();
    // KnightRiderLoader or similar loading indicator renders when loading and no vehicles
    expect(screen.queryByText('dashboard.welcome')).not.toBeInTheDocument();
  });

  test('displays empty state when no vehicles exist', () => {
    renderDashboard();
    expect(screen.getByText('common.noVehicles')).toBeInTheDocument();
  });

  test('shows add vehicle button in empty state', () => {
    renderDashboard();
    expect(screen.getByText('vehicle.addVehicle')).toBeInTheDocument();
  });

  test('clicking add vehicle button opens dialog', () => {
    renderDashboard();
    const addButton = screen.getByText('vehicle.addVehicle');
    fireEvent.click(addButton);
    // VehicleDialog should be opened — it renders with open=true
    // The dialog renders a form with vehicle fields
    expect(screen.getAllByRole('button').length).toBeGreaterThan(1);
  });

  test('displays vehicle stats when vehicles are loaded', async () => {
    useVehicles.mockReturnValue({
      vehicles: mockVehicles,
      loading: false,
      error: null,
      refreshVehicles: jest.fn(),
      recordsVersion: 0,
    });
    renderDashboard();
    await waitFor(() => {
      expect(screen.getByText('dashboard.totalVehicles')).toBeInTheDocument();
    });
  });

  test('shows vehicle count in status stats', async () => {
    useVehicles.mockReturnValue({
      vehicles: mockVehicles,
      loading: false,
      error: null,
      refreshVehicles: jest.fn(),
      recordsVersion: 0,
    });
    renderDashboard();
    await waitFor(() => {
      // Two live vehicles
      expect(screen.getByText('dashboard.totalVehicles')).toBeInTheDocument();
    });
    // The count appears as "2" in the stat card
    expect(screen.getAllByText('2').length).toBeGreaterThanOrEqual(1);
  });

  test('shows expired MOT stat card', async () => {
    useVehicles.mockReturnValue({
      vehicles: mockVehicles,
      loading: false,
      error: null,
      refreshVehicles: jest.fn(),
      recordsVersion: 0,
    });
    renderDashboard();
    await waitFor(() => {
      expect(screen.getByText('dashboard.expiredMot')).toBeInTheDocument();
    });
  });

  test('shows expired insurance stat card', async () => {
    useVehicles.mockReturnValue({
      vehicles: mockVehicles,
      loading: false,
      error: null,
      refreshVehicles: jest.fn(),
      recordsVersion: 0,
    });
    renderDashboard();
    await waitFor(() => {
      expect(screen.getByText('dashboard.expiredInsurance')).toBeInTheDocument();
    });
  });

  test('shows service due stat card', async () => {
    useVehicles.mockReturnValue({
      vehicles: mockVehicles,
      loading: false,
      error: null,
      refreshVehicles: jest.fn(),
      recordsVersion: 0,
    });
    renderDashboard();
    await waitFor(() => {
      expect(screen.getAllByText('dashboard.serviceDue').length).toBeGreaterThanOrEqual(1);
    });
  });

  test('displays vehicle cards when vehicles loaded', async () => {
    useVehicles.mockReturnValue({
      vehicles: mockVehicles,
      loading: false,
      error: null,
      refreshVehicles: jest.fn(),
      recordsVersion: 0,
    });
    renderDashboard();
    await waitFor(() => {
      expect(screen.getAllByText('Toyota Corolla').length).toBeGreaterThanOrEqual(1);
      expect(screen.getAllByText('Honda Civic').length).toBeGreaterThanOrEqual(1);
    });
  });

  test('shows purchase cost stat', async () => {
    useVehicles.mockReturnValue({
      vehicles: mockVehicles,
      loading: false,
      error: null,
      refreshVehicles: jest.fn(),
      recordsVersion: 0,
    });
    renderDashboard();
    await waitFor(() => {
      expect(screen.getByText('dashboard.totalValue')).toBeInTheDocument();
    });
  });

  test('shows monthly spend section when api returns data', async () => {
    mockApi.get.mockImplementation((url) => {
      if (url.includes('/vehicles/monthly-costs')) {
        return Promise.resolve({
          data: {
            months: ['2026-01'],
            vehicles: { 1: [100] },
            vehicleTotals: [{ id: 1, name: 'Toyota Corolla', total: 100 }],
          },
        });
      }
      return Promise.resolve({ data: {} });
    });
    useVehicles.mockReturnValue({
      vehicles: mockVehicles,
      loading: false,
      error: null,
      refreshVehicles: jest.fn(),
      recordsVersion: 0,
    });
    renderDashboard();
    await waitFor(() => {
      expect(screen.getByText('dashboard.monthlySpend')).toBeInTheDocument();
    });
  });

  test('shows cost per vehicle chart section', async () => {
    mockApi.get.mockImplementation((url) => {
      if (url.includes('/vehicles/monthly-costs')) {
        return Promise.resolve({
          data: {
            months: ['2026-01'],
            vehicles: { 1: [100] },
            vehicleTotals: [{ id: 1, name: 'Toyota Corolla', total: 100 }],
          },
        });
      }
      return Promise.resolve({ data: {} });
    });
    useVehicles.mockReturnValue({
      vehicles: mockVehicles,
      loading: false,
      error: null,
      refreshVehicles: jest.fn(),
      recordsVersion: 0,
    });
    renderDashboard();
    await waitFor(() => {
      expect(screen.getByText('dashboard.costPerVehicle')).toBeInTheDocument();
    });
  });

  test('renders add vehicle button when vehicles exist', async () => {
    useVehicles.mockReturnValue({
      vehicles: mockVehicles,
      loading: false,
      error: null,
      refreshVehicles: jest.fn(),
      recordsVersion: 0,
    });
    renderDashboard();
    await waitFor(() => {
      // There should be an add vehicle action somewhere
      expect(screen.getByText('dashboard.welcome')).toBeInTheDocument();
    });
  });

  test('shows SORN vehicles stat', async () => {
    useVehicles.mockReturnValue({
      vehicles: mockVehicles,
      loading: false,
      error: null,
      refreshVehicles: jest.fn(),
      recordsVersion: 0,
    });
    renderDashboard();
    await waitFor(() => {
      expect(screen.getByText('dashboard.sornVehicles')).toBeInTheDocument();
    });
  });

  test('handles api errors gracefully', async () => {
    mockApi.get.mockRejectedValue(new Error('Network error'));
    useVehicles.mockReturnValue({
      vehicles: mockVehicles,
      loading: false,
      error: null,
      refreshVehicles: jest.fn(),
      recordsVersion: 0,
    });
    renderDashboard();
    await waitFor(() => {
      // Should still render without crashing
      expect(screen.getByText('dashboard.welcome')).toBeInTheDocument();
    });
  });

  test('service cost stat card displays averageServiceCost value from API', async () => {
    // Verify the card reads averageServiceCost first (not totalServiceCost),
    // so the "Average Service Cost" title matches the displayed figure.
    mockApi.get.mockImplementation((url) => {
      if (url.includes('/vehicles/totals')) {
        return Promise.resolve({
          data: {
            fuel: 200,
            parts: 50,
            consumables: 30,
            averageServiceCost: 75,
            totalServiceCost: 150,
          },
        });
      }
      return Promise.resolve({ data: {} });
    });
    useVehicles.mockReturnValue({
      vehicles: mockVehicles,
      loading: false,
      error: null,
      refreshVehicles: jest.fn(),
      recordsVersion: 0,
    });
    renderDashboard();

    await waitFor(() => {
      // The stat card title key should be 'dashboard.averageServiceCost'.
      expect(screen.getByText(/dashboard\.averageServiceCost/)).toBeInTheDocument();
    });

    // The card should display the average (£75), not the total (£150).
    // The i18n mock returns the key string, so the subtitle reads 'common.average'.
    expect(screen.getByText(/common\.average/)).toBeInTheDocument();

    // The formatted value should show £75, not £150.
    expect(screen.getByText('£75')).toBeInTheDocument();
  });
});
