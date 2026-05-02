import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { BrowserRouter } from 'react-router-dom';
import { AuthContext } from '../contexts/AuthContext';
import Insurance from '../pages/Insurance';

// Override the global VehiclesContext mock so we can control vehicles per-test
jest.mock('../contexts/VehiclesContext', () => ({
  VehiclesProvider: ({ children }) => children,
  useVehicles: jest.fn(),
}));

// Override the global useApiData mock so we can control fetchArrayData per-test
jest.mock('../hooks/useApiData', () => ({
  useApiData: jest.fn(() => ({ data: [], loading: false, error: null, fetchData: jest.fn(), setData: jest.fn() })),
  fetchArrayData: jest.fn(),
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
  user: { id: 1, email: 'test@test.com' },
  token: 'mock-token',
  isAuthenticated: true,
  login: jest.fn(),
  logout: jest.fn(),
};

const mockVehicles = [
  { id: 1, make: 'Toyota', model: 'Corolla', year: 2020, registrationNumber: 'AB12CDE' },
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

describe('Insurance Component', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    // Default: no vehicles
    useVehicles.mockReturnValue({
      vehicles: [],
      loading: false,
      error: null,
      refreshVehicles: jest.fn(),
      fetchVehicles: jest.fn(),
      recordsVersion: 0,
    });
    fetchArrayData.mockResolvedValue([]);
  });

  test('renders insurance page title', async () => {
    useVehicles.mockReturnValue({
      vehicles: mockVehicles,
      loading: false,
      error: null,
      refreshVehicles: jest.fn(),
      fetchVehicles: jest.fn(),
      recordsVersion: 0,
    });
    fetchArrayData.mockResolvedValue([]);

    renderWithProviders(<Insurance />);

    await waitFor(() => {
      expect(screen.getByText('insurance.policies.title')).toBeInTheDocument();
    });
  });

  test('displays no vehicles message when no vehicles exist', async () => {
    renderWithProviders(<Insurance />);

    await waitFor(() => {
      expect(screen.getByText('No vehicles')).toBeInTheDocument();
    });
  });

  test('shows add policy button', async () => {
    useVehicles.mockReturnValue({
      vehicles: mockVehicles,
      loading: false,
      error: null,
      refreshVehicles: jest.fn(),
      fetchVehicles: jest.fn(),
      recordsVersion: 0,
    });
    fetchArrayData.mockResolvedValue([]);

    renderWithProviders(<Insurance />);

    await waitFor(() => {
      expect(screen.getByText('insurance.policies.addPolicy')).toBeInTheDocument();
    });
  });

  test('loads and displays insurance policy records', async () => {
    useVehicles.mockReturnValue({
      vehicles: mockVehicles,
      loading: false,
      error: null,
      refreshVehicles: jest.fn(),
      fetchVehicles: jest.fn(),
      recordsVersion: 0,
    });

    const mockPolicies = [
      {
        id: 1,
        provider: 'Test Insurance Co',
        policyNumber: 'POL123',
        startDate: '2026-01-01',
        expiryDate: '2027-01-01',
        ncdYears: 5,
        vehicles: [],
      },
    ];
    fetchArrayData.mockResolvedValue(mockPolicies);

    renderWithProviders(<Insurance />);

    await waitFor(() => {
      expect(screen.getByText('Test Insurance Co')).toBeInTheDocument();
      expect(screen.getByText('POL123')).toBeInTheDocument();
    });
  });

  test('opens add dialog when add button clicked', async () => {
    useVehicles.mockReturnValue({
      vehicles: mockVehicles,
      loading: false,
      error: null,
      refreshVehicles: jest.fn(),
      fetchVehicles: jest.fn(),
      recordsVersion: 0,
    });
    fetchArrayData.mockResolvedValue([]);

    renderWithProviders(<Insurance />);

    const addButton = await screen.findByText('insurance.policies.addPolicy');
    expect(addButton).toBeInTheDocument();

    fireEvent.click(addButton);

    // After clicking, PolicyDialog mounts — verify the dialog is in the DOM
    await waitFor(() => {
      // PolicyDialog renders a form with vehicle-related fields
      expect(screen.getByRole('dialog')).toBeInTheDocument();
    });
  });

  test('handles delete with confirmation', async () => {
    useVehicles.mockReturnValue({
      vehicles: mockVehicles,
      loading: false,
      error: null,
      refreshVehicles: jest.fn(),
      fetchVehicles: jest.fn(),
      recordsVersion: 0,
    });

    const mockPolicies = [
      {
        id: 42,
        provider: 'Delete Me Insurance',
        policyNumber: 'DEL001',
        startDate: '2026-01-01',
        expiryDate: '2027-01-01',
        ncdYears: 3,
        vehicles: [],
      },
    ];
    fetchArrayData.mockResolvedValue(mockPolicies);
    mockApi.delete.mockResolvedValue({});
    global.confirm = jest.fn(() => true);

    renderWithProviders(<Insurance />);

    await waitFor(() => {
      expect(screen.getByText('Delete Me Insurance')).toBeInTheDocument();
    });

    const deleteButton = screen.getByRole('button', { name: 'common.delete' });
    fireEvent.click(deleteButton);

    await waitFor(() => {
      expect(mockApi.delete).toHaveBeenCalledWith('/insurance/policies/42');
    });
  });

  test('shows no records message when vehicle has no policies', async () => {
    useVehicles.mockReturnValue({
      vehicles: mockVehicles,
      loading: false,
      error: null,
      refreshVehicles: jest.fn(),
      fetchVehicles: jest.fn(),
      recordsVersion: 0,
    });
    fetchArrayData.mockResolvedValue([]);

    renderWithProviders(<Insurance />);

    await waitFor(() => {
      expect(screen.getByText('common.noRecords')).toBeInTheDocument();
    });
  });

  test('shows loading spinner initially', () => {
    useVehicles.mockReturnValue({
      vehicles: [],
      loading: true,
      error: null,
      refreshVehicles: jest.fn(),
      fetchVehicles: jest.fn(),
      recordsVersion: 0,
    });
    fetchArrayData.mockReturnValue(new Promise(() => {})); // never resolves

    renderWithProviders(<Insurance />);

    // Should show loader (KnightRiderLoader) while loading
    expect(screen.queryByText('insurance.policies.title')).not.toBeInTheDocument();
  });

  test('shows provider and policy number column headers', async () => {
    useVehicles.mockReturnValue({
      vehicles: mockVehicles,
      loading: false,
      error: null,
      refreshVehicles: jest.fn(),
      fetchVehicles: jest.fn(),
      recordsVersion: 0,
    });
    fetchArrayData.mockResolvedValue([]);

    renderWithProviders(<Insurance />);

    await waitFor(() => {
      expect(screen.getByText('insurance.policies.provider')).toBeInTheDocument();
      expect(screen.getByText('insurance.policies.policyNumber')).toBeInTheDocument();
      expect(screen.getByText('insurance.policies.expiryDate')).toBeInTheDocument();
    });
  });
});
