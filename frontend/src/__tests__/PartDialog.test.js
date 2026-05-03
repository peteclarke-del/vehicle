import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import PartDialog from '../components/PartDialog';
import { AuthContext } from '../context/AuthContext';
import '@testing-library/jest-dom';

const mockSetDefaultVehicle = jest.fn();

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

jest.mock('../contexts/UserPreferencesContext', () => ({
  useUserPreferences: () => ({
    setDefaultVehicle: mockSetDefaultVehicle,
  }),
}));

jest.mock('../components/FilteredVehicleSelector', () => (props) => (
  <button type="button" onClick={() => props.onVehicleChange(2)}>
    move-selector
  </button>
));

// Mock ReceiptUpload
jest.mock('../components/ReceiptUpload', () => (props) => (
  <div data-testid="receipt-upload">ReceiptUpload</div>
));

// Mock UrlScraper
jest.mock('../components/UrlScraper', () => (props) => (
  <div data-testid="url-scraper">UrlScraper</div>
));

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

describe('PartDialog Component', () => {
  const mockOnClose = jest.fn();

  const defaultProps = {
    open: true,
    onClose: mockOnClose,
    vehicleId: 1,
  };

  beforeEach(() => {
    jest.clearAllMocks();
    mockSetDefaultVehicle.mockReset();
    mockApi.get.mockResolvedValue({ data: [] });
    mockApi.post.mockResolvedValue({ data: { id: 1 } });
    mockApi.put.mockResolvedValue({ data: { id: 1 } });
  });

  test('renders dialog when open', () => {
    renderWithProviders(<PartDialog {...defaultProps} />);
    expect(screen.getByText('parts.addPart')).toBeInTheDocument();
  });

  test('displays all form fields', () => {
    renderWithProviders(<PartDialog {...defaultProps} />);

    expect(screen.getByLabelText(/parts\.description/i)).toBeInTheDocument();
    expect(screen.getByLabelText(/parts\.category/i)).toBeInTheDocument();
    expect(screen.getByLabelText(/parts\.price/i)).toBeInTheDocument();
    expect(screen.getByLabelText(/common\.quantity/i)).toBeInTheDocument();
    expect(screen.getByLabelText(/common\.supplier/i)).toBeInTheDocument();
  });

  test('submits form with valid data', async () => {
    renderWithProviders(<PartDialog {...defaultProps} />);

    fireEvent.change(screen.getByLabelText(/parts\.description/i), {
      target: { name: 'description', value: 'Brake Pads' },
    });
    fireEvent.change(screen.getByLabelText(/parts\.price/i), {
      target: { name: 'price', value: '45.99' },
    });

    const saveButton = screen.getByText('common.save');
    fireEvent.click(saveButton);

    await waitFor(() => {
      expect(mockApi.post).toHaveBeenCalledWith(
        '/parts',
        expect.objectContaining({ vehicleId: 1, description: 'Brake Pads' })
      );
    });
  });

  test('displays notes field', () => {
    renderWithProviders(<PartDialog {...defaultProps} />);
    expect(screen.getByLabelText(/common\.notes/i)).toBeInTheDocument();
  });

  test('displays purchase date field', () => {
    renderWithProviders(<PartDialog {...defaultProps} />);
    expect(screen.getByLabelText(/common\.purchaseDate/i)).toBeInTheDocument();
  });

  test('displays installation date field', () => {
    renderWithProviders(<PartDialog {...defaultProps} />);
    expect(screen.getByLabelText(/common\.installationDate/i)).toBeInTheDocument();
  });

  test('displays warranty field', () => {
    renderWithProviders(<PartDialog {...defaultProps} />);
    expect(screen.getByLabelText(/common\.warranty/i)).toBeInTheDocument();
  });

  test('displays part number field', () => {
    renderWithProviders(<PartDialog {...defaultProps} />);
    expect(screen.getByLabelText(/common\.partNumber/i)).toBeInTheDocument();
  });

  test('displays manufacturer field', () => {
    renderWithProviders(<PartDialog {...defaultProps} />);
    expect(screen.getByLabelText(/common\.manufacturer/i)).toBeInTheDocument();
  });

  test('displays receipt upload', () => {
    renderWithProviders(<PartDialog {...defaultProps} />);
    expect(screen.getByTestId('receipt-upload')).toBeInTheDocument();
  });

  test('displays URL scraper', () => {
    renderWithProviders(<PartDialog {...defaultProps} />);
    expect(screen.getByTestId('url-scraper')).toBeInTheDocument();
  });

  test('displays MOT record link', () => {
    renderWithProviders(<PartDialog {...defaultProps} />);
    expect(screen.getByLabelText(/parts\.motRecord/i)).toBeInTheDocument();
  });

  test('displays service record link', () => {
    renderWithProviders(<PartDialog {...defaultProps} />);
    expect(screen.getByLabelText(/service\.serviceRecord/i)).toBeInTheDocument();
  });

  test('populates form in edit mode', () => {
    const part = {
      id: 1,
      description: 'Oil Filter',
      partNumber: 'OF-123',
      manufacturer: 'Bosch',
      price: 12.99,
      quantity: 1,
      supplier: 'AutoParts Co',
      warranty: '1 year',
      notes: 'Test note',
    };

    renderWithProviders(<PartDialog {...defaultProps} part={part} />);

    expect(screen.getByText('parts.editPart')).toBeInTheDocument();
    expect(screen.getByDisplayValue('Oil Filter')).toBeInTheDocument();
    expect(screen.getByDisplayValue('OF-123')).toBeInTheDocument();
    expect(screen.getByDisplayValue('Bosch')).toBeInTheDocument();
    expect(screen.getByDisplayValue('12.99')).toBeInTheDocument();
    expect(screen.getByDisplayValue('AutoParts Co')).toBeInTheDocument();
  });

  test('calls onClose with false on cancel', () => {
    renderWithProviders(<PartDialog {...defaultProps} />);

    const cancelButton = screen.getByText('common.cancel');
    fireEvent.click(cancelButton);

    expect(mockOnClose).toHaveBeenCalledWith(false);
  });

  test('uses PUT for editing existing part', async () => {
    const part = {
      id: 42,
      description: 'Oil Filter',
      price: 12.99,
      quantity: 1,
    };

    renderWithProviders(<PartDialog {...defaultProps} part={part} />);

    const saveButton = screen.getByText('common.save');
    fireEvent.click(saveButton);

    await waitFor(() => {
      expect(mockApi.put).toHaveBeenCalledWith(
        '/parts/42',
        expect.objectContaining({ vehicleId: 1 })
      );
    });
  });

  test('moves edited part to another vehicle and updates default selection', async () => {
    const onVehicleMoved = jest.fn();
    const part = {
      id: 42,
      vehicleId: 1,
      description: 'Oil Filter',
      price: 12.99,
      quantity: 1,
    };

    renderWithProviders(
      <PartDialog
        {...defaultProps}
        part={part}
        onVehicleMoved={onVehicleMoved}
      />
    );

    fireEvent.click(screen.getByRole('button', { name: 'move-selector' }));

    await waitFor(() => {
      expect(mockApi.put).toHaveBeenCalledWith('/parts/42', {
        vehicleId: 2,
        motRecordId: null,
        serviceRecordId: null,
      });
    });

    expect(mockSetDefaultVehicle).toHaveBeenCalledWith(2);
    expect(onVehicleMoved).toHaveBeenCalledWith(2);
  });

  test('does not render when closed', () => {
    renderWithProviders(<PartDialog {...defaultProps} open={false} />);
    expect(screen.queryByText('parts.addPart')).not.toBeInTheDocument();
  });

  test('loads part categories on mount', async () => {
    mockApi.get.mockResolvedValue({ data: [] });
    renderWithProviders(<PartDialog {...defaultProps} />);

    await waitFor(() => {
      expect(mockApi.get).toHaveBeenCalledWith(
        '/part-categories',
        expect.any(Object)
      );
    });
  });
});
