import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { BrowserRouter } from 'react-router-dom';
import ConsumableDialog from '../components/ConsumableDialog';
import { AuthContext } from '../context/AuthContext';

jest.mock('../hooks/useApiData');
const { useApiData } = require('../hooks/useApiData');

const renderWithProviders = (component) => {
  const mockAuthContext = {
    token: 'mock-token',
    user: { id: 1, email: 'test@example.com' }
  };

  return render(
    <BrowserRouter>
      <AuthContext.Provider value={mockAuthContext}>
        {component}
      </AuthContext.Provider>
    </BrowserRouter>
  );
};

describe('ConsumableDialog', () => {
  const mockOnClose = jest.fn();
  const mockOnSave = jest.fn();
  const mockVehicleId = 1;

  const mockConsumableTypes = [
    { id: 1, name: 'Engine Oil', category: 'Fluids', defaultIntervalMiles: 10000 },
    { id: 2, name: 'Air Filter', category: 'Filters', defaultIntervalMiles: 15000 },
    { id: 3, name: 'Brake Pads', category: 'Brakes', defaultIntervalMiles: 30000 }
  ];

  beforeEach(() => {
    jest.clearAllMocks();
    useApiData.mockReturnValue({
      data: mockConsumableTypes,
      loading: false,
      error: null
    });
  });

  test('renders consumable dialog with form fields', () => {
    renderWithProviders(
      <ConsumableDialog
        open={true}
        onClose={mockOnClose}
        onSave={mockOnSave}
        vehicleId={mockVehicleId}
      />
    );

    expect(screen.getByText(/add consumable/i)).toBeInTheDocument();
    expect(screen.getByLabelText(/type/i)).toBeInTheDocument();
    expect(screen.getByLabelText(/replacement mileage/i)).toBeInTheDocument();
    expect(screen.getByLabelText(/cost/i)).toBeInTheDocument();
    expect(screen.getByLabelText(/supplier/i)).toBeInTheDocument();
  });

  test('populates default interval when type is selected', () => {
    renderWithProviders(
      <ConsumableDialog
        open={true}
        onClose={mockOnClose}
        onSave={mockOnSave}
        vehicleId={mockVehicleId}
      />
    );

    const typeSelect = screen.getByLabelText(/type/i);
    fireEvent.change(typeSelect, { target: { value: 1 } });

    const intervalField = screen.getByLabelText(/interval miles/i);
    expect(intervalField.value).toBe('10000');
  });

  test('calculates next replacement mileage', async () => {
    renderWithProviders(
      <ConsumableDialog
        open={true}
        onClose={mockOnClose}
        onSave={mockOnSave}
        vehicleId={mockVehicleId}
        currentMileage={45000}
      />
    );

    const replacementField = screen.getByLabelText(/replacement mileage/i);
    fireEvent.change(replacementField, { target: { value: 45000 } });

    const intervalField = screen.getByLabelText(/interval miles/i);
    fireEvent.change(intervalField, { target: { value: 10000 } });

    await waitFor(() => {
      expect(screen.getByText(/next due: 55,000 miles/i)).toBeInTheDocument();
    });
  });

  test('validates required fields', async () => {
    renderWithProviders(
      <ConsumableDialog
        open={true}
        onClose={mockOnClose}
        onSave={mockOnSave}
        vehicleId={mockVehicleId}
      />
    );

    const saveButton = screen.getByRole('button', { name: /save/i });
    fireEvent.click(saveButton);

    await waitFor(() => {
      expect(screen.getByText(/type is required/i)).toBeInTheDocument();
    });
  });

  test('validates replacement mileage is positive', async () => {
    renderWithProviders(
      <ConsumableDialog
        open={true}
        onClose={mockOnClose}
        onSave={mockOnSave}
        vehicleId={mockVehicleId}
      />
    );

    const replacementField = screen.getByLabelText(/replacement mileage/i);
    fireEvent.change(replacementField, { target: { value: -1000 } });

    const saveButton = screen.getByRole('button', { name: /save/i });
    fireEvent.click(saveButton);

    await waitFor(() => {
      expect(screen.getByText(/mileage must be positive/i)).toBeInTheDocument();
    });
  });

  test('validates cost is positive', async () => {
    renderWithProviders(
      <ConsumableDialog
        open={true}
        onClose={mockOnClose}
        onSave={mockOnSave}
        vehicleId={mockVehicleId}
      />
    );

    const costField = screen.getByLabelText(/cost/i);
    fireEvent.change(costField, { target: { value: -10 } });

    const saveButton = screen.getByRole('button', { name: /save/i });
    fireEvent.click(saveButton);

    await waitFor(() => {
      expect(screen.getByText(/cost must be positive/i)).toBeInTheDocument();
    });
  });

  test('saves consumable with correct data', async () => {
    renderWithProviders(
      <ConsumableDialog
        open={true}
        onClose={mockOnClose}
        onSave={mockOnSave}
        vehicleId={mockVehicleId}
      />
    );

    fireEvent.change(screen.getByLabelText(/type/i), { target: { value: 1 } });
    fireEvent.change(screen.getByLabelText(/replacement mileage/i), { target: { value: 45000 } });
    fireEvent.change(screen.getByLabelText(/interval miles/i), { target: { value: 10000 } });
    fireEvent.change(screen.getByLabelText(/cost/i), { target: { value: 45.99 } });
    fireEvent.change(screen.getByLabelText(/supplier/i), { target: { value: 'Halfords' } });

    const saveButton = screen.getByRole('button', { name: /save/i });
    fireEvent.click(saveButton);

    await waitFor(() => {
      expect(mockOnSave).toHaveBeenCalledWith({
        typeId: 1,
        replacementMileage: 45000,
        intervalMiles: 10000,
        cost: 45.99,
        supplier: 'Halfords',
        vehicleId: mockVehicleId
      });
    });
  });

  test('displays overdue indicator when past interval', () => {
    renderWithProviders(
      <ConsumableDialog
        open={true}
        onClose={mockOnClose}
        onSave={mockOnSave}
        vehicleId={mockVehicleId}
        currentMileage={60000}
        consumable={{
          id: 1,
          type: 'Engine Oil',
          replacementMileage: 45000,
          intervalMiles: 10000
        }}
      />
    );

    expect(screen.getByText(/overdue by 5,000 miles/i)).toBeInTheDocument();
  });

  test('displays due soon indicator', () => {
    renderWithProviders(
      <ConsumableDialog
        open={true}
        onClose={mockOnClose}
        onSave={mockOnSave}
        vehicleId={mockVehicleId}
        currentMileage={53500}
        consumable={{
          id: 1,
          type: 'Engine Oil',
          replacementMileage: 45000,
          intervalMiles: 10000
        }}
      />
    );

    expect(screen.getByText(/due in 1,500 miles/i)).toBeInTheDocument();
  });

  test('closes dialog on cancel', () => {
    renderWithProviders(
      <ConsumableDialog
        open={true}
        onClose={mockOnClose}
        onSave={mockOnSave}
        vehicleId={mockVehicleId}
      />
    );

    const cancelButton = screen.getByRole('button', { name: /cancel/i });
    fireEvent.click(cancelButton);

    expect(mockOnClose).toHaveBeenCalled();
  });

  test('loads consumable data for editing', () => {
    const existingConsumable = {
      id: 1,
      type: { id: 1, name: 'Engine Oil' },
      replacementMileage: 45000,
      intervalMiles: 10000,
      cost: 45.99,
      supplier: 'Halfords'
    };

    renderWithProviders(
      <ConsumableDialog
        open={true}
        onClose={mockOnClose}
        onSave={mockOnSave}
        vehicleId={mockVehicleId}
        consumable={existingConsumable}
      />
    );

    expect(screen.getByText(/edit consumable/i)).toBeInTheDocument();
    expect(screen.getByLabelText(/type/i).value).toBe('1');
    expect(screen.getByLabelText(/replacement mileage/i).value).toBe('45000');
    expect(screen.getByLabelText(/cost/i).value).toBe('45.99');
  });

  test('shows loading state', () => {
    useApiData.mockReturnValue({
      data: null,
      loading: true,
      error: null
    });

    renderWithProviders(
      <ConsumableDialog
        open={true}
        onClose={mockOnClose}
        onSave={mockOnSave}
        vehicleId={mockVehicleId}
      />
    );

    expect(screen.getByRole('progressbar')).toBeInTheDocument();
  });

  test('shows error state', () => {
    useApiData.mockReturnValue({
      data: null,
      loading: false,
      error: 'Failed to load consumable types'
    });

    renderWithProviders(
      <ConsumableDialog
        open={true}
        onClose={mockOnClose}
        onSave={mockOnSave}
        vehicleId={mockVehicleId}
      />
    );

    expect(screen.getByText(/failed to load/i)).toBeInTheDocument();
  });

  test('allows adding notes', () => {
    renderWithProviders(
      <ConsumableDialog
        open={true}
        onClose={mockOnClose}
        onSave={mockOnSave}
        vehicleId={mockVehicleId}
      />
    );

    const notesField = screen.getByLabelText(/notes/i);
    fireEvent.change(notesField, { target: { value: 'Used synthetic oil' } });

    expect(notesField.value).toBe('Used synthetic oil');
  });

  test('displays category for selected type', () => {
    renderWithProviders(
      <ConsumableDialog
        open={true}
        onClose={mockOnClose}
        onSave={mockOnSave}
        vehicleId={mockVehicleId}
      />
    );

    const typeSelect = screen.getByLabelText(/type/i);
    fireEvent.change(typeSelect, { target: { value: 1 } });

    expect(screen.getByText(/category: fluids/i)).toBeInTheDocument();
  });

  test('calculates annual cost estimate', async () => {
    renderWithProviders(
      <ConsumableDialog
        open={true}
        onClose={mockOnClose}
        onSave={mockOnSave}
        vehicleId={mockVehicleId}
        annualMileage={20000}
      />
    );

    fireEvent.change(screen.getByLabelText(/cost/i), { target: { value: 45 } });
    fireEvent.change(screen.getByLabelText(/interval miles/i), { target: { value: 10000 } });

    await waitFor(() => {
      expect(screen.getByText(/estimated annual cost: £90.00/i)).toBeInTheDocument();
    });
  });
});
