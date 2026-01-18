import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { BrowserRouter } from 'react-router-dom';
import ServiceRecords from '../pages/ServiceRecords';
import { AuthContext } from '../context/AuthContext';

jest.mock('react-i18next', () => ({
  useTranslation: () => ({ t: (key) => key }),
}));

jest.mock('../hooks/useApiData', () => ({
  useApiData: jest.fn(),
}));

const { useApiData } = require('../hooks/useApiData');

const mockApi = {
  get: jest.fn(),
  post: jest.fn(),
  put: jest.fn(),
  delete: jest.fn(),
};

describe('ServiceRecords Component', () => {
  const mockAuthContext = {
    user: { id: 1, email: 'test@example.com' },
    logout: jest.fn(),
  };

  const mockVehicles = [
    { id: 1, registration: 'ABC123', make: 'Toyota', model: 'Corolla' },
    { id: 2, registration: 'XYZ789', make: 'Honda', model: 'Civic' },
  ];

  const mockServiceRecords = [
    {
      id: 1,
      vehicleId: 1,
      description: 'Annual Service',
      serviceDate: '2026-01-10',
      mileage: 50000,
      labourCost: 150.00,
      partsCost: 75.00,
      totalCost: 225.00,
      serviceType: 'scheduled',
      garage: 'Test Garage',
    },
    {
      id: 2,
      vehicleId: 1,
      description: 'Oil Change',
      serviceDate: '2025-10-15',
      mileage: 48000,
      labourCost: 50.00,
      partsCost: 30.00,
      totalCost: 80.00,
      serviceType: 'oil_change',
      garage: 'Quick Oil',
    },
  ];

  beforeEach(() => {
    jest.clearAllMocks();
  });

  const renderWithProviders = (component) => {
    return render(
      <BrowserRouter>
        <AuthContext.Provider value={mockAuthContext}>
          {component}
        </AuthContext.Provider>
      </BrowserRouter>
    );
  };

  test('renders service records page title', () => {
    useApiData
      .mockReturnValueOnce({ data: mockVehicles, loading: false, error: null })
      .mockReturnValueOnce({ data: mockServiceRecords, loading: false, error: null });

    renderWithProviders(<ServiceRecords />);

    expect(screen.getByText('serviceRecords.title')).toBeInTheDocument();
  });

  test('displays loading state', () => {
    useApiData.mockReturnValue({ data: null, loading: true, error: null });

    renderWithProviders(<ServiceRecords />);

    expect(screen.getByRole('progressbar')).toBeInTheDocument();
  });

  test('displays error message', () => {
    useApiData
      .mockReturnValueOnce({ data: mockVehicles, loading: false, error: null })
      .mockReturnValueOnce({ data: null, loading: false, error: 'Failed to fetch' });

    renderWithProviders(<ServiceRecords />);

    expect(screen.getByText(/error/i)).toBeInTheDocument();
  });

  test('loads and displays service records', async () => {
    useApiData
      .mockReturnValueOnce({ data: mockVehicles, loading: false, error: null })
      .mockReturnValueOnce({ data: mockServiceRecords, loading: false, error: null });

    renderWithProviders(<ServiceRecords />);

    await waitFor(() => {
      expect(screen.getByText('Annual Service')).toBeInTheDocument();
      expect(screen.getByText('Oil Change')).toBeInTheDocument();
      expect(screen.getByText('£225.00')).toBeInTheDocument();
      expect(screen.getByText('£80.00')).toBeInTheDocument();
    });
  });

  test('opens add dialog when add button clicked', async () => {
    useApiData
      .mockReturnValueOnce({ data: mockVehicles, loading: false, error: null })
      .mockReturnValueOnce({ data: mockServiceRecords, loading: false, error: null });

    renderWithProviders(<ServiceRecords />);

    const addButton = screen.getByText('serviceRecords.addRecord');
    fireEvent.click(addButton);

    await waitFor(() => {
      expect(screen.getByText('serviceRecords.addNewRecord')).toBeInTheDocument();
    });
  });

  test('opens edit dialog when edit button clicked', async () => {
    useApiData
      .mockReturnValueOnce({ data: mockVehicles, loading: false, error: null })
      .mockReturnValueOnce({ data: mockServiceRecords, loading: false, error: null });

    renderWithProviders(<ServiceRecords />);

    await waitFor(() => {
      const editButtons = screen.getAllByLabelText('serviceRecords.edit');
      fireEvent.click(editButtons[0]);
    });

    expect(screen.getByText('serviceRecords.editRecord')).toBeInTheDocument();
  });

  test('handles delete confirmation', async () => {
    window.confirm = jest.fn(() => true);
    mockApi.delete.mockResolvedValueOnce({});

    useApiData
      .mockReturnValueOnce({ data: mockVehicles, loading: false, error: null })
      .mockReturnValueOnce({
        data: mockServiceRecords,
        loading: false,
        error: null,
        refresh: jest.fn(),
      });

    renderWithProviders(<ServiceRecords />);

    await waitFor(() => {
      const deleteButtons = screen.getAllByLabelText('serviceRecords.delete');
      fireEvent.click(deleteButtons[0]);
    });

    expect(window.confirm).toHaveBeenCalledWith('serviceRecords.confirmDelete');
    expect(mockApi.delete).toHaveBeenCalledWith('/service-records/1');
  });

  test('calculates total cost correctly', async () => {
    useApiData
      .mockReturnValueOnce({ data: mockVehicles, loading: false, error: null })
      .mockReturnValueOnce({ data: mockServiceRecords, loading: false, error: null });

    renderWithProviders(<ServiceRecords />);

    await waitFor(() => {
      expect(screen.getByText('serviceRecords.totalCost')).toBeInTheDocument();
      expect(screen.getByText('£305.00')).toBeInTheDocument(); // 225 + 80
    });
  });

  test('filters records by service type', async () => {
    useApiData
      .mockReturnValueOnce({ data: mockVehicles, loading: false, error: null })
      .mockReturnValueOnce({ data: mockServiceRecords, loading: false, error: null });

    renderWithProviders(<ServiceRecords />);

    const filterSelect = screen.getByLabelText('serviceRecords.filterByType');
    fireEvent.change(filterSelect, { target: { value: 'scheduled' } });

    await waitFor(() => {
      expect(screen.getByText('Annual Service')).toBeInTheDocument();
      expect(screen.queryByText('Oil Change')).not.toBeInTheDocument();
    });
  });

  test('switches between vehicles', async () => {
    useApiData
      .mockReturnValueOnce({ data: mockVehicles, loading: false, error: null })
      .mockReturnValueOnce({ data: mockServiceRecords, loading: false, error: null });

    renderWithProviders(<ServiceRecords />);

    await waitFor(() => {
      const vehicleSelect = screen.getByLabelText('serviceRecords.selectVehicle');
      fireEvent.change(vehicleSelect, { target: { value: '2' } });
    });

    expect(useApiData).toHaveBeenCalledWith(
      expect.stringContaining('vehicleId=2'),
      expect.any(Object)
    );
  });

  test('displays empty state when no records exist', () => {
    useApiData
      .mockReturnValueOnce({ data: mockVehicles, loading: false, error: null })
      .mockReturnValueOnce({ data: [], loading: false, error: null });

    renderWithProviders(<ServiceRecords />);

    expect(screen.getByText('serviceRecords.noRecords')).toBeInTheDocument();
  });

  test('sorts records by date descending', async () => {
    useApiData
      .mockReturnValueOnce({ data: mockVehicles, loading: false, error: null })
      .mockReturnValueOnce({ data: mockServiceRecords, loading: false, error: null });

    renderWithProviders(<ServiceRecords />);

    const sortSelect = screen.getByLabelText('serviceRecords.sortBy');
    fireEvent.change(sortSelect, { target: { value: 'date_desc' } });

    await waitFor(() => {
      const dates = screen.getAllByText(/2026-01-10|2025-10-15/);
      expect(dates[0]).toHaveTextContent('2026-01-10');
    });
  });

  test('displays service type badges', async () => {
    useApiData
      .mockReturnValueOnce({ data: mockVehicles, loading: false, error: null })
      .mockReturnValueOnce({ data: mockServiceRecords, loading: false, error: null });

    renderWithProviders(<ServiceRecords />);

    await waitFor(() => {
      expect(screen.getByText('scheduled')).toBeInTheDocument();
      expect(screen.getByText('oil_change')).toBeInTheDocument();
    });
  });

  test('displays cost breakdown', async () => {
    useApiData
      .mockReturnValueOnce({ data: mockVehicles, loading: false, error: null })
      .mockReturnValueOnce({ data: mockServiceRecords, loading: false, error: null });

    renderWithProviders(<ServiceRecords />);

    await waitFor(() => {
      expect(screen.getByText('serviceRecords.labourCost')).toBeInTheDocument();
      expect(screen.getByText('serviceRecords.partsCost')).toBeInTheDocument();
      expect(screen.getByText('£200.00')).toBeInTheDocument(); // Total labour: 150 + 50
      expect(screen.getByText('£105.00')).toBeInTheDocument(); // Total parts: 75 + 30
    });
  });

  test('filters by date range', async () => {
    useApiData
      .mockReturnValueOnce({ data: mockVehicles, loading: false, error: null })
      .mockReturnValueOnce({ data: mockServiceRecords, loading: false, error: null });

    renderWithProviders(<ServiceRecords />);

    const startDate = screen.getByLabelText('serviceRecords.startDate');
    const endDate = screen.getByLabelText('serviceRecords.endDate');

    fireEvent.change(startDate, { target: { value: '2026-01-01' } });
    fireEvent.change(endDate, { target: { value: '2026-12-31' } });

    await waitFor(() => {
      expect(screen.getByText('Annual Service')).toBeInTheDocument();
      expect(screen.queryByText('Oil Change')).not.toBeInTheDocument();
    });
  });

  test('uploads attachment', async () => {
    const mockFile = new File(['receipt'], 'receipt.pdf', { type: 'application/pdf' });
    mockApi.post.mockResolvedValueOnce({ id: 1, filename: 'receipt.pdf' });

    useApiData
      .mockReturnValueOnce({ data: mockVehicles, loading: false, error: null })
      .mockReturnValueOnce({ data: mockServiceRecords, loading: false, error: null });

    renderWithProviders(<ServiceRecords />);

    await waitFor(() => {
      const uploadButton = screen.getAllByLabelText('serviceRecords.uploadAttachment')[0];
      const fileInput = uploadButton.querySelector('input[type="file"]');
      
      fireEvent.change(fileInput, { target: { files: [mockFile] } });
    });

    expect(mockApi.post).toHaveBeenCalledWith(
      expect.stringContaining('/attachments'),
      expect.any(FormData)
    );
  });

  test('displays upcoming service reminders', async () => {
    const recordsWithReminder = [
      ...mockServiceRecords,
      {
        id: 3,
        vehicleId: 1,
        description: 'Next Service Due',
        serviceDate: '2025-01-10',
        mileage: 40000,
        nextServiceMileage: 52000,
        labourCost: 0,
        partsCost: 0,
        totalCost: 0,
      },
    ];

    useApiData
      .mockReturnValueOnce({ data: mockVehicles, loading: false, error: null })
      .mockReturnValueOnce({ data: recordsWithReminder, loading: false, error: null });

    renderWithProviders(<ServiceRecords />);

    await waitFor(() => {
      expect(screen.getByText('serviceRecords.nextServiceDue')).toBeInTheDocument();
      expect(screen.getByText('52,000')).toBeInTheDocument();
    });
  });

  test('exports service history', async () => {
    const mockCreateObjectURL = jest.fn();
    window.URL.createObjectURL = mockCreateObjectURL;

    useApiData
      .mockReturnValueOnce({ data: mockVehicles, loading: false, error: null })
      .mockReturnValueOnce({ data: mockServiceRecords, loading: false, error: null });

    renderWithProviders(<ServiceRecords />);

    const exportButton = screen.getByText('serviceRecords.export');
    fireEvent.click(exportButton);

    await waitFor(() => {
      expect(mockCreateObjectURL).toHaveBeenCalled();
    });
  });
});
