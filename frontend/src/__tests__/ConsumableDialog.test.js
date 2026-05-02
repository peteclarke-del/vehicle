import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import ConsumableDialog from '../components/ConsumableDialog';
import '@testing-library/jest-dom';

// Stable mock references (must be outside jest.mock to avoid recreation)
const mockApi = {
  get: jest.fn().mockResolvedValue({ data: [] }),
  post: jest.fn().mockResolvedValue({ data: { id: 99 } }),
  put: jest.fn().mockResolvedValue({ data: { id: 1 } }),
};

const mockConvert = (v) => v;
const mockToKm = (v) => v;
const mockGetLabel = () => 'mi';
const mockDistanceResult = { convert: mockConvert, toKm: mockToKm, getLabel: mockGetLabel };

jest.mock('react-i18next', () => ({
  useTranslation: () => ({ t: (k) => k }),
}));

jest.mock('../contexts/AuthContext', () => ({
  useAuth: () => ({ api: mockApi, user: { id: 1 } }),
}));

jest.mock('../hooks/useDistance', () => ({
  useDistance: () => mockDistanceResult,
}));

jest.mock('../components/ReceiptUpload', () => () => null);
jest.mock('../components/UrlScraper', () => () => null);
jest.mock('../components/KnightRiderLoader', () => () => <div data-testid="loader">Loading...</div>);
jest.mock('../utils/logger', () => ({ error: jest.fn(), warn: jest.fn() }));

const renderDialog = (props = {}) =>
  render(
    <MemoryRouter>
      <ConsumableDialog
        open={true}
        onClose={jest.fn()}
        vehicleId={1}
        {...props}
      />
    </MemoryRouter>
  );

describe('ConsumableDialog', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    // Re-set post/put mocks after clearAllMocks
    mockApi.post.mockResolvedValue({ data: { id: 99 } });
    mockApi.put.mockResolvedValue({ data: { id: 1 } });
    // Default: vehicle returns type, consumable types return list, MOT/service return empty
    mockApi.get.mockImplementation((url) => {
      if (url.includes('/vehicles/')) {
        return Promise.resolve({ data: { id: 1, vehicleType: { id: 10 } } });
      }
      if (url.includes('/consumable-types')) {
        return Promise.resolve({
          data: [
            { id: 1, name: 'Engine Oil', unit: 'litres' },
            { id: 2, name: 'Air Filter', unit: 'pcs' },
          ],
        });
      }
      if (url.includes('/mot-records')) {
        return Promise.resolve({ data: [] });
      }
      if (url.includes('/service-records')) {
        return Promise.resolve({ data: [] });
      }
      return Promise.resolve({ data: [] });
    });
  });

  test('renders dialog with form fields', async () => {
    renderDialog();

    // Title (translation key)
    expect(screen.getByText('consumables.addConsumable')).toBeInTheDocument();

    // Wait for consumable types to load (API calls complete)
    await waitFor(() => {
      expect(mockApi.get).toHaveBeenCalledWith('/vehicle-types/10/consumable-types');
    });

    // Check key form field labels (all are translation keys)
    expect(screen.getByLabelText('consumables.name *')).toBeInTheDocument();
    expect(screen.getByLabelText('consumables.cost *')).toBeInTheDocument();
    expect(screen.getByLabelText('common.supplier')).toBeInTheDocument();
    expect(screen.getByLabelText('common.notes')).toBeInTheDocument();
  });

  test('loads consumable types on open', async () => {
    renderDialog();

    await waitFor(() => {
      expect(mockApi.get).toHaveBeenCalledWith('/vehicles/1');
    });

    await waitFor(() => {
      expect(mockApi.get).toHaveBeenCalledWith('/vehicle-types/10/consumable-types');
    });
  });

  test('populates form when editing existing consumable', async () => {
    const consumable = {
      id: 5,
      consumableType: { id: 1, name: 'Engine Oil' },
      description: 'Castrol Edge 5W-30',
      cost: 29.99,
      quantity: 4,
      supplier: 'Halfords',
      brand: 'Castrol',
      partNumber: 'CE530',
      notes: 'Synthetic',
      mileageAtChange: 50000,
      replacementIntervalMiles: 10000,
      nextReplacementMileage: 60000,
    };

    renderDialog({ consumable });

    expect(screen.getByText('consumables.editConsumable')).toBeInTheDocument();

    await waitFor(() => {
      expect(screen.getByLabelText('consumables.name *')).toHaveValue('Castrol Edge 5W-30');
    });

    expect(screen.getByLabelText('consumables.cost *')).toHaveValue(29.99);
    expect(screen.getByLabelText('common.supplier')).toHaveValue('Halfords');
    expect(screen.getByLabelText('common.notes')).toHaveValue('Synthetic');
  });

  test('submits new consumable via api.post', async () => {
    const onClose = jest.fn();
    renderDialog({ onClose });

    // Wait for types to load
    await waitFor(() => {
      expect(mockApi.get).toHaveBeenCalledWith('/vehicle-types/10/consumable-types');
    });

    // Fill required fields
    fireEvent.change(screen.getByLabelText('consumables.name *'), {
      target: { name: 'description', value: 'Castrol Edge' },
    });
    fireEvent.change(screen.getByLabelText('consumables.cost *'), {
      target: { name: 'cost', value: '29.99' },
    });
    fireEvent.change(screen.getByLabelText('consumables.quantity *'), {
      target: { name: 'quantity', value: '4' },
    });

    // Submit
    fireEvent.click(screen.getByRole('button', { name: 'common.save' }));

    await waitFor(() => {
      expect(mockApi.post).toHaveBeenCalledWith(
        '/consumables',
        expect.objectContaining({
          description: 'Castrol Edge',
          cost: 29.99,
          vehicleId: 1,
        })
      );
    });

    await waitFor(() => {
      expect(onClose).toHaveBeenCalledWith({ id: 99 });
    });
  });

  test('submits edited consumable via api.put', async () => {
    const onClose = jest.fn();
    const consumable = {
      id: 5,
      consumableType: { id: 1 },
      description: 'Old Oil',
      cost: 20,
      quantity: 4,
      supplier: '',
      brand: '',
      partNumber: '',
      notes: '',
      mileageAtChange: 0,
      replacementIntervalMiles: 0,
      nextReplacementMileage: 0,
    };

    renderDialog({ consumable, onClose });

    await waitFor(() => {
      expect(screen.getByLabelText('consumables.name *')).toHaveValue('Old Oil');
    });

    fireEvent.change(screen.getByLabelText('consumables.name *'), {
      target: { name: 'description', value: 'New Oil' },
    });

    fireEvent.click(screen.getByRole('button', { name: 'common.save' }));

    await waitFor(() => {
      expect(mockApi.put).toHaveBeenCalledWith(
        '/consumables/5',
        expect.objectContaining({ description: 'New Oil' })
      );
    });
  });

  test('cancel button calls onClose', async () => {
    const onClose = jest.fn();
    renderDialog({ onClose });

    await waitFor(() => {
      expect(mockApi.get).toHaveBeenCalledWith('/vehicle-types/10/consumable-types');
    });

    fireEvent.click(screen.getByRole('button', { name: 'common.cancel' }));
    expect(onClose).toHaveBeenCalledWith(false);
  });

  test('shows loader while types are loading', () => {
    // Make the API call hang
    mockApi.get.mockImplementation(() => new Promise(() => {}));

    renderDialog();

    expect(screen.getByTestId('loader')).toBeInTheDocument();
  });
});
