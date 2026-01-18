import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { BrowserRouter } from 'react-router-dom';
import Parts from '../pages/Parts';
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

describe('Parts Component', () => {
  const mockAuthContext = {
    user: { id: 1, email: 'test@example.com' },
  };

  const mockVehicles = [
    { id: 1, registration: 'ABC123', make: 'Toyota', model: 'Corolla' },
  ];

  const mockParts = [
    {
      id: 1,
      vehicleId: 1,
      name: 'Air Filter',
      partNumber: 'AF-12345',
      manufacturer: 'Mann Filter',
      price: 25.99,
      quantity: 1,
      purchaseDate: '2026-01-10',
      supplier: 'AutoParts Ltd',
      category: 'Filters',
    },
    {
      id: 2,
      vehicleId: 1,
      name: 'Brake Pads',
      partNumber: 'BP-67890',
      manufacturer: 'Brembo',
      price: 85.00,
      quantity: 1,
      purchaseDate: '2026-01-05',
      category: 'Brakes',
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

  test('renders parts page title', () => {
    useApiData
      .mockReturnValueOnce({ data: mockVehicles, loading: false, error: null })
      .mockReturnValueOnce({ data: mockParts, loading: false, error: null });

    renderWithProviders(<Parts />);

    expect(screen.getByText('parts.title')).toBeInTheDocument();
  });

  test('displays loading state', () => {
    useApiData.mockReturnValue({ data: null, loading: true, error: null });

    renderWithProviders(<Parts />);

    expect(screen.getByRole('progressbar')).toBeInTheDocument();
  });

  test('loads and displays parts', async () => {
    useApiData
      .mockReturnValueOnce({ data: mockVehicles, loading: false, error: null })
      .mockReturnValueOnce({ data: mockParts, loading: false, error: null });

    renderWithProviders(<Parts />);

    await waitFor(() => {
      expect(screen.getByText('Air Filter')).toBeInTheDocument();
      expect(screen.getByText('Brake Pads')).toBeInTheDocument();
      expect(screen.getByText('£25.99')).toBeInTheDocument();
      expect(screen.getByText('£85.00')).toBeInTheDocument();
    });
  });

  test('opens add dialog when add button clicked', async () => {
    useApiData
      .mockReturnValueOnce({ data: mockVehicles, loading: false, error: null })
      .mockReturnValueOnce({ data: mockParts, loading: false, error: null });

    renderWithProviders(<Parts />);

    const addButton = screen.getByText('parts.addPart');
    fireEvent.click(addButton);

    await waitFor(() => {
      expect(screen.getByText('parts.addNewPart')).toBeInTheDocument();
    });
  });

  test('opens scrape dialog when scrape button clicked', async () => {
    useApiData
      .mockReturnValueOnce({ data: mockVehicles, loading: false, error: null })
      .mockReturnValueOnce({ data: mockParts, loading: false, error: null });

    renderWithProviders(<Parts />);

    const scrapeButton = screen.getByText('parts.scrapeFromUrl');
    fireEvent.click(scrapeButton);

    await waitFor(() => {
      expect(screen.getByText('parts.enterUrl')).toBeInTheDocument();
    });
  });

  test('scrapes part from Amazon URL', async () => {
    const scrapedData = {
      name: 'Oil Filter',
      price: 15.99,
      manufacturer: 'Bosch',
    };

    mockApi.post.mockResolvedValueOnce(scrapedData);

    useApiData
      .mockReturnValueOnce({ data: mockVehicles, loading: false, error: null })
      .mockReturnValueOnce({ data: mockParts, loading: false, error: null });

    renderWithProviders(<Parts />);

    const scrapeButton = screen.getByText('parts.scrapeFromUrl');
    fireEvent.click(scrapeButton);

    await waitFor(() => {
      const urlInput = screen.getByLabelText('parts.url');
      fireEvent.change(urlInput, {
        target: { value: 'https://www.amazon.com/dp/B001234567' },
      });

      const scrapeSubmit = screen.getByText('parts.scrape');
      fireEvent.click(scrapeSubmit);
    });

    expect(mockApi.post).toHaveBeenCalledWith(
      '/parts/scrape',
      expect.objectContaining({
        url: 'https://www.amazon.com/dp/B001234567',
      })
    );
  });

  test('handles scraping errors', async () => {
    mockApi.post.mockRejectedValueOnce(new Error('Failed to scrape'));

    useApiData
      .mockReturnValueOnce({ data: mockVehicles, loading: false, error: null })
      .mockReturnValueOnce({ data: mockParts, loading: false, error: null });

    renderWithProviders(<Parts />);

    const scrapeButton = screen.getByText('parts.scrapeFromUrl');
    fireEvent.click(scrapeButton);

    await waitFor(() => {
      const urlInput = screen.getByLabelText('parts.url');
      fireEvent.change(urlInput, {
        target: { value: 'https://invalid-url' },
      });

      const scrapeSubmit = screen.getByText('parts.scrape');
      fireEvent.click(scrapeSubmit);
    });

    await waitFor(() => {
      expect(screen.getByText(/error/i)).toBeInTheDocument();
    });
  });

  test('handles delete confirmation', async () => {
    window.confirm = jest.fn(() => true);
    mockApi.delete.mockResolvedValueOnce({});

    useApiData
      .mockReturnValueOnce({ data: mockVehicles, loading: false, error: null })
      .mockReturnValueOnce({
        data: mockParts,
        loading: false,
        error: null,
        refresh: jest.fn(),
      });

    renderWithProviders(<Parts />);

    await waitFor(() => {
      const deleteButtons = screen.getAllByLabelText('parts.delete');
      fireEvent.click(deleteButtons[0]);
    });

    expect(window.confirm).toHaveBeenCalledWith('parts.confirmDelete');
    expect(mockApi.delete).toHaveBeenCalledWith('/parts/1');
  });

  test('calculates total parts cost', async () => {
    useApiData
      .mockReturnValueOnce({ data: mockVehicles, loading: false, error: null })
      .mockReturnValueOnce({ data: mockParts, loading: false, error: null });

    renderWithProviders(<Parts />);

    await waitFor(() => {
      expect(screen.getByText('parts.totalCost')).toBeInTheDocument();
      expect(screen.getByText('£110.99')).toBeInTheDocument(); // 25.99 + 85.00
    });
  });

  test('filters parts by category', async () => {
    useApiData
      .mockReturnValueOnce({ data: mockVehicles, loading: false, error: null })
      .mockReturnValueOnce({ data: mockParts, loading: false, error: null });

    renderWithProviders(<Parts />);

    const categoryFilter = screen.getByLabelText('parts.filterByCategory');
    fireEvent.change(categoryFilter, { target: { value: 'Filters' } });

    await waitFor(() => {
      expect(screen.getByText('Air Filter')).toBeInTheDocument();
      expect(screen.queryByText('Brake Pads')).not.toBeInTheDocument();
    });
  });

  test('searches parts by name', async () => {
    useApiData
      .mockReturnValueOnce({ data: mockVehicles, loading: false, error: null })
      .mockReturnValueOnce({ data: mockParts, loading: false, error: null });

    renderWithProviders(<Parts />);

    const searchInput = screen.getByLabelText('parts.search');
    fireEvent.change(searchInput, { target: { value: 'Brake' } });

    await waitFor(() => {
      expect(screen.getByText('Brake Pads')).toBeInTheDocument();
      expect(screen.queryByText('Air Filter')).not.toBeInTheDocument();
    });
  });

  test('switches between vehicles', async () => {
    useApiData
      .mockReturnValueOnce({ data: mockVehicles, loading: false, error: null })
      .mockReturnValueOnce({ data: mockParts, loading: false, error: null });

    renderWithProviders(<Parts />);

    await waitFor(() => {
      const vehicleSelect = screen.getByLabelText('parts.selectVehicle');
      fireEvent.change(vehicleSelect, { target: { value: '1' } });
    });

    expect(useApiData).toHaveBeenCalledWith(
      expect.stringContaining('vehicleId=1'),
      expect.any(Object)
    );
  });

  test('displays part details card', async () => {
    useApiData
      .mockReturnValueOnce({ data: mockVehicles, loading: false, error: null })
      .mockReturnValueOnce({ data: mockParts, loading: false, error: null });

    renderWithProviders(<Parts />);

    await waitFor(() => {
      expect(screen.getByText('AF-12345')).toBeInTheDocument();
      expect(screen.getByText('Mann Filter')).toBeInTheDocument();
      expect(screen.getByText('AutoParts Ltd')).toBeInTheDocument();
    });
  });

  test('displays empty state when no parts exist', () => {
    useApiData
      .mockReturnValueOnce({ data: mockVehicles, loading: false, error: null })
      .mockReturnValueOnce({ data: [], loading: false, error: null });

    renderWithProviders(<Parts />);

    expect(screen.getByText('parts.noParts')).toBeInTheDocument();
  });

  test('sorts parts by price', async () => {
    useApiData
      .mockReturnValueOnce({ data: mockVehicles, loading: false, error: null })
      .mockReturnValueOnce({ data: mockParts, loading: false, error: null });

    renderWithProviders(<Parts />);

    const sortSelect = screen.getByLabelText('parts.sortBy');
    fireEvent.change(sortSelect, { target: { value: 'price_desc' } });

    await waitFor(() => {
      const prices = screen.getAllByText(/£\d+\.\d{2}/);
      expect(prices[0]).toHaveTextContent('£85.00');
    });
  });

  test('displays supported scraping platforms', () => {
    useApiData
      .mockReturnValueOnce({ data: mockVehicles, loading: false, error: null })
      .mockReturnValueOnce({ data: mockParts, loading: false, error: null });

    renderWithProviders(<Parts />);

    const scrapeButton = screen.getByText('parts.scrapeFromUrl');
    fireEvent.click(scrapeButton);

    expect(screen.getByText(/Amazon/i)).toBeInTheDocument();
    expect(screen.getByText(/eBay/i)).toBeInTheDocument();
    expect(screen.getByText(/Shopify/i)).toBeInTheDocument();
  });
});
