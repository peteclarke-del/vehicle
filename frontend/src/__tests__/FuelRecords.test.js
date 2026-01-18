import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { BrowserRouter } from 'react-router-dom';
import FuelRecords from '../pages/FuelRecords';
import { AuthContext } from '../context/AuthContext';

jest.mock('react-i18next', () => ({
  useTranslation: () => ({ t: (key) => key }),
}));

jest.mock('../hooks/useApiData', () => ({
  useApiData: jest.fn(),
}));

jest.mock('recharts', () => ({
  LineChart: ({ children }) => <div data-testid="line-chart">{children}</div>,
  Line: () => null,
  XAxis: () => null,
  YAxis: () => null,
  CartesianGrid: () => null,
  Tooltip: () => null,
  Legend: () => null,
  ResponsiveContainer: ({ children }) => <div>{children}</div>,
}));

const { useApiData } = require('../hooks/useApiData');

const mockApi = {
  delete: jest.fn(),
};

describe('FuelRecords Component', () => {
  const mockAuthContext = {
    user: { id: 1, email: 'test@example.com' },
  };

  const mockVehicles = [
    { id: 1, registration: 'ABC123', make: 'Toyota', model: 'Corolla' },
  ];

  const mockFuelRecords = [
    {
      id: 1,
      vehicleId: 1,
      date: '2026-01-15',
      mileage: 50000,
      litres: 45.5,
      cost: 68.25,
      pricePerLitre: 1.50,
      fuelType: 'Petrol',
      station: 'Shell',
      fullTank: true,
    },
    {
      id: 2,
      vehicleId: 1,
      date: '2026-01-01',
      mileage: 49500,
      litres: 48.0,
      cost: 72.00,
      pricePerLitre: 1.50,
      fuelType: 'Petrol',
      station: 'BP',
      fullTank: true,
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

  test('renders fuel records page title', () => {
    useApiData
      .mockReturnValueOnce({ data: mockVehicles, loading: false, error: null })
      .mockReturnValueOnce({ data: mockFuelRecords, loading: false, error: null });

    renderWithProviders(<FuelRecords />);

    expect(screen.getByText('fuelRecords.title')).toBeInTheDocument();
  });

  test('displays loading state', () => {
    useApiData.mockReturnValue({ data: null, loading: true, error: null });

    renderWithProviders(<FuelRecords />);

    expect(screen.getByRole('progressbar')).toBeInTheDocument();
  });

  test('loads and displays fuel records', async () => {
    useApiData
      .mockReturnValueOnce({ data: mockVehicles, loading: false, error: null })
      .mockReturnValueOnce({ data: mockFuelRecords, loading: false, error: null });

    renderWithProviders(<FuelRecords />);

    await waitFor(() => {
      expect(screen.getByText('Shell')).toBeInTheDocument();
      expect(screen.getByText('BP')).toBeInTheDocument();
      expect(screen.getByText('£68.25')).toBeInTheDocument();
      expect(screen.getByText('£72.00')).toBeInTheDocument();
    });
  });

  test('opens add dialog when add button clicked', async () => {
    useApiData
      .mockReturnValueOnce({ data: mockVehicles, loading: false, error: null })
      .mockReturnValueOnce({ data: mockFuelRecords, loading: false, error: null });

    renderWithProviders(<FuelRecords />);

    const addButton = screen.getByText('fuelRecords.addRecord');
    fireEvent.click(addButton);

    await waitFor(() => {
      expect(screen.getByText('fuelRecords.addNewRecord')).toBeInTheDocument();
    });
  });

  test('handles delete confirmation', async () => {
    window.confirm = jest.fn(() => true);
    mockApi.delete.mockResolvedValueOnce({});

    useApiData
      .mockReturnValueOnce({ data: mockVehicles, loading: false, error: null })
      .mockReturnValueOnce({
        data: mockFuelRecords,
        loading: false,
        error: null,
        refresh: jest.fn(),
      });

    renderWithProviders(<FuelRecords />);

    await waitFor(() => {
      const deleteButtons = screen.getAllByLabelText('fuelRecords.delete');
      fireEvent.click(deleteButtons[0]);
    });

    expect(window.confirm).toHaveBeenCalledWith('fuelRecords.confirmDelete');
    expect(mockApi.delete).toHaveBeenCalledWith('/fuel-records/1');
  });

  test('calculates total fuel cost', async () => {
    useApiData
      .mockReturnValueOnce({ data: mockVehicles, loading: false, error: null })
      .mockReturnValueOnce({ data: mockFuelRecords, loading: false, error: null });

    renderWithProviders(<FuelRecords />);

    await waitFor(() => {
      expect(screen.getByText('fuelRecords.totalCost')).toBeInTheDocument();
      expect(screen.getByText('£140.25')).toBeInTheDocument(); // 68.25 + 72.00
    });
  });

  test('calculates average fuel economy', async () => {
    useApiData
      .mockReturnValueOnce({ data: mockVehicles, loading: false, error: null })
      .mockReturnValueOnce({ data: mockFuelRecords, loading: false, error: null });

    renderWithProviders(<FuelRecords />);

    await waitFor(() => {
      expect(screen.getByText('fuelRecords.averageMpg')).toBeInTheDocument();
      // 500 miles / 45.5 litres * 4.546 = 50.0 mpg
      expect(screen.getByText(/50\.0.*mpg/i)).toBeInTheDocument();
    });
  });

  test('calculates cost per mile', async () => {
    useApiData
      .mockReturnValueOnce({ data: mockVehicles, loading: false, error: null })
      .mockReturnValueOnce({ data: mockFuelRecords, loading: false, error: null });

    renderWithProviders(<FuelRecords />);

    await waitFor(() => {
      expect(screen.getByText('fuelRecords.costPerMile')).toBeInTheDocument();
      // 68.25 / 500 miles = £0.14 per mile
      expect(screen.getByText(/£0\.14/i)).toBeInTheDocument();
    });
  });

  test('renders fuel economy chart', async () => {
    useApiData
      .mockReturnValueOnce({ data: mockVehicles, loading: false, error: null })
      .mockReturnValueOnce({ data: mockFuelRecords, loading: false, error: null });

    renderWithProviders(<FuelRecords />);

    await waitFor(() => {
      expect(screen.getByTestId('line-chart')).toBeInTheDocument();
    });
  });

  test('filters by date range', async () => {
    useApiData
      .mockReturnValueOnce({ data: mockVehicles, loading: false, error: null })
      .mockReturnValueOnce({ data: mockFuelRecords, loading: false, error: null });

    renderWithProviders(<FuelRecords />);

    const startDate = screen.getByLabelText('fuelRecords.startDate');
    const endDate = screen.getByLabelText('fuelRecords.endDate');

    fireEvent.change(startDate, { target: { value: '2026-01-10' } });
    fireEvent.change(endDate, { target: { value: '2026-12-31' } });

    await waitFor(() => {
      expect(screen.getByText('Shell')).toBeInTheDocument();
      expect(screen.queryByText('BP')).not.toBeInTheDocument();
    });
  });

  test('switches between vehicles', async () => {
    useApiData
      .mockReturnValueOnce({ data: mockVehicles, loading: false, error: null })
      .mockReturnValueOnce({ data: mockFuelRecords, loading: false, error: null });

    renderWithProviders(<FuelRecords />);

    await waitFor(() => {
      const vehicleSelect = screen.getByLabelText('fuelRecords.selectVehicle');
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

    renderWithProviders(<FuelRecords />);

    expect(screen.getByText('fuelRecords.noRecords')).toBeInTheDocument();
  });

  test('displays litres per 100km', async () => {
    useApiData
      .mockReturnValueOnce({ data: mockVehicles, loading: false, error: null })
      .mockReturnValueOnce({ data: mockFuelRecords, loading: false, error: null });

    renderWithProviders(<FuelRecords />);

    await waitFor(() => {
      expect(screen.getByText('fuelRecords.litresPer100km')).toBeInTheDocument();
      // 45.5 litres / 500 miles * 100km = 5.7 L/100km
      expect(screen.getByText(/5\.7.*L\/100km/i)).toBeInTheDocument();
    });
  });

  test('displays full tank indicator', async () => {
    useApiData
      .mockReturnValueOnce({ data: mockVehicles, loading: false, error: null })
      .mockReturnValueOnce({ data: mockFuelRecords, loading: false, error: null });

    renderWithProviders(<FuelRecords />);

    await waitFor(() => {
      const fullTankBadges = screen.getAllByText('fuelRecords.fullTank');
      expect(fullTankBadges).toHaveLength(2);
    });
  });

  test('calculates monthly average cost', async () => {
    useApiData
      .mockReturnValueOnce({ data: mockVehicles, loading: false, error: null })
      .mockReturnValueOnce({ data: mockFuelRecords, loading: false, error: null });

    renderWithProviders(<FuelRecords />);

    await waitFor(() => {
      expect(screen.getByText('fuelRecords.monthlyAverage')).toBeInTheDocument();
    });
  });

  test('sorts records by date descending', async () => {
    useApiData
      .mockReturnValueOnce({ data: mockVehicles, loading: false, error: null })
      .mockReturnValueOnce({ data: mockFuelRecords, loading: false, error: null });

    renderWithProviders(<FuelRecords />);

    const sortSelect = screen.getByLabelText('fuelRecords.sortBy');
    fireEvent.change(sortSelect, { target: { value: 'date_desc' } });

    await waitFor(() => {
      const dates = screen.getAllByText(/2026-01-/);
      expect(dates[0]).toHaveTextContent('2026-01-15');
    });
  });

  test('displays price per litre trend', async () => {
    useApiData
      .mockReturnValueOnce({ data: mockVehicles, loading: false, error: null })
      .mockReturnValueOnce({ data: mockFuelRecords, loading: false, error: null });

    renderWithProviders(<FuelRecords />);

    await waitFor(() => {
      expect(screen.getByText('fuelRecords.pricePerLitre')).toBeInTheDocument();
      expect(screen.getAllByText('£1.50')).toHaveLength(2);
    });
  });
});
