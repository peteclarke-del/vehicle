import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import InsuranceDialog from '../components/InsuranceDialog';

jest.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: (key) => key,
  }),
}));

const mockApi = {
  post: jest.fn(),
  put: jest.fn(),
};

describe('InsuranceDialog Component', () => {
  const defaultProps = {
    open: true,
    insurance: null,
    vehicleId: 1,
    onClose: jest.fn(),
  };

  beforeEach(() => {
    jest.clearAllMocks();
  });

  test('renders create mode correctly', () => {
    render(<InsuranceDialog {...defaultProps} />);

    expect(screen.getByText('insurance.addInsurance')).toBeInTheDocument();
    expect(screen.getByLabelText('insurance.provider')).toBeInTheDocument();
    expect(screen.getByLabelText('insurance.policyNumber')).toBeInTheDocument();
    expect(screen.getByLabelText('insurance.coverageType')).toBeInTheDocument();
    expect(screen.getByLabelText('insurance.annualCost')).toBeInTheDocument();
  });

  test('renders edit mode with existing data', () => {
    const existingInsurance = {
      id: 1,
      provider: 'Test Insurance',
      policyNumber: 'POL123',
      coverageType: 'Comprehensive',
      annualCost: 650.00,
      startDate: '2026-01-01',
      expiryDate: '2027-01-01',
      notes: 'Test notes',
    };

    render(
      <InsuranceDialog
        {...defaultProps}
        insurance={existingInsurance}
      />
    );

    expect(screen.getByText('insurance.editInsurance')).toBeInTheDocument();
    expect(screen.getByDisplayValue('Test Insurance')).toBeInTheDocument();
    expect(screen.getByDisplayValue('POL123')).toBeInTheDocument();
    expect(screen.getByDisplayValue('650')).toBeInTheDocument();
  });

  test('validates required fields', async () => {
    render(<InsuranceDialog {...defaultProps} />);

    const saveButton = screen.getByText('common.save');
    fireEvent.click(saveButton);

    await waitFor(() => {
      expect(mockApi.post).not.toHaveBeenCalled();
    });
  });

  test('submits form with valid data in create mode', async () => {
    mockApi.post.mockResolvedValueOnce({});

    render(<InsuranceDialog {...defaultProps} />);

    // Fill form
    fireEvent.change(screen.getByLabelText('insurance.provider'), {
      target: { value: 'New Insurance Co' }
    });
    fireEvent.change(screen.getByLabelText('insurance.policyNumber'), {
      target: { value: 'POL456' }
    });
    fireEvent.change(screen.getByLabelText('insurance.coverageType'), {
      target: { value: 'Third Party' }
    });
    fireEvent.change(screen.getByLabelText('insurance.annualCost'), {
      target: { value: '500' }
    });

    const saveButton = screen.getByText('common.save');
    fireEvent.click(saveButton);

    await waitFor(() => {
      expect(mockApi.post).toHaveBeenCalledWith(
        '/insurance',
        expect.objectContaining({
          vehicleId: 1,
          provider: 'New Insurance Co',
          policyNumber: 'POL456',
          coverageType: 'Third Party',
          annualCost: 500,
        })
      );
      expect(defaultProps.onClose).toHaveBeenCalledWith(true);
    });
  });

  test('submits form with valid data in edit mode', async () => {
    const existingInsurance = {
      id: 1,
      provider: 'Test Insurance',
      policyNumber: 'POL123',
      coverageType: 'Comprehensive',
      annualCost: 650.00,
      startDate: '2026-01-01',
      expiryDate: '2027-01-01',
    };

    mockApi.put.mockResolvedValueOnce({});

    render(
      <InsuranceDialog
        {...defaultProps}
        insurance={existingInsurance}
      />
    );

    // Update provider
    fireEvent.change(screen.getByDisplayValue('Test Insurance'), {
      target: { value: 'Updated Insurance' }
    });

    const saveButton = screen.getByText('common.save');
    fireEvent.click(saveButton);

    await waitFor(() => {
      expect(mockApi.put).toHaveBeenCalledWith(
        '/insurance/1',
        expect.objectContaining({
          provider: 'Updated Insurance',
        })
      );
      expect(defaultProps.onClose).toHaveBeenCalledWith(true);
    });
  });

  test('closes dialog on cancel', () => {
    render(<InsuranceDialog {...defaultProps} />);

    const cancelButton = screen.getByText('common.cancel');
    fireEvent.click(cancelButton);

    expect(defaultProps.onClose).toHaveBeenCalledWith(false);
  });

  test('handles API errors gracefully', async () => {
    mockApi.post.mockRejectedValueOnce(new Error('API Error'));

    const consoleError = jest.spyOn(console, 'error').mockImplementation();

    render(<InsuranceDialog {...defaultProps} />);

    // Fill minimum required fields
    fireEvent.change(screen.getByLabelText('insurance.provider'), {
      target: { value: 'Test' }
    });
    fireEvent.change(screen.getByLabelText('insurance.annualCost'), {
      target: { value: '500' }
    });

    const saveButton = screen.getByText('common.save');
    fireEvent.click(saveButton);

    await waitFor(() => {
      expect(consoleError).toHaveBeenCalled();
    });

    consoleError.mockRestore();
  });

  test('formats currency input correctly', () => {
    render(<InsuranceDialog {...defaultProps} />);

    const costInput = screen.getByLabelText('insurance.annualCost');
    
    fireEvent.change(costInput, {
      target: { value: '1234.56' }
    });

    expect(costInput.value).toBe('1234.56');
  });

  test('validates date range (start before end)', async () => {
    render(<InsuranceDialog {...defaultProps} />);

    const startDateInput = screen.getByLabelText('insurance.startDate');
    const expiryDateInput = screen.getByLabelText('insurance.expiryDate');

    fireEvent.change(startDateInput, {
      target: { value: '2027-01-01' }
    });
    fireEvent.change(expiryDateInput, {
      target: { value: '2026-01-01' } // Before start date
    });

    const saveButton = screen.getByText('common.save');
    fireEvent.click(saveButton);

    await waitFor(() => {
      expect(mockApi.post).not.toHaveBeenCalled();
    });
  });
});
