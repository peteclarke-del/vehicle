import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { BrowserRouter } from 'react-router-dom';
import ServiceRecords from '../pages/ServiceRecords';
import { AuthContext } from '../contexts/AuthContext';

// Override the global VehiclesContext mock so we can control vehicles per-test
jest.mock('../contexts/VehiclesContext', () => ({
  VehiclesProvider: ({ children }) => children,
  useVehicles: jest.fn(),
}));

jest.mock('../hooks/useApiData', () => ({
  useApiData: jest.fn(() => ({ data: [], loading: false, error: null, fetchData: jest.fn(), setData: jest.fn() })),
  fetchArrayData: jest.fn(),
}));

// Mock the complex ServiceDialog to prevent hangs from nested components
jest.mock('../components/ServiceDialog', () => ({
  __esModule: true,
  default: ({ open }) => open ? require('react').createElement('div', { role: 'dialog', 'data-testid': 'service-dialog' }) : null,
}));

const { useVehicles } = require('../contexts/VehiclesContext');
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

const mockServiceRecords = [
  {
    id: 1,
    vehicleId: 1,
    description: 'Annual Service',
    serviceDate: '2026-01-10',
    mileage: 50000,
    laborCost: 150.00,
    partsCost: 75.00,
    totalCost: 225.00,
    serviceType: 'Full Service',
    serviceProvider: 'Test Garage',
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

describe('ServiceRecords Component', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    useVehicles.mockReturnValue({
      vehicles: mockVehicles,
      loading: false,
      error: null,
      refreshVehicles: jest.fn(),
      fetchVehicles: jest.fn(),
      notifyRecordChange: jest.fn(),
      recordsVersion: 0,
    });
    fetchArrayData.mockResolvedValue([]);
  });

  test('renders service records page title', async () => {
    renderWithProviders(<ServiceRecords />);

    await waitFor(() => {
      expect(screen.getByText('service.title')).toBeInTheDocument();
    });
  });

  test('displays loading state', () => {
    useVehicles.mockReturnValue({
      vehicles: [],
      loading: true,
      error: null,
      refreshVehicles: jest.fn(),
      fetchVehicles: jest.fn(),
      notifyRecordChange: jest.fn(),
      recordsVersion: 0,
    });
    fetchArrayData.mockReturnValue(new Promise(() => {}));

    renderWithProviders(<ServiceRecords />);

    expect(screen.queryByText('service.title')).not.toBeInTheDocument();
  });

  test('shows loading state when vehicles are loading', () => {
    useVehicles.mockReturnValue({
      vehicles: [],
      loading: true,
      error: null,
      refreshVehicles: jest.fn(),
      fetchVehicles: jest.fn(),
      notifyRecordChange: jest.fn(),
      recordsVersion: 0,
    });

    renderWithProviders(<ServiceRecords />);

    // Should show loading spinner, not the page title
    expect(screen.queryByText('service.title')).not.toBeInTheDocument();
    expect(screen.getByRole('progressbar')).toBeInTheDocument();
  });

  test('loads and displays service records', async () => {
    fetchArrayData.mockResolvedValue(mockServiceRecords);

    renderWithProviders(<ServiceRecords />);

    await waitFor(() => {
      expect(screen.getByText('Full Service')).toBeInTheDocument();
      expect(screen.getByText('Test Garage')).toBeInTheDocument();
    });
  });

  test('shows add service button', async () => {
    renderWithProviders(<ServiceRecords />);

    await waitFor(() => {
      expect(screen.getByText('service.addService')).toBeInTheDocument();
    });
  });

  test('opens add dialog when add button clicked', async () => {
    renderWithProviders(<ServiceRecords />);

    const addButton = await screen.findByText('service.addService');
    fireEvent.click(addButton);

    await waitFor(() => {
      expect(screen.getByRole('dialog')).toBeInTheDocument();
    });
  });

  test('handles delete with confirmation', async () => {
    fetchArrayData.mockResolvedValue(mockServiceRecords);
    mockApi.delete.mockResolvedValue({});
    global.confirm = jest.fn(() => true);

    renderWithProviders(<ServiceRecords />);

    await waitFor(() => {
      expect(screen.getByText('Full Service')).toBeInTheDocument();
    });

    const deleteButton = screen.getByRole('button', { name: 'common.delete' });
    fireEvent.click(deleteButton);

    await waitFor(() => {
      expect(mockApi.delete).toHaveBeenCalledWith('/service-records/1');
    });
  });

  test('shows no records message when vehicle has no service records', async () => {
    fetchArrayData.mockResolvedValue([]);

    renderWithProviders(<ServiceRecords />);

    await waitFor(() => {
      expect(screen.getByText('common.noRecords')).toBeInTheDocument();
    });
  });

  test('shows service record column headers', async () => {
    renderWithProviders(<ServiceRecords />);

    await waitFor(() => {
      expect(screen.getByText('service.serviceDate')).toBeInTheDocument();
      expect(screen.getByText('service.serviceType')).toBeInTheDocument();
      expect(screen.getByText('service.totalCost')).toBeInTheDocument();
    });
  });

  test('opens edit dialog when edit button clicked', async () => {
    fetchArrayData.mockResolvedValue(mockServiceRecords);
    mockApi.get.mockResolvedValue({ data: { serviceRecord: mockServiceRecords[0], parts: [], consumables: [] } });

    renderWithProviders(<ServiceRecords />);

    await waitFor(() => {
      expect(screen.getByText('Full Service')).toBeInTheDocument();
    });

    const editButton = screen.getByRole('button', { name: 'common.edit' });
    fireEvent.click(editButton);

    await waitFor(() => {
      expect(screen.getByRole('dialog')).toBeInTheDocument();
    });
  });
});

