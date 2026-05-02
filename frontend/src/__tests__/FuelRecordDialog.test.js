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

// Mock useDistance hook
jest.mock('../hooks/useDistance', () => ({
  useDistance: () => ({
    convert: (v) => v,
    toKm: (v) => v,
    getLabel: () => 'mi',
  }),
}));

// Mock ReceiptUpload
jest.mock('../components/ReceiptUpload', () => (props) => (
  <div data-testid="receipt-upload">ReceiptUpload</div>
));

// Mock KnightRiderLoader
jest.mock('../components/KnightRiderLoader', () => () => <span>Loading...</span>);

const mockApi = {
  get: jest.fn().mockResolvedValue({ data: [] }),
  post: jest.fn().mockResolvedValue({ data: { id: 1 } }),
  put: jest.fn().mockResolvedValue({ data: { id: 1 } }),
};

const renderWithProviders = (component) => {
  const mockAuthContext = {
    user: { id: 1, email: 'test@example.com' },
    token: 'mock-token',
    api: mockApi,
  };

  return render(
    <AuthContext.Provider value={mockAuthContext}>
      {component}
    </AuthContext.Provider>
  );
};

describe('FuelRecordDialog Component', () => {
  const mockOnClose = jest.fn();

  const defaultProps = {
    open: true,
    onClose: mockOnClose,
    vehicleId: 1,
  };

  beforeEach(() => {
    jest.clearAllMocks();
    mockApi.get.mockResolvedValue({ data: [] });
    mockApi.post.mockResolvedValue({ data: { id: 1 } });
    mockApi.put.mockResolvedValue({ data: { id: 1 } });
  });

  test('renders dialog when open', () => {
    renderWithProviders(<FuelRecordDialog {...defaultProps} />);
    expect(screen.getByText('fuel.addRecord')).toBeInTheDocument();
  });

  test('displays all form fields', () => {
    renderWithProviders(<FuelRecordDialog {...defaultProps} />);

    expect(screen.getByLabelText(/fuel\.date/i)).toBeInTheDocument();
    expect(screen.getByLabelText(/fuel\.mileage/i)).toBeInTheDocument();
    expect(screen.getByLabelText(/fuel\.litres/i)).toBeInTheDocument();
    expect(screen.getByLabelText(/fuel\.cost/i)).toBeInTheDocument();
    expect(screen.getByLabelText(/fuel\.station/i)).toBeInTheDocument();
  });

  test('submits form with valid data', async () => {
    renderWithProviders(<FuelRecordDialog {...defaultProps} />);

    fireEvent.change(screen.getByLabelText(/fuel\.date/i), {
      target: { name: 'date', value: '2024-01-15' },
    });
    fireEvent.change(screen.getByLabelText(/fuel\.mileage/i), {
      target: { name: 'mileage', value: '50500' },
    });
    fireEvent.change(screen.getByLabelText(/fuel\.litres/i), {
      target: { name: 'litres', value: '45.5' },
    });
    fireEvent.change(screen.getByLabelText(/fuel\.cost/i), {
      target: { name: 'cost', value: '68.25' },
    });

    const saveButton = screen.getByText('common.save');
    fireEvent.click(saveButton);

    await waitFor(() => {
      expect(mockApi.post).toHaveBeenCalledWith(
        '/fuel-records',
        expect.objectContaining({ vehicleId: 1 })
      );
    });
  });

  test('displays fuel type field', () => {
    renderWithProviders(<FuelRecordDialog {...defaultProps} />);
    expect(screen.getAllByText(/fuel\.fuelType/i).length).toBeGreaterThan(0);
  });

  test('displays notes field', () => {
    renderWithProviders(<FuelRecordDialog {...defaultProps} />);
    expect(screen.getByLabelText(/common\.notes/i)).toBeInTheDocument();
  });

  test('displays receipt upload', () => {
    renderWithProviders(<FuelRecordDialog {...defaultProps} />);
    expect(screen.getByTestId('receipt-upload')).toBeInTheDocument();
  });

  test('populates form in edit mode', () => {
    const record = {
      id: 1,
      date: '2024-01-15',
      mileage: 50500,
      litres: 45.5,
      cost: 68.25,
      station: 'Shell',
      fuelType: 'Unleaded',
      notes: 'Test note',
    };

    renderWithProviders(
      <FuelRecordDialog {...defaultProps} record={record} />
    );

    expect(screen.getByText('fuelDialog.editRecord')).toBeInTheDocument();
    expect(screen.getByDisplayValue('2024-01-15')).toBeInTheDocument();
    expect(screen.getByDisplayValue('45.5')).toBeInTheDocument();
    expect(screen.getByDisplayValue('68.25')).toBeInTheDocument();
    expect(screen.getByDisplayValue('Shell')).toBeInTheDocument();
  });

  test('calls onClose with false on cancel', () => {
    renderWithProviders(<FuelRecordDialog {...defaultProps} />);

    const cancelButton = screen.getByText('common.cancel');
    fireEvent.click(cancelButton);

    expect(mockOnClose).toHaveBeenCalledWith(false);
  });

  test('calls onClose with true on successful save', async () => {
    renderWithProviders(<FuelRecordDialog {...defaultProps} />);

    fireEvent.change(screen.getByLabelText(/fuel\.date/i), {
      target: { name: 'date', value: '2024-01-15' },
    });
    fireEvent.change(screen.getByLabelText(/fuel\.mileage/i), {
      target: { name: 'mileage', value: '50500' },
    });
    fireEvent.change(screen.getByLabelText(/fuel\.litres/i), {
      target: { name: 'litres', value: '45.5' },
    });
    fireEvent.change(screen.getByLabelText(/fuel\.cost/i), {
      target: { name: 'cost', value: '68.25' },
    });

    const saveButton = screen.getByText('common.save');
    fireEvent.click(saveButton);

    await waitFor(() => {
      expect(mockOnClose).toHaveBeenCalledWith(true);
    });
  });

  test('uses PUT for editing existing record', async () => {
    const record = {
      id: 42,
      date: '2024-01-15',
      mileage: 50500,
      litres: 45.5,
      cost: 68.25,
      station: 'Shell',
      fuelType: 'Unleaded',
      notes: '',
    };

    renderWithProviders(
      <FuelRecordDialog {...defaultProps} record={record} />
    );

    const saveButton = screen.getByText('common.save');
    fireEvent.click(saveButton);

    await waitFor(() => {
      expect(mockApi.put).toHaveBeenCalledWith(
        '/fuel-records/42',
        expect.objectContaining({ vehicleId: 1 })
      );
    });
  });

  test('does not render when closed', () => {
    renderWithProviders(<FuelRecordDialog {...defaultProps} open={false} />);
    expect(screen.queryByText('fuel.addRecord')).not.toBeInTheDocument();
  });

  test('loads fuel types on mount', async () => {
    mockApi.get.mockResolvedValueOnce({ data: ['Unleaded', 'Diesel', 'Super'] });
    renderWithProviders(<FuelRecordDialog {...defaultProps} />);

    await waitFor(() => {
      expect(mockApi.get).toHaveBeenCalledWith('/fuel-records/fuel-types');
    });
  });
});
