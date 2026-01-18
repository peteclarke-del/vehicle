import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { BrowserRouter } from 'react-router-dom';
import Consumables from '../pages/Consumables';
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

describe('Consumables Page', () => {
  const mockConsumables = [
    {
      id: 1,
      type: 'Engine Oil',
      lastReplacementDate: '2023-01-15',
      lastReplacementMileage: 45000,
      nextReplacementMileage: 55000,
      replacementInterval: 10000,
      cost: 45.00,
      vehicleId: 1,
    },
    {
      id: 2,
      type: 'Brake Fluid',
      lastReplacementDate: '2022-06-20',
      lastReplacementMileage: 40000,
      nextReplacementMileage: 60000,
      replacementInterval: 20000,
      cost: 35.00,
      vehicleId: 1,
    },
  ];

  const mockVehicles = [
    { id: 1, registration: 'ABC123', mileage: 50000 },
    { id: 2, registration: 'DEF456', mileage: 30000 },
  ];

  beforeEach(() => {
    useApiData.mockImplementation((url) => {
      if (url.includes('/consumables')) {
        return { data: mockConsumables, loading: false, error: null };
      }
      if (url.includes('/vehicles')) {
        return { data: mockVehicles, loading: false, error: null };
      }
      return { data: null, loading: false, error: null };
    });

    global.fetch = jest.fn(() =>
      Promise.resolve({
        ok: true,
        json: () => Promise.resolve({}),
      })
    );
  });

  afterEach(() => {
    jest.clearAllMocks();
  });

  test('renders consumables page title', () => {
    renderWithProviders(<Consumables />);
    expect(screen.getByText('consumables.title')).toBeInTheDocument();
  });

  test('displays loading state', () => {
    useApiData.mockReturnValue({ data: null, loading: true, error: null });
    renderWithProviders(<Consumables />);
    expect(screen.getByRole('progressbar')).toBeInTheDocument();
  });

  test('loads and displays consumables', () => {
    renderWithProviders(<Consumables />);
    
    expect(screen.getByText('Engine Oil')).toBeInTheDocument();
    expect(screen.getByText('Brake Fluid')).toBeInTheDocument();
    expect(screen.getByText('£45.00')).toBeInTheDocument();
    expect(screen.getByText('£35.00')).toBeInTheDocument();
  });

  test('opens add dialog when add button clicked', () => {
    renderWithProviders(<Consumables />);
    
    const addButton = screen.getByText(/add consumable/i);
    fireEvent.click(addButton);

    expect(screen.getByText(/new consumable/i)).toBeInTheDocument();
  });

  test('opens edit dialog when edit button clicked', () => {
    renderWithProviders(<Consumables />);
    
    const editButtons = screen.getAllByLabelText(/edit/i);
    fireEvent.click(editButtons[0]);

    expect(screen.getByText(/edit consumable/i)).toBeInTheDocument();
    expect(screen.getByDisplayValue('Engine Oil')).toBeInTheDocument();
  });

  test('handles delete confirmation', async () => {
    global.confirm = jest.fn(() => true);
    
    renderWithProviders(<Consumables />);
    
    const deleteButtons = screen.getAllByLabelText(/delete/i);
    fireEvent.click(deleteButtons[0]);

    await waitFor(() => {
      expect(global.fetch).toHaveBeenCalledWith(
        expect.stringContaining('/consumables/1'),
        expect.objectContaining({ method: 'DELETE' })
      );
    });
  });

  test('calculates total consumable cost', () => {
    renderWithProviders(<Consumables />);
    
    // £45.00 + £35.00 = £80.00
    expect(screen.getByText(/£80.00/i)).toBeInTheDocument();
  });

  test('displays consumables due for replacement', () => {
    renderWithProviders(<Consumables />);
    
    // Engine Oil: last at 45k, next at 55k, current 50k = due in 5k miles
    expect(screen.getByText(/due in 5,000 miles/i)).toBeInTheDocument();
  });

  test('displays overdue consumables', () => {
    const overdueConsumables = [
      {
        id: 1,
        type: 'Engine Oil',
        lastReplacementMileage: 40000,
        nextReplacementMileage: 48000,
        cost: 45.00,
        vehicleId: 1,
      },
    ];

    useApiData.mockImplementation((url) => {
      if (url.includes('/consumables')) {
        return { data: overdueConsumables, loading: false, error: null };
      }
      if (url.includes('/vehicles')) {
        return { data: mockVehicles, loading: false, error: null };
      }
      return { data: null, loading: false, error: null };
    });

    renderWithProviders(<Consumables />);
    
    // Current mileage 50k, next replacement 48k = 2k miles overdue
    expect(screen.getByText(/overdue by 2,000 miles/i)).toBeInTheDocument();
  });

  test('filters consumables by type', () => {
    renderWithProviders(<Consumables />);
    
    const typeFilter = screen.getByLabelText(/filter by type/i);
    fireEvent.change(typeFilter, { target: { value: 'Engine Oil' } });

    expect(screen.getByText('Engine Oil')).toBeInTheDocument();
    expect(screen.queryByText('Brake Fluid')).not.toBeInTheDocument();
  });

  test('switches between vehicles', async () => {
    renderWithProviders(<Consumables />);
    
    const vehicleSelector = screen.getByRole('combobox');
    fireEvent.change(vehicleSelector, { target: { value: '2' } });

    await waitFor(() => {
      expect(useApiData).toHaveBeenCalledWith(
        expect.stringContaining('vehicleId=2'),
        expect.any(String)
      );
    });
  });

  test('displays empty state when no consumables exist', () => {
    useApiData.mockImplementation((url) => {
      if (url.includes('/consumables')) {
        return { data: [], loading: false, error: null };
      }
      return { data: mockVehicles, loading: false, error: null };
    });

    renderWithProviders(<Consumables />);
    
    expect(screen.getByText(/no consumables/i)).toBeInTheDocument();
  });

  test('sorts consumables by due date', () => {
    renderWithProviders(<Consumables />);
    
    const sortButton = screen.getByText(/sort/i);
    fireEvent.click(sortButton);

    const menuItem = screen.getByText(/due date/i);
    fireEvent.click(menuItem);

    const consumableTypes = screen.getAllByTestId('consumable-type');
    // Engine Oil due at 55k, Brake Fluid due at 60k
    expect(consumableTypes[0]).toHaveTextContent('Engine Oil');
    expect(consumableTypes[1]).toHaveTextContent('Brake Fluid');
  });

  test('displays replacement history', () => {
    renderWithProviders(<Consumables />);
    
    const historyButtons = screen.getAllByLabelText(/history/i);
    fireEvent.click(historyButtons[0]);

    expect(screen.getByText(/replacement history/i)).toBeInTheDocument();
  });

  test('calculates average replacement interval', () => {
    const consumablesWithHistory = [
      {
        id: 1,
        type: 'Engine Oil',
        lastReplacementMileage: 50000,
        nextReplacementMileage: 60000,
        replacementInterval: 10000,
        cost: 45.00,
        history: [
          { mileage: 40000 },
          { mileage: 50000 },
        ],
      },
    ];

    useApiData.mockImplementation((url) => {
      if (url.includes('/consumables')) {
        return { data: consumablesWithHistory, loading: false, error: null };
      }
      return { data: mockVehicles, loading: false, error: null };
    });

    renderWithProviders(<Consumables />);
    
    expect(screen.getByText(/average interval: 10,000 miles/i)).toBeInTheDocument();
  });

  test('displays consumable type badges', () => {
    renderWithProviders(<Consumables />);
    
    const oilBadge = screen.getByText('Engine Oil').closest('div');
    const fluidBadge = screen.getByText('Brake Fluid').closest('div');
    
    expect(oilBadge).toHaveClass('badge');
    expect(fluidBadge).toHaveClass('badge');
  });

  test('exports consumables list', async () => {
    global.URL.createObjectURL = jest.fn();
    
    renderWithProviders(<Consumables />);
    
    const exportButton = screen.getByText(/export/i);
    fireEvent.click(exportButton);

    await waitFor(() => {
      expect(global.URL.createObjectURL).toHaveBeenCalled();
    });
  });

  test('displays consumable status indicators', () => {
    renderWithProviders(<Consumables />);
    
    // Engine Oil due in 5k miles (current 50k, next 55k)
    const statusIndicator = screen.getByText(/due in 5,000 miles/i).closest('div');
    expect(statusIndicator).toHaveClass('status-indicator');
  });

  test('calculates annual consumable cost', () => {
    renderWithProviders(<Consumables />);
    
    // Engine Oil: £45 every 10k miles
    // Brake Fluid: £35 every 20k miles
    // If annual mileage is 10k: £45 + £17.50 = £62.50
    expect(screen.getByText(/£62.50/i)).toBeInTheDocument();
  });

  test('displays consumable recommendations', () => {
    renderWithProviders(<Consumables />);
    
    const recommendationsButton = screen.getByText(/recommendations/i);
    fireEvent.click(recommendationsButton);

    expect(screen.getByText(/recommended replacements/i)).toBeInTheDocument();
  });

  test('handles error state', () => {
    useApiData.mockReturnValue({ 
      data: null, 
      loading: false, 
      error: 'Failed to load consumables' 
    });
    
    renderWithProviders(<Consumables />);
    
    expect(screen.getByText(/Failed to load consumables/i)).toBeInTheDocument();
  });

  test('displays consumable details card', () => {
    renderWithProviders(<Consumables />);
    
    const detailButtons = screen.getAllByLabelText(/details/i);
    fireEvent.click(detailButtons[0]);

    expect(screen.getByText(/last replacement/i)).toBeInTheDocument();
    expect(screen.getByText(/next replacement/i)).toBeInTheDocument();
    expect(screen.getByText('45,000')).toBeInTheDocument();
    expect(screen.getByText('55,000')).toBeInTheDocument();
  });

  test('displays replacement schedule', () => {
    renderWithProviders(<Consumables />);
    
    const scheduleButton = screen.getByText(/schedule/i);
    fireEvent.click(scheduleButton);

    expect(screen.getByText(/replacement schedule/i)).toBeInTheDocument();
  });
});
