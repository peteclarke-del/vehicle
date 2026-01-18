import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { BrowserRouter } from 'react-router-dom';
import Dashboard from '../pages/Dashboard';
import { AuthContext } from '../context/AuthContext';

// Mock react-i18next
jest.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: (key) => key,
  }),
}));

// Mock recharts to avoid rendering issues in tests
jest.mock('recharts', () => ({
  LineChart: ({ children }) => <div data-testid="line-chart">{children}</div>,
  Line: () => null,
  XAxis: () => null,
  YAxis: () => null,
  CartesianGrid: () => null,
  Tooltip: () => null,
  Legend: () => null,
  ResponsiveContainer: ({ children }) => <div>{children}</div>,
  PieChart: ({ children }) => <div data-testid="pie-chart">{children}</div>,
  Pie: () => null,
  Cell: () => null,
}));

// Mock useApiData hook
jest.mock('../hooks/useApiData', () => ({
  useApiData: jest.fn(),
}));

const { useApiData } = require('../hooks/useApiData');

describe('Dashboard Component', () => {
  const mockAuthContext = {
    user: { id: 1, email: 'test@example.com' },
    logout: jest.fn(),
  };

  const mockVehicleData = [
    {
      id: 1,
      registration: 'ABC123',
      make: 'Toyota',
      model: 'Corolla',
      year: 2020,
      currentMileage: 50000,
    },
    {
      id: 2,
      registration: 'XYZ789',
      make: 'Honda',
      model: 'Civic',
      year: 2019,
      currentMileage: 60000,
    },
  ];

  const mockCostData = {
    totalCosts: 15000.00,
    breakdown: {
      service: 3000.00,
      parts: 2000.00,
      fuel: 5000.00,
      insurance: 1500.00,
      consumables: 500.00,
    },
  };

  const mockFuelData = [
    {
      id: 1,
      date: '2026-01-01',
      litres: 50,
      cost: 75.00,
      mileage: 49000,
    },
    {
      id: 2,
      date: '2026-01-15',
      litres: 45,
      cost: 67.50,
      mileage: 49500,
    },
  ];

  const mockServiceData = [
    {
      id: 1,
      description: 'Annual Service',
      date: '2026-01-10',
      mileage: 49200,
      labourCost: 150.00,
      partsCost: 50.00,
    },
  ];

  beforeEach(() => {
    jest.clearAllMocks();
  });

  const renderWithProviders = (component) => {
    return render(
      <BrowserRouter>
        <AuthContext.Provider value={mockAuthContext}>
          {component}
        </AuthContext.Provider>
      </BrowserRouter>
    );
  };

  test('renders dashboard title', () => {
    useApiData.mockReturnValue({ data: mockVehicleData, loading: false, error: null });

    renderWithProviders(<Dashboard />);

    expect(screen.getByText('dashboard.title')).toBeInTheDocument();
  });

  test('displays loading state', () => {
    useApiData.mockReturnValue({ data: null, loading: true, error: null });

    renderWithProviders(<Dashboard />);

    expect(screen.getByRole('progressbar')).toBeInTheDocument();
  });

  test('displays error message when data fetch fails', () => {
    useApiData.mockReturnValue({
      data: null,
      loading: false,
      error: 'Failed to fetch data',
    });

    renderWithProviders(<Dashboard />);

    expect(screen.getByText(/error/i)).toBeInTheDocument();
  });

  test('displays vehicle count statistic', async () => {
    useApiData.mockReturnValue({ data: mockVehicleData, loading: false, error: null });

    renderWithProviders(<Dashboard />);

    await waitFor(() => {
      expect(screen.getByText('dashboard.totalVehicles')).toBeInTheDocument();
      expect(screen.getByText('2')).toBeInTheDocument();
    });
  });

  test('displays total cost statistic', async () => {
    useApiData
      .mockReturnValueOnce({ data: mockVehicleData, loading: false, error: null })
      .mockReturnValueOnce({ data: mockCostData, loading: false, error: null });

    renderWithProviders(<Dashboard />);

    await waitFor(() => {
      expect(screen.getByText('dashboard.totalCosts')).toBeInTheDocument();
      expect(screen.getByText('£15,000.00')).toBeInTheDocument();
    });
  });

  test('renders cost breakdown pie chart', async () => {
    useApiData
      .mockReturnValueOnce({ data: mockVehicleData, loading: false, error: null })
      .mockReturnValueOnce({ data: mockCostData, loading: false, error: null });

    renderWithProviders(<Dashboard />);

    await waitFor(() => {
      expect(screen.getByTestId('pie-chart')).toBeInTheDocument();
    });
  });

  test('renders fuel economy line chart', async () => {
    useApiData
      .mockReturnValueOnce({ data: mockVehicleData, loading: false, error: null })
      .mockReturnValueOnce({ data: mockFuelData, loading: false, error: null });

    renderWithProviders(<Dashboard />);

    await waitFor(() => {
      expect(screen.getByTestId('line-chart')).toBeInTheDocument();
    });
  });

  test('displays recent service records', async () => {
    useApiData
      .mockReturnValueOnce({ data: mockVehicleData, loading: false, error: null })
      .mockReturnValueOnce({ data: mockServiceData, loading: false, error: null });

    renderWithProviders(<Dashboard />);

    await waitFor(() => {
      expect(screen.getByText('Annual Service')).toBeInTheDocument();
      expect(screen.getByText('£200.00')).toBeInTheDocument();
    });
  });

  test('calculates average fuel economy correctly', async () => {
    useApiData
      .mockReturnValueOnce({ data: mockVehicleData, loading: false, error: null })
      .mockReturnValueOnce({ data: mockFuelData, loading: false, error: null });

    renderWithProviders(<Dashboard />);

    await waitFor(() => {
      // Miles: 500, Litres: 95, Economy: 500/95 * 4.546 = 23.93 mpg
      expect(screen.getByText(/23\.9.*mpg/i)).toBeInTheDocument();
    });
  });

  test('displays upcoming MOT reminders', async () => {
    const mockMotData = [
      {
        id: 1,
        vehicleId: 1,
        testDate: '2026-01-15',
        expiryDate: '2027-01-15',
        result: 'Pass',
      },
    ];

    useApiData
      .mockReturnValueOnce({ data: mockVehicleData, loading: false, error: null })
      .mockReturnValueOnce({ data: mockMotData, loading: false, error: null });

    renderWithProviders(<Dashboard />);

    await waitFor(() => {
      expect(screen.getByText('dashboard.upcomingMot')).toBeInTheDocument();
    });
  });

  test('displays insurance renewal reminders', async () => {
    const mockInsuranceData = [
      {
        id: 1,
        vehicleId: 1,
        provider: 'Test Insurance',
        expiryDate: new Date(Date.now() + 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0], // 30 days from now
        annualCost: 650.00,
      },
    ];

    useApiData
      .mockReturnValueOnce({ data: mockVehicleData, loading: false, error: null })
      .mockReturnValueOnce({ data: mockInsuranceData, loading: false, error: null });

    renderWithProviders(<Dashboard />);

    await waitFor(() => {
      expect(screen.getByText('dashboard.upcomingRenewal')).toBeInTheDocument();
      expect(screen.getByText('Test Insurance')).toBeInTheDocument();
    });
  });

  test('navigates to vehicles page when clicking add vehicle button', async () => {
    useApiData.mockReturnValue({ data: [], loading: false, error: null });

    renderWithProviders(<Dashboard />);

    const addButton = screen.getByText('dashboard.addVehicle');
    expect(addButton).toBeInTheDocument();
    
    // Button should have link to /vehicles
    expect(addButton.closest('a')).toHaveAttribute('href', '/vehicles');
  });

  test('displays empty state when no vehicles exist', () => {
    useApiData.mockReturnValue({ data: [], loading: false, error: null });

    renderWithProviders(<Dashboard />);

    expect(screen.getByText('dashboard.noVehicles')).toBeInTheDocument();
    expect(screen.getByText('dashboard.addVehicle')).toBeInTheDocument();
  });

  test('displays cost per month statistic', async () => {
    const monthlyData = {
      ...mockCostData,
      monthlyAverage: 625.00,
    };

    useApiData
      .mockReturnValueOnce({ data: mockVehicleData, loading: false, error: null })
      .mockReturnValueOnce({ data: monthlyData, loading: false, error: null });

    renderWithProviders(<Dashboard />);

    await waitFor(() => {
      expect(screen.getByText('dashboard.monthlyAverage')).toBeInTheDocument();
      expect(screen.getByText('£625.00')).toBeInTheDocument();
    });
  });

  test('displays mileage statistics', async () => {
    useApiData.mockReturnValue({ data: mockVehicleData, loading: false, error: null });

    renderWithProviders(<Dashboard />);

    await waitFor(() => {
      expect(screen.getByText('dashboard.totalMileage')).toBeInTheDocument();
      // Total: 50000 + 60000 = 110000
      expect(screen.getByText('110,000')).toBeInTheDocument();
    });
  });

  test('filters data by selected vehicle', async () => {
    useApiData.mockReturnValue({ data: mockVehicleData, loading: false, error: null });

    renderWithProviders(<Dashboard />);

    await waitFor(() => {
      const vehicleSelect = screen.getByLabelText('dashboard.selectVehicle');
      fireEvent.change(vehicleSelect, { target: { value: '1' } });
    });

    // Should filter data to only show vehicle 1
    expect(useApiData).toHaveBeenCalledWith(
      expect.stringContaining('vehicleId=1'),
      expect.any(Object)
    );
  });

  test('refreshes data when refresh button clicked', async () => {
    const mockRefresh = jest.fn();
    useApiData.mockReturnValue({
      data: mockVehicleData,
      loading: false,
      error: null,
      refresh: mockRefresh,
    });

    renderWithProviders(<Dashboard />);

    const refreshButton = screen.getByLabelText('dashboard.refresh');
    fireEvent.click(refreshButton);

    expect(mockRefresh).toHaveBeenCalled();
  });
});
