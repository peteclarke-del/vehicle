import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import '@testing-library/jest-dom';
import Stock from '../pages/Stock';

const mockApi = {
  get: jest.fn(),
  post: jest.fn(),
  put: jest.fn(),
};

const mockFetchArrayData = jest.fn();

jest.mock('react-i18next', () => ({
  useTranslation: () => ({ t: (k) => k }),
}));

jest.mock('../contexts/AuthContext', () => ({
  useAuth: () => ({ api: mockApi }),
}));

jest.mock('../hooks/useApiData', () => ({
  fetchArrayData: (...args) => mockFetchArrayData(...args),
}));

jest.mock('../components/KnightRiderLoader', () => () => <div data-testid="loader">Loading...</div>);

jest.mock('../utils/logger', () => ({ error: jest.fn(), warn: jest.fn() }));

describe('Stock page', () => {
  beforeEach(() => {
    jest.clearAllMocks();

    mockApi.get.mockImplementation((url) => {
      if (url === '/vehicle-types') {
        return Promise.resolve({ data: [{ id: 1, name: 'Car' }] });
      }
      if (url === '/part-categories') {
        return Promise.resolve({ data: [] });
      }
      if (url.includes('/consumable-types')) {
        return Promise.resolve({ data: [] });
      }
      return Promise.resolve({ data: [] });
    });

    mockApi.put.mockResolvedValue({ data: { success: true } });

    mockFetchArrayData.mockResolvedValue([
      {
        id: 1,
        vehicleTypeId: 1,
        itemType: 'part',
        category: 'Oil Filter',
        description: 'Bosch Oil Filter',
        partNumber: 'BOF-1',
        supplier: 'ECP',
        price: '9.99',
        quantity: '3.00',
        purchaseDate: '2026-05-12',
        updatedAt: '2026-05-12T10:00:00Z',
      },
      {
        id: 2,
        vehicleTypeId: 1,
        itemType: 'consumable',
        category: 'Coolant',
        description: 'Blue Coolant',
        partNumber: null,
        supplier: 'Euro Car Parts',
        price: '12.00',
        quantity: '2.00',
        purchaseDate: '2026-05-11',
        updatedAt: '2026-05-11T10:00:00Z',
      },
    ]);
  });

  test('filters rows using search input', async () => {
    render(<Stock />);

    await waitFor(() => {
      expect(screen.getByText('Bosch Oil Filter')).toBeInTheDocument();
    });

    fireEvent.change(screen.getByPlaceholderText('common.search'), {
      target: { value: 'coolant' },
    });

    expect(screen.queryByText('Bosch Oil Filter')).not.toBeInTheDocument();
    expect(screen.getByText('Blue Coolant')).toBeInTheDocument();
  });

  test('clicking row populates form and edit updates record with success ribbon', async () => {
    render(<Stock />);

    await waitFor(() => {
      expect(screen.getByText('Bosch Oil Filter')).toBeInTheDocument();
    });

    fireEvent.click(screen.getByText('Bosch Oil Filter'));

    expect(screen.getByLabelText('stock.description')).toHaveValue('Bosch Oil Filter');
    expect(screen.getByRole('button', { name: 'common.save' })).toBeInTheDocument();

    fireEvent.change(screen.getByLabelText('stock.description'), {
      target: { value: 'Bosch Oil Filter Updated' },
    });

    fireEvent.click(screen.getByRole('button', { name: 'common.save' }));

    await waitFor(() => {
      expect(mockApi.put).toHaveBeenCalledWith(
        '/stock-items/1',
        expect.objectContaining({
          description: 'Bosch Oil Filter Updated',
          quantity: 3,
        })
      );
    });

    await waitFor(() => {
      expect(screen.queryByLabelText('stock.description')).not.toBeInTheDocument();
    });
  });

  test('creating consumable stock sends part number and manufacturer', async () => {
    mockApi.post.mockResolvedValue({ data: { success: true } });

    render(<Stock />);

    await waitFor(() => {
      expect(screen.getByText('Bosch Oil Filter')).toBeInTheDocument();
    });

    fireEvent.click(screen.getByRole('button', { name: 'common.add' }));

    fireEvent.mouseDown(screen.getByLabelText('stock.type'));
    fireEvent.click(screen.getByRole('option', { name: 'consumables.title' }));
    fireEvent.change(screen.getByLabelText('stock.category'), {
      target: { value: 'Oil' },
    });
    fireEvent.change(screen.getByLabelText('stock.quantity'), {
      target: { value: '2' },
    });
    fireEvent.change(screen.getByLabelText('stock.partNumber'), {
      target: { value: 'C-123' },
    });
    fireEvent.change(screen.getByLabelText('stock.brand'), {
      target: { value: 'BrandCo' },
    });

    fireEvent.click(screen.getByRole('button', { name: 'common.save' }));

    await waitFor(() => {
      expect(mockApi.post).toHaveBeenCalledWith(
        '/stock-items/adjust',
        expect.objectContaining({
          itemType: 'consumable',
          category: 'Oil',
          delta: 2,
          partNumber: 'C-123',
          manufacturer: 'BrandCo',
        })
      );
    });
  });
});
