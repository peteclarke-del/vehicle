import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { BrowserRouter } from 'react-router-dom';
import MotRecords from '../pages/MotRecords';
import { AuthContext } from '../context/AuthContext';

jest.mock('react-i18next', () => ({
  useTranslation: () => ({ t: (key) => key }),
}));

jest.mock('../hooks/useApiData', () => ({
  useApiData: jest.fn(),
}));

const { useApiData } = require('../hooks/useApiData');

const mockApi = {
  post: jest.fn(),
  delete: jest.fn(),
};

describe('MotRecords Component', () => {
  const mockAuthContext = {
    user: { id: 1, email: 'test@example.com' },
  };

  const mockVehicles = [
    { id: 1, registration: 'ABC123', make: 'Toyota', model: 'Corolla' },
  ];

  const mockMotRecords = [
    {
      id: 1,
      vehicleId: 1,
      testDate: '2026-01-15',
      expiryDate: '2027-01-15',
      testResult: 'Pass',
      mileage: 50000,
      testNumber: 'MOT123456789',
      testCenter: 'Test MOT Center',
      advisoryItems: ['Brake pads worn', 'Tyre tread low'],
      failureItems: [],
      cost: 40.00,
    },
    {
      id: 2,
      vehicleId: 1,
      testDate: '2025-01-10',
      expiryDate: '2026-01-10',
      testResult: 'Pass',
      mileage: 48000,
      testNumber: 'MOT987654321',
      testCenter: 'Quick MOT',
      advisoryItems: [],
      failureItems: [],
      cost: 40.00,
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

  test('renders MOT records page title', () => {
    useApiData
      .mockReturnValueOnce({ data: mockVehicles, loading: false, error: null })
      .mockReturnValueOnce({ data: mockMotRecords, loading: false, error: null });

    renderWithProviders(<MotRecords />);

    expect(screen.getByText('motRecords.title')).toBeInTheDocument();
  });

  test('displays loading state', () => {
    useApiData.mockReturnValue({ data: null, loading: true, error: null });

    renderWithProviders(<MotRecords />);

    expect(screen.getByRole('progressbar')).toBeInTheDocument();
  });

  test('loads and displays MOT records', async () => {
    useApiData
      .mockReturnValueOnce({ data: mockVehicles, loading: false, error: null })
      .mockReturnValueOnce({ data: mockMotRecords, loading: false, error: null });

    renderWithProviders(<MotRecords />);

    await waitFor(() => {
      expect(screen.getByText('MOT123456789')).toBeInTheDocument();
      expect(screen.getByText('Test MOT Center')).toBeInTheDocument();
      expect(screen.getAllByText('Pass')).toHaveLength(2);
    });
  });

  test('opens add dialog when add button clicked', async () => {
    useApiData
      .mockReturnValueOnce({ data: mockVehicles, loading: false, error: null })
      .mockReturnValueOnce({ data: mockMotRecords, loading: false, error: null });

    renderWithProviders(<MotRecords />);

    const addButton = screen.getByText('motRecords.addRecord');
    fireEvent.click(addButton);

    await waitFor(() => {
      expect(screen.getByText('motRecords.addNewRecord')).toBeInTheDocument();
    });
  });

  test('opens import from DVSA dialog', async () => {
    useApiData
      .mockReturnValueOnce({ data: mockVehicles, loading: false, error: null })
      .mockReturnValueOnce({ data: mockMotRecords, loading: false, error: null });

    renderWithProviders(<MotRecords />);

    const importButton = screen.getByText('motRecords.importFromDvsa');
    fireEvent.click(importButton);

    await waitFor(() => {
      expect(screen.getByText('motRecords.importDvsaHistory')).toBeInTheDocument();
    });
  });

  test('imports MOT history from DVSA', async () => {
    const dvsaData = {
      imported: 3,
      records: [
        { testDate: '2026-01-15', testResult: 'Pass' },
        { testDate: '2025-01-10', testResult: 'Pass' },
        { testDate: '2024-01-05', testResult: 'Pass' },
      ],
    };

    mockApi.post.mockResolvedValueOnce(dvsaData);

    useApiData
      .mockReturnValueOnce({ data: mockVehicles, loading: false, error: null })
      .mockReturnValueOnce({ data: mockMotRecords, loading: false, error: null });

    renderWithProviders(<MotRecords />);

    const importButton = screen.getByText('motRecords.importFromDvsa');
    fireEvent.click(importButton);

    await waitFor(() => {
      const registrationInput = screen.getByLabelText('motRecords.registration');
      fireEvent.change(registrationInput, { target: { value: 'ABC123' } });

      const importSubmit = screen.getByText('motRecords.import');
      fireEvent.click(importSubmit);
    });

    expect(mockApi.post).toHaveBeenCalledWith(
      '/mot-records/import-dvsa',
      expect.objectContaining({
        registration: 'ABC123',
      })
    );
  });

  test('handles delete confirmation', async () => {
    window.confirm = jest.fn(() => true);
    mockApi.delete.mockResolvedValueOnce({});

    useApiData
      .mockReturnValueOnce({ data: mockVehicles, loading: false, error: null })
      .mockReturnValueOnce({
        data: mockMotRecords,
        loading: false,
        error: null,
        refresh: jest.fn(),
      });

    renderWithProviders(<MotRecords />);

    await waitFor(() => {
      const deleteButtons = screen.getAllByLabelText('motRecords.delete');
      fireEvent.click(deleteButtons[0]);
    });

    expect(window.confirm).toHaveBeenCalledWith('motRecords.confirmDelete');
    expect(mockApi.delete).toHaveBeenCalledWith('/mot-records/1');
  });

  test('displays advisory items', async () => {
    useApiData
      .mockReturnValueOnce({ data: mockVehicles, loading: false, error: null })
      .mockReturnValueOnce({ data: mockMotRecords, loading: false, error: null });

    renderWithProviders(<MotRecords />);

    await waitFor(() => {
      expect(screen.getByText('Brake pads worn')).toBeInTheDocument();
      expect(screen.getByText('Tyre tread low')).toBeInTheDocument();
    });
  });

  test('displays pass badge for passed MOTs', async () => {
    useApiData
      .mockReturnValueOnce({ data: mockVehicles, loading: false, error: null })
      .mockReturnValueOnce({ data: mockMotRecords, loading: false, error: null });

    renderWithProviders(<MotRecords />);

    await waitFor(() => {
      const passBadges = screen.getAllByText('Pass');
      expect(passBadges.length).toBeGreaterThan(0);
    });
  });

  test('displays failed MOT with failure items', async () => {
    const failedMotRecords = [
      {
        ...mockMotRecords[0],
        testResult: 'Fail',
        failureItems: ['Brake pads below minimum', 'Headlight alignment incorrect'],
      },
    ];

    useApiData
      .mockReturnValueOnce({ data: mockVehicles, loading: false, error: null })
      .mockReturnValueOnce({ data: failedMotRecords, loading: false, error: null });

    renderWithProviders(<MotRecords />);

    await waitFor(() => {
      expect(screen.getByText('Fail')).toBeInTheDocument();
      expect(screen.getByText('Brake pads below minimum')).toBeInTheDocument();
      expect(screen.getByText('Headlight alignment incorrect')).toBeInTheDocument();
    });
  });

  test('displays next MOT due date', async () => {
    useApiData
      .mockReturnValueOnce({ data: mockVehicles, loading: false, error: null })
      .mockReturnValueOnce({ data: mockMotRecords, loading: false, error: null });

    renderWithProviders(<MotRecords />);

    await waitFor(() => {
      expect(screen.getByText('motRecords.nextDue')).toBeInTheDocument();
      expect(screen.getByText('2027-01-15')).toBeInTheDocument();
    });
  });

  test('displays expired MOT warning', async () => {
    const expiredMotRecords = [
      {
        ...mockMotRecords[0],
        expiryDate: '2025-06-01', // Expired
      },
    ];

    useApiData
      .mockReturnValueOnce({ data: mockVehicles, loading: false, error: null })
      .mockReturnValueOnce({ data: expiredMotRecords, loading: false, error: null });

    renderWithProviders(<MotRecords />);

    await waitFor(() => {
      expect(screen.getByText('motRecords.expired')).toBeInTheDocument();
    });
  });

  test('displays MOT pass rate', async () => {
    useApiData
      .mockReturnValueOnce({ data: mockVehicles, loading: false, error: null })
      .mockReturnValueOnce({ data: mockMotRecords, loading: false, error: null });

    renderWithProviders(<MotRecords />);

    await waitFor(() => {
      expect(screen.getByText('motRecords.passRate')).toBeInTheDocument();
      expect(screen.getByText('100%')).toBeInTheDocument(); // Both tests passed
    });
  });

  test('switches between vehicles', async () => {
    useApiData
      .mockReturnValueOnce({ data: mockVehicles, loading: false, error: null })
      .mockReturnValueOnce({ data: mockMotRecords, loading: false, error: null });

    renderWithProviders(<MotRecords />);

    await waitFor(() => {
      const vehicleSelect = screen.getByLabelText('motRecords.selectVehicle');
      fireEvent.change(vehicleSelect, { target: { value: '1' } });
    });

    expect(useApiData).toHaveBeenCalledWith(
      expect.stringContaining('vehicleId=1'),
      expect.any(Object)
    );
  });

  test('displays empty state when no records exist', () => {
    useApiData
      .mockReturnValueOnce({ data: mockVehicles, loading: false, error: null })
      .mockReturnValueOnce({ data: [], loading: false, error: null });

    renderWithProviders(<MotRecords />);

    expect(screen.getByText('motRecords.noRecords')).toBeInTheDocument();
  });

  test('sorts records by date descending', async () => {
    useApiData
      .mockReturnValueOnce({ data: mockVehicles, loading: false, error: null })
      .mockReturnValueOnce({ data: mockMotRecords, loading: false, error: null });

    renderWithProviders(<MotRecords />);

    const sortSelect = screen.getByLabelText('motRecords.sortBy');
    fireEvent.change(sortSelect, { target: { value: 'date_desc' } });

    await waitFor(() => {
      const dates = screen.getAllByText(/2026-01-15|2025-01-10/);
      expect(dates[0]).toHaveTextContent('2026-01-15');
    });
  });

  test('displays average mileage at test', async () => {
    useApiData
      .mockReturnValueOnce({ data: mockVehicles, loading: false, error: null })
      .mockReturnValueOnce({ data: mockMotRecords, loading: false, error: null });

    renderWithProviders(<MotRecords />);

    await waitFor(() => {
      expect(screen.getByText('motRecords.averageMileage')).toBeInTheDocument();
      expect(screen.getByText('49,000')).toBeInTheDocument(); // (50000 + 48000) / 2
    });
  });
});
