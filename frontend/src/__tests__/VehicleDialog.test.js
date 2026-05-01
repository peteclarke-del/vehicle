import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { BrowserRouter } from 'react-router-dom';
import VehicleDialog from '../components/VehicleDialog';
import { AuthContext } from '../context/AuthContext';

jest.mock('../hooks/useApiData', () => ({
  fetchArrayData: jest.fn(),
}));
const { fetchArrayData } = require('../hooks/useApiData');

const mockApi = {
  get: jest.fn().mockResolvedValue({ data: [] }),
  post: jest.fn().mockResolvedValue({ data: {} }),
  put: jest.fn().mockResolvedValue({ data: {} }),
  delete: jest.fn().mockResolvedValue({ data: {} }),
};

const renderWithProviders = (component) => {
  const mockAuthContext = {
    token: 'mock-token',
    user: { id: 1, email: 'test@example.com' },
    api: mockApi,
  };

  return render(
    <BrowserRouter>
      <AuthContext.Provider value={mockAuthContext}>
        {component}
      </AuthContext.Provider>
    </BrowserRouter>
  );
};

describe('VehicleDialog', () => {
  // VehicleDialog chains multiple async API effects (types → makes → models).
  // Allow extra time so the full render cycle completes in each test.
  jest.setTimeout(15000);

  const mockOnClose = jest.fn();

  const mockVehicleTypes = [
    { id: 1, name: 'Car' },
    { id: 2, name: 'Motorcycle' },
  ];

  beforeEach(() => {
    jest.clearAllMocks();
    fetchArrayData.mockResolvedValue(mockVehicleTypes);
    mockApi.get.mockResolvedValue({ data: [] });
    mockApi.post.mockResolvedValue({ data: {} });
    mockApi.put.mockResolvedValue({ data: {} });
    mockApi.delete.mockResolvedValue({ data: {} });
  });

  test('renders add vehicle dialog title', async () => {
    renderWithProviders(
      <VehicleDialog
        open={true}
        onClose={mockOnClose}
      />
    );

    await waitFor(() => {
      expect(screen.getByText('vehicle.addVehicle')).toBeInTheDocument();
    });
  });

  test('renders edit vehicle dialog title when vehicle is passed', async () => {
    const existingVehicle = {
      id: 1,
      name: 'My Car',
      registrationNumber: 'AB12 CDE',
      year: 2020,
      vehicleType: { id: 1, name: 'Car' },
      securityFeatures: [],
      motExempt: false,
      roadTaxExempt: false,
    };

    renderWithProviders(
      <VehicleDialog
        open={true}
        onClose={mockOnClose}
        vehicle={existingVehicle}
      />
    );

    await waitFor(() => {
      expect(screen.getByText('vehicleDialog.editVehicle')).toBeInTheDocument();
    });
  });

  test('shows cancel and save buttons', async () => {
    renderWithProviders(
      <VehicleDialog
        open={true}
        onClose={mockOnClose}
      />
    );

    await waitFor(() => {
      expect(screen.getByRole('button', { name: 'common.cancel' })).toBeInTheDocument();
      expect(screen.getByRole('button', { name: 'common.save' })).toBeInTheDocument();
    });
  });

  test('calls onClose when cancel is clicked', async () => {
    renderWithProviders(
      <VehicleDialog
        open={true}
        onClose={mockOnClose}
      />
    );

    await waitFor(() => {
      expect(screen.getByRole('button', { name: 'common.cancel' })).toBeInTheDocument();
    });

    fireEvent.click(screen.getByRole('button', { name: 'common.cancel' }));

    expect(mockOnClose).toHaveBeenCalled();
  });

  test('loads vehicle types on open', async () => {
    renderWithProviders(
      <VehicleDialog
        open={true}
        onClose={mockOnClose}
      />
    );

    await waitFor(() => {
      expect(fetchArrayData).toHaveBeenCalledWith(mockApi, '/vehicle-types');
    });
  });

  test('does not load when dialog is closed', () => {
    renderWithProviders(
      <VehicleDialog
        open={false}
        onClose={mockOnClose}
      />
    );

    expect(fetchArrayData).not.toHaveBeenCalled();
  });

  test('renders name and registration fields', async () => {
    renderWithProviders(
      <VehicleDialog
        open={true}
        onClose={mockOnClose}
      />
    );

    await waitFor(() => {
      expect(screen.getByLabelText(/vehicle\.name/i)).toBeInTheDocument();
      expect(screen.getByLabelText(/common\.registrationNumber/i)).toBeInTheDocument();
    });
  });

  test('populates form fields from vehicle prop', async () => {
    const existingVehicle = {
      id: 1,
      name: 'My Car',
      registrationNumber: 'AB12 CDE',
      year: 2020,
      vehicleType: { id: 1, name: 'Car' },
      securityFeatures: [],
      motExempt: false,
      roadTaxExempt: false,
    };

    renderWithProviders(
      <VehicleDialog
        open={true}
        onClose={mockOnClose}
        vehicle={existingVehicle}
      />
    );

    await waitFor(() => {
      const nameField = screen.getByLabelText(/vehicle\.name/i);
      expect(nameField.value).toBe('My Car');
    });
  });
});
