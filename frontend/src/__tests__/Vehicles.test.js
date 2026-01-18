import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { BrowserRouter } from 'react-router-dom';
import Vehicles from '../pages/Vehicles';
import { AuthContext } from '../context/AuthContext';

// Mock react-i18next
jest.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: (key) => key,
  }),
}));

// Mock useApiData hook
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

describe('Vehicles Component', () => {
  const mockAuthContext = {
    user: { id: 1, email: 'test@example.com' },
    logout: jest.fn(),
  };

  const mockVehicles = [
    {
      id: 1,
      registration: 'ABC123',
      make: 'Toyota',
      model: 'Corolla',
      year: 2020,
      colour: 'Silver',
      fuelType: 'Petrol',
      currentMileage: 50000,
      purchasePrice: 15000.00,
      purchaseDate: '2020-01-15',
    },
    {
      id: 2,
      registration: 'XYZ789',
      make: 'Honda',
      model: 'Civic',
      year: 2019,
      colour: 'Blue',
      fuelType: 'Diesel',
      currentMileage: 60000,
      purchasePrice: 12000.00,
      purchaseDate: '2019-06-20',
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

  test('renders vehicles page title', () => {
    useApiData.mockReturnValue({ data: mockVehicles, loading: false, error: null });

    renderWithProviders(<Vehicles />);

    expect(screen.getByText('vehicles.title')).toBeInTheDocument();
  });

  test('displays loading state', () => {
    useApiData.mockReturnValue({ data: null, loading: true, error: null });

    renderWithProviders(<Vehicles />);

    expect(screen.getByRole('progressbar')).toBeInTheDocument();
  });

  test('displays error message when data fetch fails', () => {
    useApiData.mockReturnValue({
      data: null,
      loading: false,
      error: 'Failed to fetch vehicles',
    });

    renderWithProviders(<Vehicles />);

    expect(screen.getByText(/error/i)).toBeInTheDocument();
  });

  test('loads and displays vehicle list', async () => {
    useApiData.mockReturnValue({ data: mockVehicles, loading: false, error: null });

    renderWithProviders(<Vehicles />);

    await waitFor(() => {
      expect(screen.getByText('ABC123')).toBeInTheDocument();
      expect(screen.getByText('Toyota Corolla')).toBeInTheDocument();
      expect(screen.getByText('XYZ789')).toBeInTheDocument();
      expect(screen.getByText('Honda Civic')).toBeInTheDocument();
    });
  });

  test('opens add dialog when add button clicked', async () => {
    useApiData.mockReturnValue({ data: mockVehicles, loading: false, error: null });

    renderWithProviders(<Vehicles />);

    const addButton = screen.getByText('vehicles.addVehicle');
    fireEvent.click(addButton);

    await waitFor(() => {
      expect(screen.getByText('vehicles.addNewVehicle')).toBeInTheDocument();
    });
  });

  test('opens edit dialog when edit button clicked', async () => {
    useApiData.mockReturnValue({ data: mockVehicles, loading: false, error: null });

    renderWithProviders(<Vehicles />);

    await waitFor(() => {
      const editButtons = screen.getAllByLabelText('vehicles.edit');
      fireEvent.click(editButtons[0]);
    });

    expect(screen.getByText('vehicles.editVehicle')).toBeInTheDocument();
    expect(screen.getByDisplayValue('ABC123')).toBeInTheDocument();
  });

  test('handles delete confirmation and API call', async () => {
    window.confirm = jest.fn(() => true);
    mockApi.delete.mockResolvedValueOnce({});

    useApiData.mockReturnValue({
      data: mockVehicles,
      loading: false,
      error: null,
      refresh: jest.fn(),
    });

    renderWithProviders(<Vehicles />);

    await waitFor(() => {
      const deleteButtons = screen.getAllByLabelText('vehicles.delete');
      fireEvent.click(deleteButtons[0]);
    });

    expect(window.confirm).toHaveBeenCalledWith('vehicles.confirmDelete');
    expect(mockApi.delete).toHaveBeenCalledWith('/vehicles/1');
  });

  test('cancels delete when user declines confirmation', async () => {
    window.confirm = jest.fn(() => false);

    useApiData.mockReturnValue({ data: mockVehicles, loading: false, error: null });

    renderWithProviders(<Vehicles />);

    await waitFor(() => {
      const deleteButtons = screen.getAllByLabelText('vehicles.delete');
      fireEvent.click(deleteButtons[0]);
    });

    expect(window.confirm).toHaveBeenCalled();
    expect(mockApi.delete).not.toHaveBeenCalled();
  });

  test('displays vehicle details in cards', async () => {
    useApiData.mockReturnValue({ data: mockVehicles, loading: false, error: null });

    renderWithProviders(<Vehicles />);

    await waitFor(() => {
      expect(screen.getByText('2020')).toBeInTheDocument();
      expect(screen.getByText('Silver')).toBeInTheDocument();
      expect(screen.getByText('Petrol')).toBeInTheDocument();
      expect(screen.getByText('50,000')).toBeInTheDocument(); // Mileage
      expect(screen.getByText('£15,000.00')).toBeInTheDocument(); // Purchase price
    });
  });

  test('filters vehicles by search term', async () => {
    useApiData.mockReturnValue({ data: mockVehicles, loading: false, error: null });

    renderWithProviders(<Vehicles />);

    const searchInput = screen.getByLabelText('vehicles.search');
    fireEvent.change(searchInput, { target: { value: 'ABC' } });

    await waitFor(() => {
      expect(screen.getByText('ABC123')).toBeInTheDocument();
      expect(screen.queryByText('XYZ789')).not.toBeInTheDocument();
    });
  });

  test('filters vehicles by make', async () => {
    useApiData.mockReturnValue({ data: mockVehicles, loading: false, error: null });

    renderWithProviders(<Vehicles />);

    const makeFilter = screen.getByLabelText('vehicles.filterByMake');
    fireEvent.change(makeFilter, { target: { value: 'Toyota' } });

    await waitFor(() => {
      expect(screen.getByText('Toyota Corolla')).toBeInTheDocument();
      expect(screen.queryByText('Honda Civic')).not.toBeInTheDocument();
    });
  });

  test('sorts vehicles by registration', async () => {
    useApiData.mockReturnValue({ data: mockVehicles, loading: false, error: null });

    renderWithProviders(<Vehicles />);

    const sortSelect = screen.getByLabelText('vehicles.sortBy');
    fireEvent.change(sortSelect, { target: { value: 'registration' } });

    await waitFor(() => {
      const registrations = screen.getAllByText(/^[A-Z]{3}\d{3}$/);
      expect(registrations[0]).toHaveTextContent('ABC123');
      expect(registrations[1]).toHaveTextContent('XYZ789');
    });
  });

  test('sorts vehicles by year descending', async () => {
    useApiData.mockReturnValue({ data: mockVehicles, loading: false, error: null });

    renderWithProviders(<Vehicles />);

    const sortSelect = screen.getByLabelText('vehicles.sortBy');
    fireEvent.change(sortSelect, { target: { value: 'year_desc' } });

    await waitFor(() => {
      const years = screen.getAllByText(/^\d{4}$/);
      expect(parseInt(years[0].textContent)).toBeGreaterThan(parseInt(years[1].textContent));
    });
  });

  test('displays empty state when no vehicles exist', () => {
    useApiData.mockReturnValue({ data: [], loading: false, error: null });

    renderWithProviders(<Vehicles />);

    expect(screen.getByText('vehicles.noVehicles')).toBeInTheDocument();
    expect(screen.getByText('vehicles.addFirstVehicle')).toBeInTheDocument();
  });

  test('navigates to vehicle details page when clicking view button', async () => {
    useApiData.mockReturnValue({ data: mockVehicles, loading: false, error: null });

    renderWithProviders(<Vehicles />);

    await waitFor(() => {
      const viewButtons = screen.getAllByLabelText('vehicles.view');
      const firstButton = viewButtons[0];
      
      expect(firstButton.closest('a')).toHaveAttribute('href', '/vehicles/1');
    });
  });

  test('displays vehicle count badge', async () => {
    useApiData.mockReturnValue({ data: mockVehicles, loading: false, error: null });

    renderWithProviders(<Vehicles />);

    await waitFor(() => {
      expect(screen.getByText('2 vehicles.vehicles')).toBeInTheDocument();
    });
  });

  test('groups vehicles by make', async () => {
    useApiData.mockReturnValue({ data: mockVehicles, loading: false, error: null });

    renderWithProviders(<Vehicles />);

    const groupBySelect = screen.getByLabelText('vehicles.groupBy');
    fireEvent.change(groupBySelect, { target: { value: 'make' } });

    await waitFor(() => {
      expect(screen.getByText('Toyota')).toBeInTheDocument();
      expect(screen.getByText('Honda')).toBeInTheDocument();
    });
  });

  test('toggles between grid and list view', async () => {
    useApiData.mockReturnValue({ data: mockVehicles, loading: false, error: null });

    renderWithProviders(<Vehicles />);

    const viewToggle = screen.getByLabelText('vehicles.toggleView');
    fireEvent.click(viewToggle);

    await waitFor(() => {
      // Check that layout has changed (implementation specific)
      expect(viewToggle).toHaveAttribute('aria-pressed', 'true');
    });
  });

  test('exports vehicle list to CSV', async () => {
    useApiData.mockReturnValue({ data: mockVehicles, loading: false, error: null });

    const mockCreateObjectURL = jest.fn();
    window.URL.createObjectURL = mockCreateObjectURL;

    renderWithProviders(<Vehicles />);

    const exportButton = screen.getByText('vehicles.export');
    fireEvent.click(exportButton);

    await waitFor(() => {
      expect(mockCreateObjectURL).toHaveBeenCalled();
    });
  });

  test('displays total purchase value', async () => {
    useApiData.mockReturnValue({ data: mockVehicles, loading: false, error: null });

    renderWithProviders(<Vehicles />);

    await waitFor(() => {
      // Total: 15000 + 12000 = 27000
      expect(screen.getByText('vehicles.totalValue')).toBeInTheDocument();
      expect(screen.getByText('£27,000.00')).toBeInTheDocument();
    });
  });

  test('displays total mileage across all vehicles', async () => {
    useApiData.mockReturnValue({ data: mockVehicles, loading: false, error: null });

    renderWithProviders(<Vehicles />);

    await waitFor(() => {
      // Total: 50000 + 60000 = 110000
      expect(screen.getByText('vehicles.totalMileage')).toBeInTheDocument();
      expect(screen.getByText('110,000')).toBeInTheDocument();
    });
  });

  test('refreshes vehicle list when refresh button clicked', async () => {
    const mockRefresh = jest.fn();
    useApiData.mockReturnValue({
      data: mockVehicles,
      loading: false,
      error: null,
      refresh: mockRefresh,
    });

    renderWithProviders(<Vehicles />);

    const refreshButton = screen.getByLabelText('vehicles.refresh');
    fireEvent.click(refreshButton);

    expect(mockRefresh).toHaveBeenCalled();
  });
});
