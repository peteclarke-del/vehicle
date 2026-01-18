import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import FuelRecordDialog from '../components/FuelRecordDialog';
import { AuthContext } from '../context/AuthContext';
import '@testing-library/jest-dom';

// Mock i18next
jest.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: (key) => key,
  }),
}));

const renderWithProviders = (component) => {
  const mockAuthContext = {
    user: { id: 1, email: 'test@example.com' },
    token: 'mock-token',
  };

  return render(
    <AuthContext.Provider value={mockAuthContext}>
      {component}
    </AuthContext.Provider>
  );
};

describe('FuelRecordDialog Component', () => {
  const mockOnClose = jest.fn();
  const mockOnSave = jest.fn();

  const defaultProps = {
    open: true,
    onClose: mockOnClose,
    onSave: mockOnSave,
    vehicleId: 1,
    currentMileage: 50000,
  };

  beforeEach(() => {
    global.fetch = jest.fn(() =>
      Promise.resolve({
        ok: true,
        json: () => Promise.resolve({ id: 1 }),
      })
    );
  });

  afterEach(() => {
    jest.clearAllMocks();
  });

  test('renders dialog when open', () => {
    renderWithProviders(<FuelRecordDialog {...defaultProps} />);
    expect(screen.getByText('fuelRecord.newTitle')).toBeInTheDocument();
  });

  test('displays all form fields', () => {
    renderWithProviders(<FuelRecordDialog {...defaultProps} />);
    
    expect(screen.getByLabelText(/date/i)).toBeInTheDocument();
    expect(screen.getByLabelText(/mileage/i)).toBeInTheDocument();
    expect(screen.getByLabelText(/litres/i)).toBeInTheDocument();
    expect(screen.getByLabelText(/cost/i)).toBeInTheDocument();
    expect(screen.getByLabelText(/station/i)).toBeInTheDocument();
  });

  test('submits form with valid data', async () => {
    renderWithProviders(<FuelRecordDialog {...defaultProps} />);
    
    fireEvent.change(screen.getByLabelText(/date/i), {
      target: { value: '2024-01-15' },
    });
    fireEvent.change(screen.getByLabelText(/mileage/i), {
      target: { value: '50500' },
    });
    fireEvent.change(screen.getByLabelText(/litres/i), {
      target: { value: '45.5' },
    });
    fireEvent.change(screen.getByLabelText(/cost/i), {
      target: { value: '68.25' },
    });

    const saveButton = screen.getByText(/save/i);
    fireEvent.click(saveButton);

    await waitFor(() => {
      expect(global.fetch).toHaveBeenCalledWith(
        expect.stringContaining('/fuel-records'),
        expect.objectContaining({
          method: 'POST',
          body: expect.stringContaining('"litres":45.5'),
        })
      );
      expect(mockOnSave).toHaveBeenCalled();
    });
  });

  test('calculates price per litre', () => {
    renderWithProviders(<FuelRecordDialog {...defaultProps} />);
    
    fireEvent.change(screen.getByLabelText(/litres/i), {
      target: { value: '45.5' },
    });
    fireEvent.change(screen.getByLabelText(/cost/i), {
      target: { value: '68.25' },
    });

    // £68.25 / 45.5 litres = £1.50 per litre
    expect(screen.getByText(/£1.50 per litre/i)).toBeInTheDocument();
  });

  test('calculates fuel economy estimate', () => {
    const fuelRecord = {
      previousMileage: 49500,
      previousLitres: 45.0,
    };

    renderWithProviders(
      <FuelRecordDialog {...defaultProps} previousRecord={fuelRecord} />
    );
    
    fireEvent.change(screen.getByLabelText(/mileage/i), {
      target: { value: '50000' },
    });
    fireEvent.change(screen.getByLabelText(/litres/i), {
      target: { value: '45.5' },
    });

    // (50000 - 49500) miles = 500 miles
    // 500 / 45.5 litres * 4.546 = 50 mpg
    expect(screen.getByText(/50.0 mpg/i)).toBeInTheDocument();
  });

  test('validates required fields', async () => {
    renderWithProviders(<FuelRecordDialog {...defaultProps} />);
    
    const saveButton = screen.getByText(/save/i);
    fireEvent.click(saveButton);

    await waitFor(() => {
      expect(screen.getByText(/date is required/i)).toBeInTheDocument();
      expect(screen.getByText(/mileage is required/i)).toBeInTheDocument();
      expect(screen.getByText(/litres is required/i)).toBeInTheDocument();
    });

    expect(global.fetch).not.toHaveBeenCalled();
  });

  test('validates mileage is greater than previous', async () => {
    renderWithProviders(<FuelRecordDialog {...defaultProps} />);
    
    fireEvent.change(screen.getByLabelText(/mileage/i), {
      target: { value: '49000' },
    });

    const saveButton = screen.getByText(/save/i);
    fireEvent.click(saveButton);

    await waitFor(() => {
      expect(screen.getByText(/mileage must be greater than current/i)).toBeInTheDocument();
    });
  });

  test('displays full tank checkbox', () => {
    renderWithProviders(<FuelRecordDialog {...defaultProps} />);
    expect(screen.getByLabelText(/full tank/i)).toBeInTheDocument();
  });

  test('displays fuel type field', () => {
    renderWithProviders(<FuelRecordDialog {...defaultProps} />);
    expect(screen.getByLabelText(/fuel type/i)).toBeInTheDocument();
  });

  test('populates form in edit mode', () => {
    const fuelRecord = {
      id: 1,
      date: '2024-01-15',
      mileage: 50500,
      litres: 45.5,
      cost: 68.25,
      station: 'Shell',
      fullTank: true,
    };

    renderWithProviders(
      <FuelRecordDialog {...defaultProps} fuelRecord={fuelRecord} />
    );
    
    expect(screen.getByDisplayValue('2024-01-15')).toBeInTheDocument();
    expect(screen.getByDisplayValue('50500')).toBeInTheDocument();
    expect(screen.getByDisplayValue('45.5')).toBeInTheDocument();
    expect(screen.getByDisplayValue('68.25')).toBeInTheDocument();
    expect(screen.getByDisplayValue('Shell')).toBeInTheDocument();
    expect(screen.getByLabelText(/full tank/i)).toBeChecked();
  });

  test('calculates cost per mile', () => {
    renderWithProviders(<FuelRecordDialog {...defaultProps} />);
    
    fireEvent.change(screen.getByLabelText(/mileage/i), {
      target: { value: '50500' },
    });
    fireEvent.change(screen.getByLabelText(/cost/i), {
      target: { value: '70.00' },
    });

    // £70 / 500 miles = £0.14 per mile
    expect(screen.getByText(/£0.14 per mile/i)).toBeInTheDocument();
  });

  test('displays payment method field', () => {
    renderWithProviders(<FuelRecordDialog {...defaultProps} />);
    expect(screen.getByLabelText(/payment method/i)).toBeInTheDocument();
  });

  test('displays notes field', () => {
    renderWithProviders(<FuelRecordDialog {...defaultProps} />);
    expect(screen.getByLabelText(/notes/i)).toBeInTheDocument();
  });

  test('handles receipt image upload', async () => {
    renderWithProviders(<FuelRecordDialog {...defaultProps} />);
    
    const file = new File(['receipt'], 'receipt.jpg', { type: 'image/jpeg' });
    const fileInput = screen.getByLabelText(/upload receipt/i);

    fireEvent.change(fileInput, { target: { files: [file] } });

    await waitFor(() => {
      expect(screen.getByText('receipt.jpg')).toBeInTheDocument();
    });
  });

  test('validates positive litres value', async () => {
    renderWithProviders(<FuelRecordDialog {...defaultProps} />);
    
    fireEvent.change(screen.getByLabelText(/litres/i), {
      target: { value: '-10' },
    });

    const saveButton = screen.getByText(/save/i);
    fireEvent.click(saveButton);

    await waitFor(() => {
      expect(screen.getByText(/litres must be positive/i)).toBeInTheDocument();
    });
  });

  test('validates positive cost value', async () => {
    renderWithProviders(<FuelRecordDialog {...defaultProps} />);
    
    fireEvent.change(screen.getByLabelText(/cost/i), {
      target: { value: '-50' },
    });

    const saveButton = screen.getByText(/save/i);
    fireEvent.click(saveButton);

    await waitFor(() => {
      expect(screen.getByText(/cost must be positive/i)).toBeInTheDocument();
    });
  });

  test('closes dialog on cancel', () => {
    renderWithProviders(<FuelRecordDialog {...defaultProps} />);
    
    const cancelButton = screen.getByText(/cancel/i);
    fireEvent.click(cancelButton);

    expect(mockOnClose).toHaveBeenCalled();
  });

  test('displays error message on save failure', async () => {
    global.fetch = jest.fn(() =>
      Promise.reject(new Error('Network error'))
    );

    renderWithProviders(<FuelRecordDialog {...defaultProps} />);
    
    fireEvent.change(screen.getByLabelText(/date/i), {
      target: { value: '2024-01-15' },
    });
    fireEvent.change(screen.getByLabelText(/mileage/i), {
      target: { value: '50500' },
    });

    const saveButton = screen.getByText(/save/i);
    fireEvent.click(saveButton);

    await waitFor(() => {
      expect(screen.getByText(/error saving fuel record/i)).toBeInTheDocument();
    });
  });

  test('displays trip computer MPG field', () => {
    renderWithProviders(<FuelRecordDialog {...defaultProps} />);
    expect(screen.getByLabelText(/trip computer mpg/i)).toBeInTheDocument();
  });

  test('compares calculated vs trip computer MPG', () => {
    renderWithProviders(<FuelRecordDialog {...defaultProps} />);
    
    fireEvent.change(screen.getByLabelText(/mileage/i), {
      target: { value: '50500' },
    });
    fireEvent.change(screen.getByLabelText(/litres/i), {
      target: { value: '45.5' },
    });
    fireEvent.change(screen.getByLabelText(/trip computer mpg/i), {
      target: { value: '52.0' },
    });

    // Calculated: 50 mpg, Trip: 52 mpg, Difference: 2 mpg
    expect(screen.getByText(/2.0 mpg difference/i)).toBeInTheDocument();
  });
});
