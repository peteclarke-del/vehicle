import React from 'react';
import { render, screen, waitFor } from '@testing-library/react';
import { MemoryRouter, Routes, Route } from 'react-router-dom';
import VehicleDetails from '../pages/VehicleDetails';
import { AuthContext } from '../contexts/AuthContext';

const mockApi = {
  get: jest.fn(),
  post: jest.fn(),
  put: jest.fn(),
  delete: jest.fn(),
};

const mockVehicle = {
  id: 1,
  name: 'My Toyota',
  registrationNumber: 'ABC123',
  make: 'Toyota',
  model: 'Corolla',
  year: 2020,
  colour: 'Silver',
  fuelType: 'Petrol',
  currentMileage: 50000,
  purchasePrice: 15000,
  purchaseDate: '2020-01-15',
};

const mockStats = {
  stats: {
    totalCostToDate: 7900,
    purchaseCost: 15000,
    currentValue: 12000,
    totalRunningCost: 5000,
    totalFuelCost: 3000,
    totalPartsCost: 800,
    totalConsumablesCost: 400,
    totalServiceCost: 2500,
    costPerMile: 0.158,
  },
};

const mockAuthValue = {
  api: mockApi,
  user: { id: 1, email: 'test@example.com' },
  token: 'mock-token',
  isAuthenticated: true,
  login: jest.fn(),
  logout: jest.fn(),
};

const renderWithProviders = () => {
  return render(
    <MemoryRouter initialEntries={['/vehicles/1']}>
      <AuthContext.Provider value={mockAuthValue}>
        <Routes>
          <Route path="/vehicles/:id" element={<VehicleDetails />} />
        </Routes>
      </AuthContext.Provider>
    </MemoryRouter>
  );
};

describe('VehicleDetails Component', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    mockApi.get.mockImplementation((url) => {
      if (url.includes('/vehicles/1/stats')) {
        return Promise.resolve({ data: mockStats });
      }
      if (url.includes('/vehicles/1')) {
        return Promise.resolve({ data: mockVehicle });
      }
      if (url.includes('/depreciation')) {
        return Promise.resolve({ data: { schedule: [] } });
      }
      return Promise.resolve({ data: null });
    });
  });

  test('renders loading state initially', () => {
    mockApi.get.mockReturnValue(new Promise(() => {})); // never resolves

    renderWithProviders();

    expect(screen.queryByText('vehicleDetails.overview')).not.toBeInTheDocument();
  });

  test('renders vehicle details after loading', async () => {
    renderWithProviders();

    await waitFor(() => {
      expect(screen.getByText('vehicleDetails.overview')).toBeInTheDocument();
    });
  });

  test('renders vehicle make and model', async () => {
    renderWithProviders();

    await waitFor(() => {
      expect(screen.getByText(/Toyota.*Corolla.*2020/i)).toBeInTheDocument();
    });
  });

  test('renders tab navigation', async () => {
    renderWithProviders();

    await waitFor(() => {
      expect(screen.getByText('vehicleDetails.overview')).toBeInTheDocument();
      expect(screen.getByText('vehicleDetails.statistics')).toBeInTheDocument();
      expect(screen.getByText('vehicleDetails.specifications')).toBeInTheDocument();
    });
  });

  test('shows vehicleNotFound when API returns no data', async () => {
    mockApi.get.mockResolvedValue({ data: null });

    renderWithProviders();

    await waitFor(() => {
      expect(screen.getByText('vehicleDetails.vehicleNotFound')).toBeInTheDocument();
    });
  });

  test('renders vehicle information section', async () => {
    renderWithProviders();

    await waitFor(() => {
      expect(screen.getByText('vehicleDetails.vehicleInformation')).toBeInTheDocument();
    });
  });
});
