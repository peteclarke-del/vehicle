import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { BrowserRouter } from 'react-router-dom';
import Consumables from '../pages/Consumables';
import { AuthContext } from '../contexts/AuthContext';

// Consumables fetches vehicles itself via fetchArrayData('/vehicles')
jest.mock('../hooks/useApiData', () => ({
  useApiData: jest.fn(() => ({ data: [], loading: false, error: null, fetchData: jest.fn(), setData: jest.fn() })),
  fetchArrayData: jest.fn(),
}));

// Mock the complex dialogs to prevent hangs from nested components
jest.mock('../components/ConsumableDialog', () => ({
  __esModule: true,
  default: ({ open }) => open ? require('react').createElement('div', { role: 'dialog', 'data-testid': 'consumable-dialog' }) : null,
}));
jest.mock('../components/ServiceDialog', () => ({
  __esModule: true,
  default: ({ open }) => open ? require('react').createElement('div', { role: 'dialog', 'data-testid': 'service-dialog' }) : null,
}));

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

const mockConsumables = [
  {
    id: 1,
    vehicleId: 1,
    description: 'Engine Oil',
    brand: 'Castrol',
    cost: 45.00,
    purchaseDate: '2026-01-10',
    fitDate: '2026-01-10',
    mileageFitted: 50000,
    mileageInterval: 10000,
    nextMileage: 60000,
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

describe('Consumables Component', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    fetchArrayData.mockImplementation((api, url) => {
      if (url === '/vehicles') return Promise.resolve(mockVehicles);
      return Promise.resolve([]);
    });
  });

  test('renders consumables page title', async () => {
    renderWithProviders(<Consumables />);

    await waitFor(() => {
      expect(screen.getByText('consumables.title')).toBeInTheDocument();
    });
  });

  test('displays loading state initially', () => {
    fetchArrayData.mockReturnValue(new Promise(() => {}));

    renderWithProviders(<Consumables />);

    expect(screen.queryByText('consumables.title')).not.toBeInTheDocument();
  });

  test('displays no vehicles message when no vehicles exist', async () => {
    fetchArrayData.mockResolvedValue([]);

    renderWithProviders(<Consumables />);

    await waitFor(() => {
      expect(screen.getByText('common.noVehicles')).toBeInTheDocument();
    });
  });

  test('loads and displays consumables', async () => {
    fetchArrayData.mockImplementation((api, url) => {
      if (url === '/vehicles') return Promise.resolve(mockVehicles);
      return Promise.resolve(mockConsumables);
    });

    renderWithProviders(<Consumables />);

    await waitFor(() => {
      expect(screen.getByText('Engine Oil')).toBeInTheDocument();
    });
  });

  test('shows add consumable button', async () => {
    renderWithProviders(<Consumables />);

    await waitFor(() => {
      expect(screen.getByText('consumables.addConsumable')).toBeInTheDocument();
    });
  });

  test('shows add consumable button (disabled for all-vehicles view)', async () => {
    renderWithProviders(<Consumables />);

    // Button exists but disabled when '__all__' vehicles selected
    const addButton = await screen.findByText('consumables.addConsumable');
    expect(addButton).toBeInTheDocument();
    const btn = addButton.closest('button');
    expect(btn).toBeDisabled();
  });

  test('handles delete with confirmation', async () => {
    fetchArrayData.mockImplementation((api, url) => {
      if (url === '/vehicles') return Promise.resolve(mockVehicles);
      return Promise.resolve(mockConsumables);
    });
    mockApi.delete.mockResolvedValue({});
    global.confirm = jest.fn(() => true);

    renderWithProviders(<Consumables />);

    await waitFor(() => {
      expect(screen.getByText('Engine Oil')).toBeInTheDocument();
    });

    // Consumables delete buttons don't have aria-label, click icon buttons until delete is called
    const allButtons = screen.getAllByRole('button');
    for (const btn of allButtons) {
      if (!btn.disabled) {
        fireEvent.click(btn);
        if (mockApi.delete.mock.calls.length > 0) break;
      }
    }

    await waitFor(() => {
      expect(mockApi.delete).toHaveBeenCalledWith('/consumables/1');
    });
  });

  test('shows no records message when vehicle has no consumables', async () => {
    renderWithProviders(<Consumables />);

    await waitFor(() => {
      expect(screen.getByText('common.noRecords')).toBeInTheDocument();
    });
  });

  test('shows consumables column headers', async () => {
    renderWithProviders(<Consumables />);

    await waitFor(() => {
      expect(screen.getByText('consumables.name')).toBeInTheDocument();
      expect(screen.getByText('consumables.cost')).toBeInTheDocument();
    });
  });
});
