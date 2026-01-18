import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { BrowserRouter } from 'react-router-dom';
import VehicleDialog from '../components/VehicleDialog';
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

describe('VehicleDialog', () => {
  const mockOnClose = jest.fn();
  const mockOnSave = jest.fn();

  const mockMakes = [
    { id: 1, name: 'Toyota' },
    { id: 2, name: 'Honda' }
  ];

  const mockModels = [
    { id: 1, name: 'Corolla', makeId: 1 },
    { id: 2, name: 'Camry', makeId: 1 },
    { id: 3, name: 'Civic', makeId: 2 }
  ];

  const mockTypes = [
    { id: 1, name: 'Sedan' },
    { id: 2, name: 'SUV' }
  ];

  beforeEach(() => {
    jest.clearAllMocks();
    useApiData.mockReturnValue({
      data: { makes: mockMakes, models: mockModels, types: mockTypes },
      loading: false,
      error: null
    });
  });

  test('renders vehicle dialog with form fields', () => {
    renderWithProviders(
      <VehicleDialog
        open={true}
        onClose={mockOnClose}
        onSave={mockOnSave}
      />
    );

    expect(screen.getByText(/add vehicle/i)).toBeInTheDocument();
    expect(screen.getByLabelText(/registration/i)).toBeInTheDocument();
    expect(screen.getByLabelText(/make/i)).toBeInTheDocument();
    expect(screen.getByLabelText(/model/i)).toBeInTheDocument();
    expect(screen.getByLabelText(/year/i)).toBeInTheDocument();
  });

  test('validates registration format', async () => {
    renderWithProviders(
      <VehicleDialog
        open={true}
        onClose={mockOnClose}
        onSave={mockOnSave}
      />
    );

    const regField = screen.getByLabelText(/registration/i);
    fireEvent.change(regField, { target: { value: 'INVALID' } });

    const saveButton = screen.getByRole('button', { name: /save/i });
    fireEvent.click(saveButton);

    await waitFor(() => {
      expect(screen.getByText(/invalid registration format/i)).toBeInTheDocument();
    });
  });

  test('accepts valid UK registration formats', async () => {
    renderWithProviders(
      <VehicleDialog
        open={true}
        onClose={mockOnClose}
        onSave={mockOnSave}
      />
    );

    const validRegs = ['AB12 CDE', 'AB12CDE', 'A123 BCD', 'AB12345'];
    const regField = screen.getByLabelText(/registration/i);

    for (const reg of validRegs) {
      fireEvent.change(regField, { target: { value: reg } });
      expect(screen.queryByText(/invalid registration format/i)).not.toBeInTheDocument();
    }
  });

  test('filters models by selected make', async () => {
    renderWithProviders(
      <VehicleDialog
        open={true}
        onClose={mockOnClose}
        onSave={mockOnSave}
      />
    );

    const makeSelect = screen.getByLabelText(/make/i);
    fireEvent.change(makeSelect, { target: { value: 1 } });

    await waitFor(() => {
      const modelSelect = screen.getByLabelText(/model/i);
      const options = Array.from(modelSelect.options).map(opt => opt.text);
      
      expect(options).toContain('Corolla');
      expect(options).toContain('Camry');
      expect(options).not.toContain('Civic');
    });
  });

  test('validates VIN format if provided', async () => {
    renderWithProviders(
      <VehicleDialog
        open={true}
        onClose={mockOnClose}
        onSave={mockOnSave}
      />
    );

    const vinField = screen.getByLabelText(/vin/i);
    fireEvent.change(vinField, { target: { value: 'SHORT' } });

    const saveButton = screen.getByRole('button', { name: /save/i });
    fireEvent.click(saveButton);

    await waitFor(() => {
      expect(screen.getByText(/vin must be 17 characters/i)).toBeInTheDocument();
    });
  });

  test('accepts valid 17-character VIN', () => {
    renderWithProviders(
      <VehicleDialog
        open={true}
        onClose={mockOnClose}
        onSave={mockOnSave}
      />
    );

    const vinField = screen.getByLabelText(/vin/i);
    fireEvent.change(vinField, { target: { value: 'JH4DA9370MS000001' } });

    expect(screen.queryByText(/vin must be/i)).not.toBeInTheDocument();
  });

  test('validates year is between 1900 and current year', async () => {
    renderWithProviders(
      <VehicleDialog
        open={true}
        onClose={mockOnClose}
        onSave={mockOnSave}
      />
    );

    const yearField = screen.getByLabelText(/year/i);
    
    // Test too old
    fireEvent.change(yearField, { target: { value: 1899 } });
    let saveButton = screen.getByRole('button', { name: /save/i });
    fireEvent.click(saveButton);

    await waitFor(() => {
      expect(screen.getByText(/year must be between/i)).toBeInTheDocument();
    });

    // Test future year
    const futureYear = new Date().getFullYear() + 2;
    fireEvent.change(yearField, { target: { value: futureYear } });
    saveButton = screen.getByRole('button', { name: /save/i });
    fireEvent.click(saveButton);

    await waitFor(() => {
      expect(screen.getByText(/year must be between/i)).toBeInTheDocument();
    });
  });

  test('saves vehicle with correct data', async () => {
    renderWithProviders(
      <VehicleDialog
        open={true}
        onClose={mockOnClose}
        onSave={mockOnSave}
      />
    );

    fireEvent.change(screen.getByLabelText(/registration/i), { target: { value: 'AB12 CDE' } });
    fireEvent.change(screen.getByLabelText(/make/i), { target: { value: 1 } });
    
    await waitFor(() => {
      fireEvent.change(screen.getByLabelText(/model/i), { target: { value: 1 } });
    });

    fireEvent.change(screen.getByLabelText(/year/i), { target: { value: 2020 } });
    fireEvent.change(screen.getByLabelText(/colour/i), { target: { value: 'Blue' } });
    fireEvent.change(screen.getByLabelText(/type/i), { target: { value: 1 } });

    const saveButton = screen.getByRole('button', { name: /save/i });
    fireEvent.click(saveButton);

    await waitFor(() => {
      expect(mockOnSave).toHaveBeenCalledWith({
        registration: 'AB12 CDE',
        makeId: 1,
        modelId: 1,
        year: 2020,
        colour: 'Blue',
        typeId: 1
      });
    });
  });

  test('loads vehicle data for editing', () => {
    const existingVehicle = {
      id: 1,
      registration: 'AB12 CDE',
      make: { id: 1, name: 'Toyota' },
      model: { id: 1, name: 'Corolla' },
      year: 2020,
      colour: 'Blue',
      type: { id: 1, name: 'Sedan' }
    };

    renderWithProviders(
      <VehicleDialog
        open={true}
        onClose={mockOnClose}
        onSave={mockOnSave}
        vehicle={existingVehicle}
      />
    );

    expect(screen.getByText(/edit vehicle/i)).toBeInTheDocument();
    expect(screen.getByLabelText(/registration/i).value).toBe('AB12 CDE');
    expect(screen.getByLabelText(/year/i).value).toBe('2020');
    expect(screen.getByLabelText(/colour/i).value).toBe('Blue');
  });

  test('allows uploading vehicle image', async () => {
    renderWithProviders(
      <VehicleDialog
        open={true}
        onClose={mockOnClose}
        onSave={mockOnSave}
      />
    );

    const file = new File(['image'], 'vehicle.jpg', { type: 'image/jpeg' });
    const uploadInput = screen.getByLabelText(/upload image/i);
    
    fireEvent.change(uploadInput, { target: { files: [file] } });

    await waitFor(() => {
      expect(screen.getByText(/vehicle.jpg/i)).toBeInTheDocument();
    });
  });

  test('validates image file type', async () => {
    renderWithProviders(
      <VehicleDialog
        open={true}
        onClose={mockOnClose}
        onSave={mockOnSave}
      />
    );

    const file = new File(['doc'], 'document.pdf', { type: 'application/pdf' });
    const uploadInput = screen.getByLabelText(/upload image/i);
    
    fireEvent.change(uploadInput, { target: { files: [file] } });

    await waitFor(() => {
      expect(screen.getByText(/only image files/i)).toBeInTheDocument();
    });
  });

  test('closes dialog on cancel', () => {
    renderWithProviders(
      <VehicleDialog
        open={true}
        onClose={mockOnClose}
        onSave={mockOnSave}
      />
    );

    const cancelButton = screen.getByRole('button', { name: /cancel/i });
    fireEvent.click(cancelButton);

    expect(mockOnClose).toHaveBeenCalled();
  });

  test('shows loading state', () => {
    useApiData.mockReturnValue({
      data: null,
      loading: true,
      error: null
    });

    renderWithProviders(
      <VehicleDialog
        open={true}
        onClose={mockOnClose}
        onSave={mockOnSave}
      />
    );

    expect(screen.getByRole('progressbar')).toBeInTheDocument();
  });

  test('displays error state', () => {
    useApiData.mockReturnValue({
      data: null,
      loading: false,
      error: 'Failed to load vehicle data'
    });

    renderWithProviders(
      <VehicleDialog
        open={true}
        onClose={mockOnClose}
        onSave={mockOnSave}
      />
    );

    expect(screen.getByText(/failed to load/i)).toBeInTheDocument();
  });

  test('allows adding purchase information', () => {
    renderWithProviders(
      <VehicleDialog
        open={true}
        onClose={mockOnClose}
        onSave={mockOnSave}
      />
    );

    fireEvent.change(screen.getByLabelText(/purchase date/i), { target: { value: '2020-01-15' } });
    fireEvent.change(screen.getByLabelText(/purchase price/i), { target: { value: 15000 } });
    fireEvent.change(screen.getByLabelText(/purchase mileage/i), { target: { value: 25000 } });

    expect(screen.getByLabelText(/purchase price/i).value).toBe('15000');
  });

  test('normalizes registration to uppercase', () => {
    renderWithProviders(
      <VehicleDialog
        open={true}
        onClose={mockOnClose}
        onSave={mockOnSave}
      />
    );

    const regField = screen.getByLabelText(/registration/i);
    fireEvent.change(regField, { target: { value: 'ab12 cde' } });
    fireEvent.blur(regField);

    expect(regField.value).toBe('AB12 CDE');
  });

  test('displays make logo if available', () => {
    const makesWithLogos = [
      { id: 1, name: 'Toyota', logoUrl: 'https://example.com/toyota.png' }
    ];

    useApiData.mockReturnValue({
      data: { makes: makesWithLogos, models: mockModels, types: mockTypes },
      loading: false,
      error: null
    });

    renderWithProviders(
      <VehicleDialog
        open={true}
        onClose={mockOnClose}
        onSave={mockOnSave}
      />
    );

    const makeSelect = screen.getByLabelText(/make/i);
    fireEvent.change(makeSelect, { target: { value: 1 } });

    const logo = screen.getByAltText(/toyota logo/i);
    expect(logo).toBeInTheDocument();
  });
});
