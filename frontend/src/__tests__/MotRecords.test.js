import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { BrowserRouter } from 'react-router-dom';
import MotRecords from '../pages/MotRecords';
import { AuthContext } from '../contexts/AuthContext';

// Override the global VehiclesContext mock so we can control vehicles per-test
jest.mock('../contexts/VehiclesContext', () => ({
  VehiclesProvider: ({ children }) => children,
  useVehicles: jest.fn(),
}));

jest.mock('../hooks/useApiData', () => ({
  useApiData: jest.fn(() => ({ data: [], loading: false, error: null, fetchData: jest.fn(), setData: jest.fn() })),
  fetchArrayData: jest.fn(),
}));

// Mock the complex MotDialog to prevent hangs from nested components
jest.mock('../components/MotDialog', () => ({
  __esModule: true,
  default: ({ open }) => open ? require('react').createElement('div', { role: 'dialog', 'data-testid': 'mot-dialog' }) : null,
}));

const { useVehicles } = require('../contexts/VehiclesContext');
const { fetchArrayData } = require('../hooks/useApiData');

const mockApi = {
  get: jest.fn(),
  post: jest.fn(),
  put: jest.fn(),
  delete: jest.fn(),
};

const mockAuthValue = {
  api: mockApi,
  user: { id: 1, email: 'test@example.com' },
  token: 'mock-token',
  isAuthenticated: true,
  login: jest.fn(),
  logout: jest.fn(),
};

const mockVehicles = [
  { id: 1, make: 'Toyota', model: 'Corolla', year: 2020, registrationNumber: 'ABC123', registration: 'ABC123' },
];

const mockMotRecords = [
  {
    id: 1,
    vehicleId: 1,
    testDate: '2026-01-15',
    expiryDate: '2027-01-15',
    testResult: 'Pass',
    result: 'PASSED',
    odometerValue: 50000,
    motTestNumber: 'MOT123456789',
    testCenter: 'Test MOT Center',
    rfrAndComments: [{ type: 'ADVISORY', text: 'Brake pads worn' }],
  },
];

const renderWithProviders = (component) => {
  return render(
    <BrowserRouter>
      <AuthContext.Provider value={mockAuthValue}>
        {component}
      </AuthContext.Provider>
    </BrowserRouter>
  );
};

describe('MotRecords Component', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    useVehicles.mockReturnValue({
      vehicles: mockVehicles,
      loading: false,
      error: null,
      refreshVehicles: jest.fn(),
      fetchVehicles: jest.fn(),
      notifyRecordChange: jest.fn(),
      recordsVersion: 0,
    });
    fetchArrayData.mockResolvedValue([]);
  });

  test('renders MOT records page title', async () => {
    renderWithProviders(<MotRecords />);

    await waitFor(() => {
      expect(screen.getByText('mot.title')).toBeInTheDocument();
    });
  });

  test('displays loading state', () => {
    useVehicles.mockReturnValue({
      vehicles: [],
      loading: true,
      error: null,
      refreshVehicles: jest.fn(),
      fetchVehicles: jest.fn(),
      notifyRecordChange: jest.fn(),
      recordsVersion: 0,
    });
    fetchArrayData.mockReturnValue(new Promise(() => {}));

    renderWithProviders(<MotRecords />);

    expect(screen.queryByText('mot.title')).not.toBeInTheDocument();
  });

  test('shows loading state when vehicles are loading', () => {
    useVehicles.mockReturnValue({
      vehicles: [],
      loading: true,
      error: null,
      refreshVehicles: jest.fn(),
      fetchVehicles: jest.fn(),
      notifyRecordChange: jest.fn(),
      recordsVersion: 0,
    });

    renderWithProviders(<MotRecords />);

    expect(screen.queryByText('mot.title')).not.toBeInTheDocument();
    expect(screen.getByRole('progressbar')).toBeInTheDocument();
  });

  test('loads and displays MOT records', async () => {
    fetchArrayData.mockResolvedValue(mockMotRecords);

    renderWithProviders(<MotRecords />);

    await waitFor(() => {
      expect(screen.getByText('Test MOT Center')).toBeInTheDocument();
    });
  });

  test('shows add MOT button', async () => {
    renderWithProviders(<MotRecords />);

    await waitFor(() => {
      expect(screen.getByText('mot.addMot')).toBeInTheDocument();
    });
  });

  test('opens add dialog when add button clicked', async () => {
    renderWithProviders(<MotRecords />);

    const addButton = await screen.findByText('mot.addMot');
    fireEvent.click(addButton);

    await waitFor(() => {
      expect(screen.getByRole('dialog')).toBeInTheDocument();
    });
  });

  test('shows import from DVSA button', async () => {
    renderWithProviders(<MotRecords />);

    await waitFor(() => {
      // The button uses a fallback default string when key is not in test i18n
      expect(screen.getByText(/mot\.importFromDvsa|Import MOT history/i)).toBeInTheDocument();
    });
  });

  test('handles delete with confirmation', async () => {
    fetchArrayData.mockResolvedValue(mockMotRecords);
    mockApi.delete.mockResolvedValue({});
    global.confirm = jest.fn(() => true);

    renderWithProviders(<MotRecords />);

    await waitFor(() => {
      expect(screen.getByText('Test MOT Center')).toBeInTheDocument();
    });

    const deleteButton = screen.getByRole('button', { name: 'common.delete' });
    fireEvent.click(deleteButton);

    await waitFor(() => {
      expect(mockApi.delete).toHaveBeenCalledWith('/mot-records/1');
    });
  });

  test('shows no records message when vehicle has no MOT records', async () => {
    fetchArrayData.mockResolvedValue([]);

    renderWithProviders(<MotRecords />);

    await waitFor(() => {
      expect(screen.getByText('common.noRecords')).toBeInTheDocument();
    });
  });

  test('shows MOT record column headers', async () => {
    renderWithProviders(<MotRecords />);

    await waitFor(() => {
      expect(screen.getByText('mot.testDate')).toBeInTheDocument();
      expect(screen.getByText('mot.result')).toBeInTheDocument();
    });
  });
});
