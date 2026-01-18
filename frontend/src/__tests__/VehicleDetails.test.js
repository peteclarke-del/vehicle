import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { BrowserRouter } from 'react-router-dom';
import VehicleDetails from '../pages/VehicleDetails';
import { AuthContext } from '../context/AuthContext';
import '@testing-library/jest-dom';

// Mock useApiData hook
jest.mock('../hooks/useApiData', () => ({
  useApiData: jest.fn(),
}));

// Mock i18next
jest.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: (key) => key,
  }),
}));

const { useApiData } = require('../hooks/useApiData');

const renderWithProviders = (component) => {
  const mockAuthContext = {
    user: { id: 1, email: 'test@example.com' },
    token: 'mock-token',
  };

  return render(
    <BrowserRouter>
      <AuthContext.Provider value={mockAuthContext}>
        {component}
      </AuthContext.Provider>
    </BrowserRouter>
  );
};

describe('VehicleDetails Page', () => {
  const mockVehicle = {
    id: 1,
    registration: 'ABC123',
    make: { name: 'Toyota' },
    model: { name: 'Corolla' },
    year: 2020,
    colour: 'Silver',
    mileage: 50000,
    purchasePrice: 15000,
    purchaseDate: '2020-01-15',
    fuelType: 'Petrol',
    engineSize: 1.8,
  };

  const mockCostSummary = {
    totalInsurance: 1000,
    totalService: 2500,
    totalParts: 800,
    totalFuel: 3000,
    totalMot: 200,
    totalConsumables: 400,
    grandTotal: 7900,
  };

  const mockDepreciation = {
    currentValue: 12000,
    depreciationAmount: 3000,
    depreciationPercentage: 20.0,
  };

  const mockServiceRecords = [
    {
      id: 1,
      serviceDate: '2023-01-15',
      serviceType: 'Annual Service',
      totalCost: 250,
    },
    {
      id: 2,
      serviceDate: '2023-06-20',
      serviceType: 'Oil Change',
      totalCost: 80,
    },
  ];

  beforeEach(() => {
    useApiData.mockImplementation((url) => {
      if (url.includes('/vehicles/1')) {
        return { data: mockVehicle, loading: false, error: null };
      }
      if (url.includes('/costs/summary')) {
        return { data: mockCostSummary, loading: false, error: null };
      }
      if (url.includes('/depreciation')) {
        return { data: mockDepreciation, loading: false, error: null };
      }
      if (url.includes('/service-records')) {
        return { data: mockServiceRecords, loading: false, error: null };
      }
      return { data: null, loading: false, error: null };
    });
  });

  test('renders vehicle details page', () => {
    renderWithProviders(<VehicleDetails />);
    expect(screen.getByText('vehicleDetails.title')).toBeInTheDocument();
  });

  test('displays loading state', () => {
    useApiData.mockReturnValue({ data: null, loading: true, error: null });
    renderWithProviders(<VehicleDetails />);
    expect(screen.getByRole('progressbar')).toBeInTheDocument();
  });

  test('displays error message when fetch fails', () => {
    useApiData.mockReturnValue({ data: null, loading: false, error: 'Failed to load vehicle' });
    renderWithProviders(<VehicleDetails />);
    expect(screen.getByText(/Failed to load vehicle/i)).toBeInTheDocument();
  });

  test('displays vehicle information', () => {
    renderWithProviders(<VehicleDetails />);
    
    expect(screen.getByText('ABC123')).toBeInTheDocument();
    expect(screen.getByText('Toyota')).toBeInTheDocument();
    expect(screen.getByText('Corolla')).toBeInTheDocument();
    expect(screen.getByText('2020')).toBeInTheDocument();
    expect(screen.getByText('Silver')).toBeInTheDocument();
    expect(screen.getByText('50000')).toBeInTheDocument();
  });

  test('displays cost summary', () => {
    renderWithProviders(<VehicleDetails />);
    
    expect(screen.getByText(/£7,900.00/i)).toBeInTheDocument();
    expect(screen.getByText(/£1,000.00/i)).toBeInTheDocument();
    expect(screen.getByText(/£2,500.00/i)).toBeInTheDocument();
  });

  test('displays depreciation information', () => {
    renderWithProviders(<VehicleDetails />);
    
    expect(screen.getByText(/£12,000.00/i)).toBeInTheDocument();
    expect(screen.getByText(/£3,000.00/i)).toBeInTheDocument();
    expect(screen.getByText(/20.0%/i)).toBeInTheDocument();
  });

  test('displays recent service records', () => {
    renderWithProviders(<VehicleDetails />);
    
    expect(screen.getByText('Annual Service')).toBeInTheDocument();
    expect(screen.getByText('Oil Change')).toBeInTheDocument();
  });

  test('navigates to insurance when insurance card clicked', () => {
    renderWithProviders(<VehicleDetails />);
    
    const insuranceCard = screen.getByText(/insurance/i).closest('div[role="button"]');
    fireEvent.click(insuranceCard);

    // Check navigation (would need to mock useNavigate in real implementation)
    expect(window.location.pathname).toContain('/insurance');
  });

  test('navigates to service records when service card clicked', () => {
    renderWithProviders(<VehicleDetails />);
    
    const serviceCard = screen.getByText(/service/i).closest('div[role="button"]');
    fireEvent.click(serviceCard);

    expect(window.location.pathname).toContain('/service-records');
  });

  test('opens edit vehicle dialog', () => {
    renderWithProviders(<VehicleDetails />);
    
    const editButton = screen.getByLabelText(/edit/i);
    fireEvent.click(editButton);

    expect(screen.getByText(/edit vehicle/i)).toBeInTheDocument();
  });

  test('opens delete vehicle confirmation', () => {
    renderWithProviders(<VehicleDetails />);
    
    const deleteButton = screen.getByLabelText(/delete/i);
    fireEvent.click(deleteButton);

    expect(screen.getByText(/delete vehicle/i)).toBeInTheDocument();
  });

  test('displays MOT status', () => {
    const mockMot = {
      expiryDate: '2024-06-15',
      isValid: true,
      daysUntilExpiry: 120,
    };

    useApiData.mockImplementation((url) => {
      if (url.includes('/mot-records/status')) {
        return { data: mockMot, loading: false, error: null };
      }
      return { data: mockVehicle, loading: false, error: null };
    });

    renderWithProviders(<VehicleDetails />);
    
    expect(screen.getByText(/MOT Valid/i)).toBeInTheDocument();
    expect(screen.getByText(/120 days/i)).toBeInTheDocument();
  });

  test('displays expired MOT warning', () => {
    const mockMot = {
      expiryDate: '2023-01-15',
      isValid: false,
      daysUntilExpiry: -90,
    };

    useApiData.mockImplementation((url) => {
      if (url.includes('/mot-records/status')) {
        return { data: mockMot, loading: false, error: null };
      }
      return { data: mockVehicle, loading: false, error: null };
    });

    renderWithProviders(<VehicleDetails />);
    
    expect(screen.getByText(/MOT Expired/i)).toBeInTheDocument();
  });

  test('displays fuel economy statistics', () => {
    const mockFuelEconomy = {
      averageMpg: 48.5,
      averageLitresPer100km: 5.8,
      costPerMile: 0.14,
    };

    useApiData.mockImplementation((url) => {
      if (url.includes('/fuel-records/economy')) {
        return { data: mockFuelEconomy, loading: false, error: null };
      }
      return { data: mockVehicle, loading: false, error: null };
    });

    renderWithProviders(<VehicleDetails />);
    
    expect(screen.getByText(/48.5 mpg/i)).toBeInTheDocument();
    expect(screen.getByText(/5.8 L\/100km/i)).toBeInTheDocument();
    expect(screen.getByText(/£0.14/i)).toBeInTheDocument();
  });

  test('displays upcoming service reminders', () => {
    const mockReminders = [
      {
        id: 1,
        type: 'service',
        dueDate: '2024-07-15',
        daysUntilDue: 45,
      },
      {
        id: 2,
        type: 'mot',
        dueDate: '2024-06-15',
        daysUntilDue: 15,
      },
    ];

    useApiData.mockImplementation((url) => {
      if (url.includes('/reminders')) {
        return { data: mockReminders, loading: false, error: null };
      }
      return { data: mockVehicle, loading: false, error: null };
    });

    renderWithProviders(<VehicleDetails />);
    
    expect(screen.getByText(/45 days/i)).toBeInTheDocument();
    expect(screen.getByText(/15 days/i)).toBeInTheDocument();
  });

  test('calculates cost per mile', () => {
    renderWithProviders(<VehicleDetails />);
    
    // £7900 total / 50000 miles = £0.158 per mile
    expect(screen.getByText(/£0.16/i)).toBeInTheDocument();
  });

  test('displays annual cost estimate', () => {
    renderWithProviders(<VehicleDetails />);
    
    // If annual mileage is 10000, cost would be £1580
    expect(screen.getByText(/£1,580.00/i)).toBeInTheDocument();
  });

  test('renders cost breakdown chart', () => {
    renderWithProviders(<VehicleDetails />);
    
    expect(screen.getByTestId('cost-chart')).toBeInTheDocument();
  });

  test('renders depreciation chart', () => {
    renderWithProviders(<VehicleDetails />);
    
    expect(screen.getByTestId('depreciation-chart')).toBeInTheDocument();
  });

  test('exports vehicle report', async () => {
    global.URL.createObjectURL = jest.fn();
    
    renderWithProviders(<VehicleDetails />);
    
    const exportButton = screen.getByText(/export/i);
    fireEvent.click(exportButton);

    await waitFor(() => {
      expect(global.URL.createObjectURL).toHaveBeenCalled();
    });
  });

  test('shares vehicle details', () => {
    const mockShare = jest.fn();
    global.navigator.share = mockShare;

    renderWithProviders(<VehicleDetails />);
    
    const shareButton = screen.getByLabelText(/share/i);
    fireEvent.click(shareButton);

    expect(mockShare).toHaveBeenCalled();
  });
});
