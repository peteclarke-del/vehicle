import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { BrowserRouter } from 'react-router-dom';
import FuelRecords from '../pages/FuelRecords';
import { AuthContext } from '../contexts/AuthContext';

// FuelRecords fetches vehicles itself via fetchArrayData('/vehicles')
// and uses useVehicles only for notifyRecordChange
jest.mock('../hooks/useApiData', () => ({
  useApiData: jest.fn(() => ({ data: [], loading: false, error: null, fetchData: jest.fn(), setData: jest.fn() })),
  fetchArrayData: jest.fn(),
}));

// Mock the complex FuelRecordDialog to prevent hangs from nested components
jest.mock('../components/FuelRecordDialog', () => ({
  __esModule: true,
  default: ({ open }) => open ? require('react').createElement('div', { role: 'dialog', 'data-testid': 'fuel-dialog' }) : null,
}));

const { fetchArrayData } = require('../hooks/useApiData');

const mockApi = {
  get: jest.fn(),
  post: jest.fn(),
  put: jest.fn(),
  delete: jest.fn(),
};

const mockAuthValue = {
  api: mockApi,
  user: { id: 1, email: 'test@example.com' },
  token: 'mock-token',
  isAuthenticated: true,
  login: jest.fn(),
  logout: jest.fn(),
};

const mockVehicles = [
  { id: 1, make: 'Toyota', model: 'Corolla', year: 2020, registrationNumber: 'ABC123', registration: 'ABC123' },
];

const mockFuelRecords = [
  {
    id: 1,
    vehicleId: 1,
    date: '2026-01-10',
    litres: 45.5,
    totalCost: 68.25,
    pricePerLitre: 1.50,
    mileage: 50000,
    fuelType: 'Petrol',
    station: 'Shell',
    fullTank: true,
  },
];

const renderWithProviders = (component) => {
  return render(
    <BrowserRouter>
      <AuthContext.Provider value={mockAuthValue}>
        {component}
      </AuthContext.Provider>
    </BrowserRouter>
  );
};

describe('FuelRecords Component', () => {
  beforeEach(() => {
    jest.useFakeTimers();
    jest.clearAllMocks();
    // First fetchArrayData call is for /vehicles, subsequent for /fuel-records
    fetchArrayData.mockImplementation((api, url) => {
      if (url === '/vehicles') return Promise.resolve(mockVehicles);
      return Promise.resolve([]);
    });
  });

  afterEach(() => {
    jest.runOnlyPendingTimers();
    jest.useRealTimers();
  });

  test('renders fuel records page title', async () => {
    renderWithProviders(<FuelRecords />);

    await waitFor(() => {
      expect(screen.getByText('fuel.title')).toBeInTheDocument();
    });
  });

  test('displays loading state initially', () => {
    fetchArrayData.mockReturnValue(new Promise(() => {})); // never resolves

    renderWithProviders(<FuelRecords />);

    expect(screen.queryByText('fuel.title')).not.toBeInTheDocument();
  });

  test('loads and displays fuel records', async () => {
    fetchArrayData.mockImplementation((api, url) => {
      if (url === '/vehicles') return Promise.resolve(mockVehicles);
      return Promise.resolve(mockFuelRecords);
    });

    renderWithProviders(<FuelRecords />);

    await waitFor(() => {
      expect(screen.getByText('Shell')).toBeInTheDocument();
    });
  });

  test('shows add fuel record button', async () => {
    renderWithProviders(<FuelRecords />);

    await waitFor(() => {
      expect(screen.getByText('fuel.addRecord')).toBeInTheDocument();
    });
  });

  test('shows add record button (disabled for all-vehicles view)', async () => {
    renderWithProviders(<FuelRecords />);

    // Button exists but is disabled when '__all__' vehicles selected
    const addButton = await screen.findByText('fuel.addRecord');
    expect(addButton).toBeInTheDocument();
    const btn = addButton.closest('button');
    expect(btn).toBeDisabled();
  });

  test('handles delete with confirmation', async () => {
    fetchArrayData.mockImplementation((api, url) => {
      if (url === '/vehicles') return Promise.resolve(mockVehicles);
      return Promise.resolve(mockFuelRecords);
    });
    mockApi.delete.mockResolvedValue({});
    global.confirm = jest.fn(() => true);

    renderWithProviders(<FuelRecords />);

    await waitFor(() => {
      expect(screen.getByText('Shell')).toBeInTheDocument();
    });

    const deleteButton = screen.getByRole('button', { name: 'common.delete' });
    fireEvent.click(deleteButton);

    await waitFor(() => {
      expect(mockApi.delete).toHaveBeenCalledWith('/fuel-records/1');
    });
  });

  test('shows table when no fuel records (empty table)', async () => {
    renderWithProviders(<FuelRecords />);

    // FuelRecords shows an empty table, not a noRecords message
    await waitFor(() => {
      expect(screen.getByText('fuel.litres')).toBeInTheDocument();
    });
  });

  test('shows fuel records column headers', async () => {
    renderWithProviders(<FuelRecords />);

    await waitFor(() => {
      expect(screen.getByText('fuel.litres')).toBeInTheDocument();
      expect(screen.getByText('fuel.cost')).toBeInTheDocument();
      expect(screen.getByText('fuel.station')).toBeInTheDocument();
    });
  });
});
