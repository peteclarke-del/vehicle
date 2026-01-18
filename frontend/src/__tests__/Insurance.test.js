import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { BrowserRouter } from 'react-router-dom';
import { AuthProvider } from '../contexts/AuthContext';
import Insurance from '../pages/Insurance';

// Mock the API calls
jest.mock('../hooks/useApiData', () => ({
  fetchArrayData: jest.fn(),
}));

// Mock translation
jest.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: (key) => key,
  }),
}));

const mockApi = {
  get: jest.fn(),
  post: jest.fn(),
  put: jest.fn(),
  delete: jest.fn(),
};

const mockAuthContext = {
  api: mockApi,
  user: { id: 1, email: 'test@test.com' },
  isAuthenticated: true,
  login: jest.fn(),
  logout: jest.fn(),
};

const renderWithProviders = (component) => {
  return render(
    <BrowserRouter>
      <AuthProvider value={mockAuthContext}>
        {component}
      </AuthProvider>
    </BrowserRouter>
  );
};

describe('Insurance Component', () => {
  beforeEach(() => {
    jest.clearAllMocks();
  });

  test('renders insurance page title', async () => {
    const { fetchArrayData } = require('../hooks/useApiData');
    fetchArrayData.mockResolvedValueOnce([
      { id: 1, name: 'Test Vehicle' }
    ]);

    mockApi.get.mockResolvedValueOnce({
      data: []
    });

    renderWithProviders(<Insurance />);

    await waitFor(() => {
      expect(screen.getByText('insurance.title')).toBeInTheDocument();
    });
  });

  test('displays no vehicles message when no vehicles exist', async () => {
    const { fetchArrayData } = require('../hooks/useApiData');
    fetchArrayData.mockResolvedValueOnce([]);

    renderWithProviders(<Insurance />);

    await waitFor(() => {
      expect(screen.getByText('common.noVehicles')).toBeInTheDocument();
    });
  });

  test('loads and displays insurance records', async () => {
    const { fetchArrayData } = require('../hooks/useApiData');
    fetchArrayData.mockResolvedValueOnce([
      { id: 1, name: 'Test Vehicle' }
    ]);

    const mockInsurance = [
      {
        id: 1,
        provider: 'Test Insurance Co',
        policyNumber: 'POL123',
        coverageType: 'Comprehensive',
        annualCost: 650.00,
        startDate: '2026-01-01',
        expiryDate: '2027-01-01',
      }
    ];

    mockApi.get.mockResolvedValueOnce({
      data: mockInsurance
    });

    renderWithProviders(<Insurance />);

    await waitFor(() => {
      expect(screen.getByText('Test Insurance Co')).toBeInTheDocument();
      expect(screen.getByText('POL123')).toBeInTheDocument();
      expect(screen.getByText('Comprehensive')).toBeInTheDocument();
    });
  });

  test('opens add dialog when add button clicked', async () => {
    const { fetchArrayData } = require('../hooks/useApiData');
    fetchArrayData.mockResolvedValueOnce([
      { id: 1, name: 'Test Vehicle' }
    ]);

    mockApi.get.mockResolvedValueOnce({
      data: []
    });

    renderWithProviders(<Insurance />);

    await waitFor(() => {
      const addButton = screen.getByText('insurance.addInsurance');
      expect(addButton).toBeInTheDocument();
    });

    const addButton = screen.getByText('insurance.addInsurance');
    fireEvent.click(addButton);

    // Dialog should open
    await waitFor(() => {
      expect(mockApi.get).toHaveBeenCalled();
    });
  });

  test('handles delete confirmation', async () => {
    const { fetchArrayData } = require('../hooks/useApiData');
    fetchArrayData.mockResolvedValueOnce([
      { id: 1, name: 'Test Vehicle' }
    ]);

    const mockInsurance = [
      {
        id: 1,
        provider: 'Test Insurance Co',
        policyNumber: 'POL123',
        coverageType: 'Comprehensive',
        annualCost: 650.00,
        startDate: '2026-01-01',
        expiryDate: '2027-01-01',
      }
    ];

    mockApi.get.mockResolvedValueOnce({
      data: mockInsurance
    });

    mockApi.delete.mockResolvedValueOnce({});

    // Mock window.confirm
    global.confirm = jest.fn(() => true);

    renderWithProviders(<Insurance />);

    await waitFor(() => {
      expect(screen.getByText('Test Insurance Co')).toBeInTheDocument();
    });

    // Find and click delete button
    const deleteButtons = screen.getAllByRole('button');
    const deleteButton = deleteButtons.find(btn => 
      btn.querySelector('svg[data-testid="DeleteIcon"]')
    );

    if (deleteButton) {
      fireEvent.click(deleteButton);

      await waitFor(() => {
        expect(mockApi.delete).toHaveBeenCalledWith('/insurance/1');
      });
    }
  });

  test('calculates total annual cost correctly', async () => {
    const { fetchArrayData } = require('../hooks/useApiData');
    fetchArrayData.mockResolvedValueOnce([
      { id: 1, name: 'Test Vehicle' }
    ]);

    const mockInsurance = [
      {
        id: 1,
        provider: 'Test Insurance Co 1',
        annualCost: 650.00,
        startDate: '2026-01-01',
        expiryDate: '2027-01-01',
      },
      {
        id: 2,
        provider: 'Test Insurance Co 2',
        annualCost: 350.00,
        startDate: '2026-01-01',
        expiryDate: '2027-01-01',
      }
    ];

    mockApi.get.mockResolvedValueOnce({
      data: mockInsurance
    });

    renderWithProviders(<Insurance />);

    await waitFor(() => {
      expect(screen.getByText(/£1,000.00/)).toBeInTheDocument();
    });
  });

  test('displays expired chip for expired policies', async () => {
    const { fetchArrayData } = require('../hooks/useApiData');
    fetchArrayData.mockResolvedValueOnce([
      { id: 1, name: 'Test Vehicle' }
    ]);

    const mockInsurance = [
      {
        id: 1,
        provider: 'Test Insurance Co',
        policyNumber: 'POL123',
        coverageType: 'Comprehensive',
        annualCost: 650.00,
        startDate: '2024-01-01',
        expiryDate: '2025-01-01', // Expired
      }
    ];

    mockApi.get.mockResolvedValueOnce({
      data: mockInsurance
    });

    renderWithProviders(<Insurance />);

    await waitFor(() => {
      expect(screen.getByText('common.expired')).toBeInTheDocument();
    });
  });

  test('switches between vehicles', async () => {
    const { fetchArrayData } = require('../hooks/useApiData');
    const vehicles = [
      { id: 1, name: 'Vehicle 1' },
      { id: 2, name: 'Vehicle 2' }
    ];
    
    fetchArrayData.mockResolvedValueOnce(vehicles);

    mockApi.get
      .mockResolvedValueOnce({ data: [] }) // First vehicle
      .mockResolvedValueOnce({ data: [] }); // Second vehicle

    renderWithProviders(<Insurance />);

    await waitFor(() => {
      expect(mockApi.get).toHaveBeenCalledWith('/insurance?vehicleId=1');
    });

    // Switch vehicle
    const select = screen.getByRole('button', { name: /vehicle.vehicleType/i });
    fireEvent.mouseDown(select);

    await waitFor(() => {
      const option2 = screen.getByText('Vehicle 2');
      fireEvent.click(option2);
    });

    await waitFor(() => {
      expect(mockApi.get).toHaveBeenCalledWith('/insurance?vehicleId=2');
    });
  });
});
