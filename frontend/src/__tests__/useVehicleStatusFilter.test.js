import { renderHook, act } from '@testing-library/react';
import useVehicleStatusFilter, { VEHICLE_STATUS_OPTIONS } from '../hooks/useVehicleStatusFilter';
import SafeStorage from '../utils/SafeStorage';

jest.mock('../utils/SafeStorage', () => ({
  get: jest.fn(),
  set: jest.fn(),
}));

const mockVehicles = [
  { id: 1, registration: 'AA11 AAA', status: 'Live' },
  { id: 2, registration: 'BB22 BBB', status: 'Sold' },
  { id: 3, registration: 'CC33 CCC', status: 'Scrapped' },
  { id: 4, registration: 'DD44 DDD', status: 'Exported' },
  { id: 5, registration: 'EE55 EEE' }, // no status — defaults to 'Live'
];

beforeEach(() => {
  jest.clearAllMocks();
  SafeStorage.get.mockReturnValue('Live');
});

describe('useVehicleStatusFilter', () => {
  test('initialises with value from SafeStorage', () => {
    SafeStorage.get.mockReturnValue('Sold');
    const { result } = renderHook(() =>
      useVehicleStatusFilter(mockVehicles, 'testKey')
    );
    expect(result.current.statusFilter).toBe('Sold');
  });

  test('defaults to Live when nothing stored', () => {
    // When SafeStorage returns the defaultValue passed to it (nothing in storage),
    // the hook should start with 'Live'.
    SafeStorage.get.mockImplementation((_key, defaultValue) => defaultValue ?? null);
    const { result } = renderHook(() =>
      useVehicleStatusFilter(mockVehicles, 'testKey')
    );
    expect(result.current.statusFilter).toBe('Live');
  });

  test('filters vehicles by Live status (including no-status vehicles)', () => {
    const { result } = renderHook(() =>
      useVehicleStatusFilter(mockVehicles, 'testKey')
    );
    // statusFilter = 'Live'; vehicles 1 and 5 match
    expect(result.current.filteredVehicles).toHaveLength(2);
    expect(result.current.filteredVehicles.map((v) => v.id)).toEqual([1, 5]);
  });

  test('filters vehicles by Sold status', () => {
    SafeStorage.get.mockReturnValue('Sold');
    const { result } = renderHook(() =>
      useVehicleStatusFilter(mockVehicles, 'testKey')
    );
    expect(result.current.filteredVehicles).toHaveLength(1);
    expect(result.current.filteredVehicles[0].id).toBe(2);
  });

  test('returns all vehicles when filter is "all"', () => {
    SafeStorage.get.mockReturnValue('all');
    const { result } = renderHook(() =>
      useVehicleStatusFilter(mockVehicles, 'testKey')
    );
    expect(result.current.filteredVehicles).toHaveLength(mockVehicles.length);
  });

  test('handleStatusFilterChange updates filter and persists to storage', () => {
    const { result } = renderHook(() =>
      useVehicleStatusFilter(mockVehicles, 'myKey')
    );
    act(() => {
      result.current.handleStatusFilterChange({ target: { value: 'Scrapped' } });
    });
    expect(result.current.statusFilter).toBe('Scrapped');
    expect(SafeStorage.set).toHaveBeenCalledWith('myKey', 'Scrapped');
  });

  test('filteredVehicles updates reactively when vehicles prop changes', () => {
    const { result, rerender } = renderHook(
      ({ vehicles }) => useVehicleStatusFilter(vehicles, 'testKey'),
      { initialProps: { vehicles: [] } }
    );
    expect(result.current.filteredVehicles).toHaveLength(0);

    rerender({ vehicles: mockVehicles });
    expect(result.current.filteredVehicles).toHaveLength(2); // Live + no-status
  });

  test('exports VEHICLE_STATUS_OPTIONS with expected keys', () => {
    const keys = VEHICLE_STATUS_OPTIONS.map((o) => o.key);
    expect(keys).toEqual(['all', 'Live', 'Sold', 'Scrapped', 'Exported']);
  });

  test('STATUS_OPTIONS returned from hook matches VEHICLE_STATUS_OPTIONS', () => {
    const { result } = renderHook(() =>
      useVehicleStatusFilter(mockVehicles, 'testKey')
    );
    expect(result.current.STATUS_OPTIONS).toBe(VEHICLE_STATUS_OPTIONS);
  });
});
