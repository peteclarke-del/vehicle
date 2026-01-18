import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import MotRecordDialog from '../components/MotRecordDialog';
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

describe('MotRecordDialog Component', () => {
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
    renderWithProviders(<MotRecordDialog {...defaultProps} />);
    expect(screen.getByText('motRecord.newTitle')).toBeInTheDocument();
  });

  test('displays all form fields', () => {
    renderWithProviders(<MotRecordDialog {...defaultProps} />);
    
    expect(screen.getByLabelText(/test date/i)).toBeInTheDocument();
    expect(screen.getByLabelText(/expiry date/i)).toBeInTheDocument();
    expect(screen.getByLabelText(/result/i)).toBeInTheDocument();
    expect(screen.getByLabelText(/mileage/i)).toBeInTheDocument();
    expect(screen.getByLabelText(/test center/i)).toBeInTheDocument();
    expect(screen.getByLabelText(/cost/i)).toBeInTheDocument();
  });

  test('submits form with valid data', async () => {
    renderWithProviders(<MotRecordDialog {...defaultProps} />);
    
    fireEvent.change(screen.getByLabelText(/test date/i), {
      target: { value: '2024-01-15' },
    });
    fireEvent.change(screen.getByLabelText(/expiry date/i), {
      target: { value: '2025-01-15' },
    });
    fireEvent.change(screen.getByLabelText(/result/i), {
      target: { value: 'PASSED' },
    });
    fireEvent.change(screen.getByLabelText(/mileage/i), {
      target: { value: '50000' },
    });
    fireEvent.change(screen.getByLabelText(/cost/i), {
      target: { value: '54.85' },
    });

    const saveButton = screen.getByText(/save/i);
    fireEvent.click(saveButton);

    await waitFor(() => {
      expect(global.fetch).toHaveBeenCalledWith(
        expect.stringContaining('/mot-records'),
        expect.objectContaining({
          method: 'POST',
          body: expect.stringContaining('"testResult":"PASSED"'),
        })
      );
      expect(mockOnSave).toHaveBeenCalled();
    });
  });

  test('validates required fields', async () => {
    renderWithProviders(<MotRecordDialog {...defaultProps} />);
    
    const saveButton = screen.getByText(/save/i);
    fireEvent.click(saveButton);

    await waitFor(() => {
      expect(screen.getByText(/test date is required/i)).toBeInTheDocument();
      expect(screen.getByText(/expiry date is required/i)).toBeInTheDocument();
      expect(screen.getByText(/result is required/i)).toBeInTheDocument();
    });

    expect(global.fetch).not.toHaveBeenCalled();
  });

  test('displays result options', () => {
    renderWithProviders(<MotRecordDialog {...defaultProps} />);
    
    const resultSelect = screen.getByLabelText(/result/i);
    fireEvent.click(resultSelect);

    expect(screen.getByText(/passed/i)).toBeInTheDocument();
    expect(screen.getByText(/failed/i)).toBeInTheDocument();
  });

  test('shows advisory items field for passed test', () => {
    renderWithProviders(<MotRecordDialog {...defaultProps} />);
    
    fireEvent.change(screen.getByLabelText(/result/i), {
      target: { value: 'PASSED' },
    });

    expect(screen.getByLabelText(/advisory items/i)).toBeInTheDocument();
  });

  test('shows failure items field for failed test', () => {
    renderWithProviders(<MotRecordDialog {...defaultProps} />);
    
    fireEvent.change(screen.getByLabelText(/result/i), {
      target: { value: 'FAILED' },
    });

    expect(screen.getByLabelText(/failure items/i)).toBeInTheDocument();
  });

  test('adds multiple advisory items', () => {
    renderWithProviders(<MotRecordDialog {...defaultProps} />);
    
    fireEvent.change(screen.getByLabelText(/result/i), {
      target: { value: 'PASSED' },
    });

    const addButton = screen.getByText(/add advisory/i);
    fireEvent.click(addButton);
    fireEvent.click(addButton);

    const advisoryInputs = screen.getAllByLabelText(/advisory item/i);
    expect(advisoryInputs).toHaveLength(2);
  });

  test('adds multiple failure items', () => {
    renderWithProviders(<MotRecordDialog {...defaultProps} />);
    
    fireEvent.change(screen.getByLabelText(/result/i), {
      target: { value: 'FAILED' },
    });

    const addButton = screen.getByText(/add failure/i);
    fireEvent.click(addButton);
    fireEvent.click(addButton);

    const failureInputs = screen.getAllByLabelText(/failure item/i);
    expect(failureInputs).toHaveLength(2);
  });

  test('populates form in edit mode', () => {
    const motRecord = {
      id: 1,
      testDate: '2024-01-15',
      expiryDate: '2025-01-15',
      testResult: 'PASSED',
      mileage: 50000,
      testCenter: 'MOT Centre Ltd',
      cost: 54.85,
      advisoryItems: ['Brake pads worn', 'Tyre tread low'],
    };

    renderWithProviders(
      <MotRecordDialog {...defaultProps} motRecord={motRecord} />
    );
    
    expect(screen.getByDisplayValue('2024-01-15')).toBeInTheDocument();
    expect(screen.getByDisplayValue('2025-01-15')).toBeInTheDocument();
    expect(screen.getByDisplayValue('PASSED')).toBeInTheDocument();
    expect(screen.getByDisplayValue('MOT Centre Ltd')).toBeInTheDocument();
    expect(screen.getByDisplayValue('Brake pads worn')).toBeInTheDocument();
  });

  test('validates expiry date is after test date', async () => {
    renderWithProviders(<MotRecordDialog {...defaultProps} />);
    
    fireEvent.change(screen.getByLabelText(/test date/i), {
      target: { value: '2024-01-15' },
    });
    fireEvent.change(screen.getByLabelText(/expiry date/i), {
      target: { value: '2023-01-15' },
    });

    const saveButton = screen.getByText(/save/i);
    fireEvent.click(saveButton);

    await waitFor(() => {
      expect(screen.getByText(/expiry date must be after test date/i)).toBeInTheDocument();
    });
  });

  test('displays MOT test number field', () => {
    renderWithProviders(<MotRecordDialog {...defaultProps} />);
    expect(screen.getByLabelText(/mot test number/i)).toBeInTheDocument();
  });

  test('displays fetch from DVSA button', () => {
    renderWithProviders(<MotRecordDialog {...defaultProps} />);
    expect(screen.getByText(/fetch from dvsa/i)).toBeInTheDocument();
  });

  test('fetches MOT data from DVSA', async () => {
    global.fetch = jest.fn(() =>
      Promise.resolve({
        ok: true,
        json: () => Promise.resolve({
          testDate: '2024-01-15',
          expiryDate: '2025-01-15',
          testResult: 'PASSED',
          mileage: 50000,
          motTestNumber: 'MOT123456789',
          advisoryItems: ['Brake pads worn'],
        }),
      })
    );

    renderWithProviders(<MotRecordDialog {...defaultProps} />);
    
    const testNumberInput = screen.getByLabelText(/mot test number/i);
    fireEvent.change(testNumberInput, {
      target: { value: 'MOT123456789' },
    });

    const fetchButton = screen.getByText(/fetch from dvsa/i);
    fireEvent.click(fetchButton);

    await waitFor(() => {
      expect(screen.getByDisplayValue('2024-01-15')).toBeInTheDocument();
      expect(screen.getByDisplayValue('2025-01-15')).toBeInTheDocument();
      expect(screen.getByDisplayValue('50000')).toBeInTheDocument();
    });
  });

  test('handles receipt upload', async () => {
    renderWithProviders(<MotRecordDialog {...defaultProps} />);
    
    const file = new File(['receipt'], 'mot-receipt.pdf', { type: 'application/pdf' });
    const fileInput = screen.getByLabelText(/upload receipt/i);

    fireEvent.change(fileInput, { target: { files: [file] } });

    await waitFor(() => {
      expect(screen.getByText('mot-receipt.pdf')).toBeInTheDocument();
    });
  });

  test('displays certificate upload field', () => {
    renderWithProviders(<MotRecordDialog {...defaultProps} />);
    expect(screen.getByLabelText(/upload certificate/i)).toBeInTheDocument();
  });

  test('validates positive cost', async () => {
    renderWithProviders(<MotRecordDialog {...defaultProps} />);
    
    fireEvent.change(screen.getByLabelText(/cost/i), {
      target: { value: '-10' },
    });

    const saveButton = screen.getByText(/save/i);
    fireEvent.click(saveButton);

    await waitFor(() => {
      expect(screen.getByText(/cost must be positive/i)).toBeInTheDocument();
    });
  });

  test('closes dialog on cancel', () => {
    renderWithProviders(<MotRecordDialog {...defaultProps} />);
    
    const cancelButton = screen.getByText(/cancel/i);
    fireEvent.click(cancelButton);

    expect(mockOnClose).toHaveBeenCalled();
  });

  test('displays error message on save failure', async () => {
    global.fetch = jest.fn(() =>
      Promise.reject(new Error('Network error'))
    );

    renderWithProviders(<MotRecordDialog {...defaultProps} />);
    
    fireEvent.change(screen.getByLabelText(/test date/i), {
      target: { value: '2024-01-15' },
    });

    const saveButton = screen.getByText(/save/i);
    fireEvent.click(saveButton);

    await waitFor(() => {
      expect(screen.getByText(/error saving mot record/i)).toBeInTheDocument();
    });
  });

  test('displays tester name field', () => {
    renderWithProviders(<MotRecordDialog {...defaultProps} />);
    expect(screen.getByLabelText(/tester name/i)).toBeInTheDocument();
  });

  test('displays retest indicator checkbox', () => {
    renderWithProviders(<MotRecordDialog {...defaultProps} />);
    expect(screen.getByLabelText(/retest/i)).toBeInTheDocument();
  });

  test('displays notes field', () => {
    renderWithProviders(<MotRecordDialog {...defaultProps} />);
    expect(screen.getByLabelText(/notes/i)).toBeInTheDocument();
  });

  test('removes advisory item', () => {
    renderWithProviders(<MotRecordDialog {...defaultProps} />);
    
    fireEvent.change(screen.getByLabelText(/result/i), {
      target: { value: 'PASSED' },
    });

    const addButton = screen.getByText(/add advisory/i);
    fireEvent.click(addButton);
    fireEvent.click(addButton);

    const removeButtons = screen.getAllByLabelText(/remove/i);
    fireEvent.click(removeButtons[0]);

    const advisoryInputs = screen.getAllByLabelText(/advisory item/i);
    expect(advisoryInputs).toHaveLength(1);
  });
});
