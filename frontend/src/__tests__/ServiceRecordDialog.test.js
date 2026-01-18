import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import ServiceRecordDialog from '../components/ServiceRecordDialog';
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

describe('ServiceRecordDialog Component', () => {
  const mockOnClose = jest.fn();
  const mockOnSave = jest.fn();

  const defaultProps = {
    open: true,
    onClose: mockOnClose,
    onSave: mockOnSave,
    vehicleId: 1,
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
    renderWithProviders(<ServiceRecordDialog {...defaultProps} />);
    expect(screen.getByText('serviceRecord.newTitle')).toBeInTheDocument();
  });

  test('does not render when closed', () => {
    renderWithProviders(<ServiceRecordDialog {...defaultProps} open={false} />);
    expect(screen.queryByText('serviceRecord.newTitle')).not.toBeInTheDocument();
  });

  test('displays all form fields', () => {
    renderWithProviders(<ServiceRecordDialog {...defaultProps} />);
    
    expect(screen.getByLabelText(/service date/i)).toBeInTheDocument();
    expect(screen.getByLabelText(/service type/i)).toBeInTheDocument();
    expect(screen.getByLabelText(/description/i)).toBeInTheDocument();
    expect(screen.getByLabelText(/labour cost/i)).toBeInTheDocument();
    expect(screen.getByLabelText(/parts cost/i)).toBeInTheDocument();
    expect(screen.getByLabelText(/mileage/i)).toBeInTheDocument();
  });

  test('submits form with valid data', async () => {
    renderWithProviders(<ServiceRecordDialog {...defaultProps} />);
    
    fireEvent.change(screen.getByLabelText(/service date/i), {
      target: { value: '2024-01-15' },
    });
    fireEvent.change(screen.getByLabelText(/service type/i), {
      target: { value: 'Annual Service' },
    });
    fireEvent.change(screen.getByLabelText(/labour cost/i), {
      target: { value: '150' },
    });
    fireEvent.change(screen.getByLabelText(/parts cost/i), {
      target: { value: '100' },
    });
    fireEvent.change(screen.getByLabelText(/mileage/i), {
      target: { value: '50000' },
    });

    const saveButton = screen.getByText(/save/i);
    fireEvent.click(saveButton);

    await waitFor(() => {
      expect(global.fetch).toHaveBeenCalledWith(
        expect.stringContaining('/service-records'),
        expect.objectContaining({
          method: 'POST',
          body: expect.stringContaining('"serviceType":"Annual Service"'),
        })
      );
      expect(mockOnSave).toHaveBeenCalled();
    });
  });

  test('calculates total cost automatically', () => {
    renderWithProviders(<ServiceRecordDialog {...defaultProps} />);
    
    fireEvent.change(screen.getByLabelText(/labour cost/i), {
      target: { value: '150' },
    });
    fireEvent.change(screen.getByLabelText(/parts cost/i), {
      target: { value: '100' },
    });

    expect(screen.getByText(/total: £250.00/i)).toBeInTheDocument();
  });

  test('validates required fields', async () => {
    renderWithProviders(<ServiceRecordDialog {...defaultProps} />);
    
    const saveButton = screen.getByText(/save/i);
    fireEvent.click(saveButton);

    await waitFor(() => {
      expect(screen.getByText(/service date is required/i)).toBeInTheDocument();
      expect(screen.getByText(/service type is required/i)).toBeInTheDocument();
    });

    expect(global.fetch).not.toHaveBeenCalled();
  });

  test('populates form in edit mode', () => {
    const serviceRecord = {
      id: 1,
      serviceDate: '2024-01-15',
      serviceType: 'Annual Service',
      description: 'Full service',
      labourCost: 150,
      partsCost: 100,
      mileage: 50000,
    };

    renderWithProviders(
      <ServiceRecordDialog {...defaultProps} serviceRecord={serviceRecord} />
    );
    
    expect(screen.getByDisplayValue('2024-01-15')).toBeInTheDocument();
    expect(screen.getByDisplayValue('Annual Service')).toBeInTheDocument();
    expect(screen.getByDisplayValue('Full service')).toBeInTheDocument();
    expect(screen.getByDisplayValue('150')).toBeInTheDocument();
    expect(screen.getByDisplayValue('100')).toBeInTheDocument();
  });

  test('updates existing service record', async () => {
    const serviceRecord = {
      id: 1,
      serviceDate: '2024-01-15',
      serviceType: 'Annual Service',
      labourCost: 150,
      partsCost: 100,
    };

    renderWithProviders(
      <ServiceRecordDialog {...defaultProps} serviceRecord={serviceRecord} />
    );
    
    fireEvent.change(screen.getByLabelText(/labour cost/i), {
      target: { value: '200' },
    });

    const saveButton = screen.getByText(/save/i);
    fireEvent.click(saveButton);

    await waitFor(() => {
      expect(global.fetch).toHaveBeenCalledWith(
        expect.stringContaining('/service-records/1'),
        expect.objectContaining({
          method: 'PUT',
        })
      );
    });
  });

  test('closes dialog on cancel', () => {
    renderWithProviders(<ServiceRecordDialog {...defaultProps} />);
    
    const cancelButton = screen.getByText(/cancel/i);
    fireEvent.click(cancelButton);

    expect(mockOnClose).toHaveBeenCalled();
  });

  test('displays service type options', () => {
    renderWithProviders(<ServiceRecordDialog {...defaultProps} />);
    
    const serviceTypeSelect = screen.getByLabelText(/service type/i);
    fireEvent.click(serviceTypeSelect);

    expect(screen.getByText(/annual service/i)).toBeInTheDocument();
    expect(screen.getByText(/oil change/i)).toBeInTheDocument();
    expect(screen.getByText(/brake service/i)).toBeInTheDocument();
    expect(screen.getByText(/tire rotation/i)).toBeInTheDocument();
  });

  test('handles file upload for receipts', async () => {
    renderWithProviders(<ServiceRecordDialog {...defaultProps} />);
    
    const file = new File(['receipt'], 'receipt.pdf', { type: 'application/pdf' });
    const fileInput = screen.getByLabelText(/upload receipt/i);

    fireEvent.change(fileInput, { target: { files: [file] } });

    await waitFor(() => {
      expect(screen.getByText('receipt.pdf')).toBeInTheDocument();
    });
  });

  test('displays error message on save failure', async () => {
    global.fetch = jest.fn(() =>
      Promise.reject(new Error('Network error'))
    );

    renderWithProviders(<ServiceRecordDialog {...defaultProps} />);
    
    fireEvent.change(screen.getByLabelText(/service date/i), {
      target: { value: '2024-01-15' },
    });
    fireEvent.change(screen.getByLabelText(/service type/i), {
      target: { value: 'Annual Service' },
    });

    const saveButton = screen.getByText(/save/i);
    fireEvent.click(saveButton);

    await waitFor(() => {
      expect(screen.getByText(/error saving service record/i)).toBeInTheDocument();
    });
  });

  test('validates positive cost values', async () => {
    renderWithProviders(<ServiceRecordDialog {...defaultProps} />);
    
    fireEvent.change(screen.getByLabelText(/labour cost/i), {
      target: { value: '-50' },
    });

    const saveButton = screen.getByText(/save/i);
    fireEvent.click(saveButton);

    await waitFor(() => {
      expect(screen.getByText(/cost must be positive/i)).toBeInTheDocument();
    });
  });

  test('displays workshop field', () => {
    renderWithProviders(<ServiceRecordDialog {...defaultProps} />);
    expect(screen.getByLabelText(/workshop/i)).toBeInTheDocument();
  });

  test('displays next service date field', () => {
    renderWithProviders(<ServiceRecordDialog {...defaultProps} />);
    expect(screen.getByLabelText(/next service date/i)).toBeInTheDocument();
  });

  test('adds additional costs field', () => {
    renderWithProviders(<ServiceRecordDialog {...defaultProps} />);
    
    const addCostButton = screen.getByText(/add cost/i);
    fireEvent.click(addCostButton);

    expect(screen.getByLabelText(/additional cost/i)).toBeInTheDocument();
  });

  test('calculates total with additional costs', () => {
    renderWithProviders(<ServiceRecordDialog {...defaultProps} />);
    
    fireEvent.change(screen.getByLabelText(/labour cost/i), {
      target: { value: '150' },
    });
    fireEvent.change(screen.getByLabelText(/parts cost/i), {
      target: { value: '100' },
    });

    const addCostButton = screen.getByText(/add cost/i);
    fireEvent.click(addCostButton);

    fireEvent.change(screen.getByLabelText(/additional cost/i), {
      target: { value: '50' },
    });

    expect(screen.getByText(/total: £300.00/i)).toBeInTheDocument();
  });

  test('displays parts list', () => {
    renderWithProviders(<ServiceRecordDialog {...defaultProps} />);
    
    const addPartButton = screen.getByText(/add part/i);
    fireEvent.click(addPartButton);

    expect(screen.getByLabelText(/part name/i)).toBeInTheDocument();
    expect(screen.getByLabelText(/part cost/i)).toBeInTheDocument();
  });
});
