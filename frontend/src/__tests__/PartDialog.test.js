import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import PartDialog from '../components/PartDialog';
import { AuthContext } from '../context/AuthContext';
import '@testing-library/jest-dom';

// Mock i18next
jest.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: (key) => key,
  }),
}));

const renderWithProviders = (component) => {
  const mockAuthContext = {
    user: { id: 1, email: 'test@example.com' },
    token: 'mock-token',
  };

  return render(
    <AuthContext.Provider value={mockAuthContext}>
      {component}
    </AuthContext.Provider>
  );
};

describe('PartDialog Component', () => {
  const mockOnClose = jest.fn();
  const mockOnSave = jest.fn();

  const defaultProps = {
    open: true,
    onClose: mockOnClose,
    onSave: mockOnSave,
    vehicleId: 1,
  };

  beforeEach(() => {
    global.fetch = jest.fn(() =>
      Promise.resolve({
        ok: true,
        json: () => Promise.resolve({ id: 1 }),
      })
    );
  });

  afterEach(() => {
    jest.clearAllMocks();
  });

  test('renders dialog when open', () => {
    renderWithProviders(<PartDialog {...defaultProps} />);
    expect(screen.getByText('part.newTitle')).toBeInTheDocument();
  });

  test('displays all form fields', () => {
    renderWithProviders(<PartDialog {...defaultProps} />);
    
    expect(screen.getByLabelText(/part name/i)).toBeInTheDocument();
    expect(screen.getByLabelText(/category/i)).toBeInTheDocument();
    expect(screen.getByLabelText(/price/i)).toBeInTheDocument();
    expect(screen.getByLabelText(/quantity/i)).toBeInTheDocument();
    expect(screen.getByLabelText(/supplier/i)).toBeInTheDocument();
  });

  test('submits form with valid data', async () => {
    renderWithProviders(<PartDialog {...defaultProps} />);
    
    fireEvent.change(screen.getByLabelText(/part name/i), {
      target: { value: 'Brake Pads' },
    });
    fireEvent.change(screen.getByLabelText(/category/i), {
      target: { value: 'Brakes' },
    });
    fireEvent.change(screen.getByLabelText(/price/i), {
      target: { value: '45.99' },
    });
    fireEvent.change(screen.getByLabelText(/quantity/i), {
      target: { value: '2' },
    });

    const saveButton = screen.getByText(/save/i);
    fireEvent.click(saveButton);

    await waitFor(() => {
      expect(global.fetch).toHaveBeenCalledWith(
        expect.stringContaining('/parts'),
        expect.objectContaining({
          method: 'POST',
          body: expect.stringContaining('"name":"Brake Pads"'),
        })
      );
      expect(mockOnSave).toHaveBeenCalled();
    });
  });

  test('calculates total cost for multiple quantities', () => {
    renderWithProviders(<PartDialog {...defaultProps} />);
    
    fireEvent.change(screen.getByLabelText(/price/i), {
      target: { value: '45.99' },
    });
    fireEvent.change(screen.getByLabelText(/quantity/i), {
      target: { value: '2' },
    });

    expect(screen.getByText(/total: £91.98/i)).toBeInTheDocument();
  });

  test('validates required fields', async () => {
    renderWithProviders(<PartDialog {...defaultProps} />);
    
    const saveButton = screen.getByText(/save/i);
    fireEvent.click(saveButton);

    await waitFor(() => {
      expect(screen.getByText(/part name is required/i)).toBeInTheDocument();
      expect(screen.getByText(/category is required/i)).toBeInTheDocument();
    });

    expect(global.fetch).not.toHaveBeenCalled();
  });

  test('populates form in edit mode', () => {
    const part = {
      id: 1,
      name: 'Oil Filter',
      category: 'Filters',
      price: 12.99,
      quantity: 1,
      supplier: 'AutoParts Co',
      sku: 'OF-12345',
    };

    renderWithProviders(<PartDialog {...defaultProps} part={part} />);
    
    expect(screen.getByDisplayValue('Oil Filter')).toBeInTheDocument();
    expect(screen.getByDisplayValue('Filters')).toBeInTheDocument();
    expect(screen.getByDisplayValue('12.99')).toBeInTheDocument();
    expect(screen.getByDisplayValue('AutoParts Co')).toBeInTheDocument();
  });

  test('displays category options', () => {
    renderWithProviders(<PartDialog {...defaultProps} />);
    
    const categorySelect = screen.getByLabelText(/category/i);
    fireEvent.click(categorySelect);

    expect(screen.getByText(/brakes/i)).toBeInTheDocument();
    expect(screen.getByText(/filters/i)).toBeInTheDocument();
    expect(screen.getByText(/engine/i)).toBeInTheDocument();
    expect(screen.getByText(/suspension/i)).toBeInTheDocument();
  });

  test('handles image upload', async () => {
    renderWithProviders(<PartDialog {...defaultProps} />);
    
    const file = new File(['image'], 'part.jpg', { type: 'image/jpeg' });
    const fileInput = screen.getByLabelText(/upload image/i);

    fireEvent.change(fileInput, { target: { files: [file] } });

    await waitFor(() => {
      expect(screen.getByText('part.jpg')).toBeInTheDocument();
    });
  });

  test('displays scrape from URL button', () => {
    renderWithProviders(<PartDialog {...defaultProps} />);
    expect(screen.getByText(/scrape from url/i)).toBeInTheDocument();
  });

  test('scrapes part data from URL', async () => {
    global.fetch = jest.fn(() =>
      Promise.resolve({
        ok: true,
        json: () => Promise.resolve({
          name: 'Brake Pads Set',
          price: 45.99,
          image: 'https://example.com/image.jpg',
        }),
      })
    );

    renderWithProviders(<PartDialog {...defaultProps} />);
    
    const urlInput = screen.getByLabelText(/product url/i);
    fireEvent.change(urlInput, {
      target: { value: 'https://amazon.com/dp/B001234567' },
    });

    const scrapeButton = screen.getByText(/scrape from url/i);
    fireEvent.click(scrapeButton);

    await waitFor(() => {
      expect(screen.getByDisplayValue('Brake Pads Set')).toBeInTheDocument();
      expect(screen.getByDisplayValue('45.99')).toBeInTheDocument();
    });
  });

  test('displays SKU field', () => {
    renderWithProviders(<PartDialog {...defaultProps} />);
    expect(screen.getByLabelText(/sku/i)).toBeInTheDocument();
  });

  test('displays purchase date field', () => {
    renderWithProviders(<PartDialog {...defaultProps} />);
    expect(screen.getByLabelText(/purchase date/i)).toBeInTheDocument();
  });

  test('displays notes/description field', () => {
    renderWithProviders(<PartDialog {...defaultProps} />);
    expect(screen.getByLabelText(/notes/i)).toBeInTheDocument();
  });

  test('validates positive price', async () => {
    renderWithProviders(<PartDialog {...defaultProps} />);
    
    fireEvent.change(screen.getByLabelText(/price/i), {
      target: { value: '-10' },
    });

    const saveButton = screen.getByText(/save/i);
    fireEvent.click(saveButton);

    await waitFor(() => {
      expect(screen.getByText(/price must be positive/i)).toBeInTheDocument();
    });
  });

  test('validates positive quantity', async () => {
    renderWithProviders(<PartDialog {...defaultProps} />);
    
    fireEvent.change(screen.getByLabelText(/quantity/i), {
      target: { value: '0' },
    });

    const saveButton = screen.getByText(/save/i);
    fireEvent.click(saveButton);

    await waitFor(() => {
      expect(screen.getByText(/quantity must be at least 1/i)).toBeInTheDocument();
    });
  });

  test('closes dialog on cancel', () => {
    renderWithProviders(<PartDialog {...defaultProps} />);
    
    const cancelButton = screen.getByText(/cancel/i);
    fireEvent.click(cancelButton);

    expect(mockOnClose).toHaveBeenCalled();
  });

  test('displays error message on save failure', async () => {
    global.fetch = jest.fn(() =>
      Promise.reject(new Error('Network error'))
    );

    renderWithProviders(<PartDialog {...defaultProps} />);
    
    fireEvent.change(screen.getByLabelText(/part name/i), {
      target: { value: 'Test Part' },
    });
    fireEvent.change(screen.getByLabelText(/category/i), {
      target: { value: 'Brakes' },
    });

    const saveButton = screen.getByText(/save/i);
    fireEvent.click(saveButton);

    await waitFor(() => {
      expect(screen.getByText(/error saving part/i)).toBeInTheDocument();
    });
  });

  test('displays warranty information field', () => {
    renderWithProviders(<PartDialog {...defaultProps} />);
    expect(screen.getByLabelText(/warranty/i)).toBeInTheDocument();
  });

  test('displays installation date field', () => {
    renderWithProviders(<PartDialog {...defaultProps} />);
    expect(screen.getByLabelText(/installation date/i)).toBeInTheDocument();
  });

  test('links to service record', () => {
    renderWithProviders(<PartDialog {...defaultProps} />);
    expect(screen.getByLabelText(/link to service record/i)).toBeInTheDocument();
  });

  test('displays supported platforms for scraping', () => {
    renderWithProviders(<PartDialog {...defaultProps} />);
    
    const urlInput = screen.getByLabelText(/product url/i);
    fireEvent.focus(urlInput);

    expect(screen.getByText(/amazon/i)).toBeInTheDocument();
    expect(screen.getByText(/ebay/i)).toBeInTheDocument();
    expect(screen.getByText(/shopify/i)).toBeInTheDocument();
  });
});
