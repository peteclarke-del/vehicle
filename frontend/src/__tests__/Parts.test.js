import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { BrowserRouter } from 'react-router-dom';
import Parts from '../pages/Parts';
import { AuthContext } from '../contexts/AuthContext';

// Parts fetches vehicles itself via fetchArrayData('/vehicles')
jest.mock('../hooks/useApiData', () => ({
  useApiData: jest.fn(() => ({ data: [], loading: false, error: null, fetchData: jest.fn(), setData: jest.fn() })),
  fetchArrayData: jest.fn(),
}));

// Mock the complex dialogs to prevent hangs from nested components
jest.mock('../components/PartDialog', () => ({
  __esModule: true,
  default: ({ open }) => open ? require('react').createElement('div', { role: 'dialog', 'data-testid': 'part-dialog' }) : null,
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

const mockParts = [
  {
    id: 1,
    vehicleId: 1,
    description: 'Air Filter',
    partNumber: 'AF-001',
    manufacturer: 'Bosch',
    cost: 25.99,
    purchaseDate: '2026-01-10',
    fitDate: '2026-01-10',
    mileageFitted: 50000,
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

describe('Parts Component', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    fetchArrayData.mockImplementation((api, url) => {
      if (url === '/vehicles') return Promise.resolve(mockVehicles);
      return Promise.resolve([]);
    });
  });

  test('renders parts page title', async () => {
    renderWithProviders(<Parts />);

    await waitFor(() => {
      expect(screen.getByText('parts.title')).toBeInTheDocument();
    });
  });

  test('displays loading state initially', () => {
    fetchArrayData.mockReturnValue(new Promise(() => {}));

    renderWithProviders(<Parts />);

    expect(screen.queryByText('parts.title')).not.toBeInTheDocument();
  });

  test('displays no vehicles message when no vehicles exist', async () => {
    fetchArrayData.mockResolvedValue([]);

    renderWithProviders(<Parts />);

    await waitFor(() => {
      expect(screen.getByText('common.noVehicles')).toBeInTheDocument();
    });
  });

  test('loads and displays parts', async () => {
    fetchArrayData.mockImplementation((api, url) => {
      if (url === '/vehicles') return Promise.resolve(mockVehicles);
      return Promise.resolve(mockParts);
    });

    renderWithProviders(<Parts />);

    await waitFor(() => {
      expect(screen.getByText('Air Filter')).toBeInTheDocument();
    });
  });

  test('shows add part button', async () => {
    renderWithProviders(<Parts />);

    await waitFor(() => {
      expect(screen.getByText('parts.addPart')).toBeInTheDocument();
    });
  });

  test('shows add part button (disabled for all-vehicles view)', async () => {
    renderWithProviders(<Parts />);

    // Button exists but disabled when '__all__' vehicles selected
    const addButton = await screen.findByText('parts.addPart');
    expect(addButton).toBeInTheDocument();
    const btn = addButton.closest('button');
    expect(btn).toBeDisabled();
  });

  test('handles delete with confirmation', async () => {
    fetchArrayData.mockImplementation((api, url) => {
      if (url === '/vehicles') return Promise.resolve(mockVehicles);
      return Promise.resolve(mockParts);
    });
    mockApi.delete.mockResolvedValue({});
    global.confirm = jest.fn(() => true);

    renderWithProviders(<Parts />);

    await waitFor(() => {
      expect(screen.getByText('Air Filter')).toBeInTheDocument();
    });

    const deleteButton = screen.getByRole('button', { name: 'common.delete' });
    fireEvent.click(deleteButton);

    await waitFor(() => {
      expect(mockApi.delete).toHaveBeenCalledWith('/parts/1');
    });
  });

  test('shows no records message when vehicle has no parts', async () => {
    renderWithProviders(<Parts />);

    await waitFor(() => {
      expect(screen.getByText('common.noRecords')).toBeInTheDocument();
    });
  });

  test('shows parts column headers', async () => {
    renderWithProviders(<Parts />);

    await waitFor(() => {
      expect(screen.getByText('common.description')).toBeInTheDocument();
      expect(screen.getByText('common.partNumber')).toBeInTheDocument();
    });
  });
});
