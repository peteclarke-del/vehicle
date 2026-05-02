import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { BrowserRouter } from 'react-router-dom';
import Vehicles from '../pages/Vehicles';
import { AuthContext } from '../contexts/AuthContext';

// Vehicles fetches its own data via fetchArrayData('/vehicles')
jest.mock('../hooks/useApiData', () => ({
  useApiData: jest.fn(() => ({ data: [], loading: false, error: null, fetchData: jest.fn(), setData: jest.fn() })),
  fetchArrayData: jest.fn(),
}));

// Mock VehicleDialog to prevent hangs from nested components
jest.mock('../components/VehicleDialog', () => ({
  __esModule: true,
  default: ({ open }) => open ? require('react').createElement('div', { role: 'dialog', 'data-testid': 'vehicle-dialog' }) : null,
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
  {
    id: 1,
    registrationNumber: 'ABC123',
    registration: 'ABC123',
    make: 'Toyota',
    model: 'Corolla',
    year: 2020,
    colour: 'Silver',
    fuelType: 'Petrol',
    currentMileage: 50000,
    purchasePrice: 15000.00,
    purchaseDate: '2020-01-15',
    status: 'Live',
  },
  {
    id: 2,
    registrationNumber: 'XYZ789',
    registration: 'XYZ789',
    make: 'Honda',
    model: 'Civic',
    year: 2019,
    colour: 'Blue',
    fuelType: 'Diesel',
    currentMileage: 60000,
    purchasePrice: 12000.00,
    purchaseDate: '2019-06-20',
    status: 'Live',
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

describe('Vehicles Component', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    fetchArrayData.mockResolvedValue(mockVehicles);
  });

  test('renders vehicles page title', async () => {
    renderWithProviders(<Vehicles />);

    // When vehicles exist, shows titleWithCount; when empty, shows vehicle.title
    await waitFor(() => {
      const title = screen.queryByText('vehicles.titleWithCount') || screen.queryByText('vehicle.title');
      expect(title || screen.getByRole('heading')).toBeInTheDocument();
    });
  });

  test('displays loading state initially', () => {
    fetchArrayData.mockReturnValue(new Promise(() => {}));

    renderWithProviders(<Vehicles />);

    // KnightRiderLoader shown, no heading yet
    expect(screen.queryByText('vehicle.addVehicle')).not.toBeInTheDocument();
  });

  test('loads and displays vehicles', async () => {
    renderWithProviders(<Vehicles />);

    await waitFor(() => {
      expect(screen.getByText('ABC123')).toBeInTheDocument();
      expect(screen.getByText('XYZ789')).toBeInTheDocument();
    });
  });

  test('shows add vehicle button', async () => {
    renderWithProviders(<Vehicles />);

    await waitFor(() => {
      expect(screen.getByText('vehicle.addVehicle')).toBeInTheDocument();
    });
  });

  test('opens add dialog when add button clicked', async () => {
    renderWithProviders(<Vehicles />);

    const addButton = await screen.findByText('vehicle.addVehicle');
    fireEvent.click(addButton);

    await waitFor(() => {
      expect(screen.getByRole('dialog')).toBeInTheDocument();
    });
  });

  test('handles delete with confirmation', async () => {
    mockApi.delete.mockResolvedValue({ data: { deleted: 1 } });
    global.confirm = jest.fn(() => true);

    renderWithProviders(<Vehicles />);

    await waitFor(() => {
      expect(screen.getByText('ABC123')).toBeInTheDocument();
    });

    // Delete buttons are icon buttons with aria-label set via tooltip
    const deleteButton = screen.getAllByRole('button', { name: 'common.delete' })[0];
    fireEvent.click(deleteButton);

    await waitFor(() => {
      expect(mockApi.delete).toHaveBeenCalled();
    });
  });

  test('shows empty state when no vehicles exist', async () => {
    fetchArrayData.mockResolvedValue([]);

    renderWithProviders(<Vehicles />);

    await waitFor(() => {
      expect(screen.getByText('vehicle.title')).toBeInTheDocument();
    });
  });
});
