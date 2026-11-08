/**
 * The shared vehicle list must reflect the server: loaded on login, refreshed
 * when the app returns to the foreground, and kept when the server is down.
 */
import React from 'react';
import TestRenderer, {act} from 'react-test-renderer';

const mockStorage: Record<string, string> = {};
jest.mock('@react-native-async-storage/async-storage', () => ({
  getItem: jest.fn((k: string) => Promise.resolve(mockStorage[k] ?? null)),
  setItem: jest.fn((k: string, v: string) => {
    mockStorage[k] = v;
    return Promise.resolve();
  }),
}));

let mockAppStateListener: ((s: string) => void) | null = null;
jest.mock('react-native', () => ({
  AppState: {
    addEventListener: (_: string, cb: (s: string) => void) => {
      mockAppStateListener = cb;
      return {remove: () => {}};
    },
  },
}));

const mockGet = jest.fn();
// Stable client object, as AuthContext memoises it.
const mockApi = {get: (...args: any[]) => mockGet(...args)};
jest.mock('../src/contexts/AuthContext', () => ({
  useAuth: () => ({api: mockApi, isAuthenticated: true}),
}));
jest.mock('../src/contexts/SyncContext', () => ({
  useSync: () => ({isOnline: true, lastSyncResult: null}),
}));

import {VehiclesProvider, useVehicles, VEHICLES_CACHE_KEY} from '../src/contexts/VehiclesContext';

let ctx: ReturnType<typeof useVehicles>;
let renderer: TestRenderer.ReactTestRenderer | null = null;
afterEach(() => {
  act(() => renderer?.unmount());
  renderer = null;
});
const Probe = () => {
  ctx = useVehicles();
  return null;
};

const flush = () => act(async () => {
  await new Promise(r => setTimeout(r, 0));
});

beforeEach(() => {
  mockGet.mockReset();
  for (const k of Object.keys(mockStorage)) delete mockStorage[k];
});

test('loads the server list on login and caches it', async () => {
  mockGet.mockResolvedValue({data: [{id: 1, status: 'Live'}]});
  await act(async () => {
    renderer = TestRenderer.create(<VehiclesProvider><Probe /></VehiclesProvider>);
  });
  await flush();
  expect(ctx.vehicles).toEqual([{id: 1, status: 'Live'}]);
  expect(JSON.parse(mockStorage[VEHICLES_CACHE_KEY])).toEqual([{id: 1, status: 'Live'}]);
  expect(mockGet).toHaveBeenCalledTimes(1);
});

test('picks up a status changed elsewhere when the app returns to the foreground', async () => {
  mockGet.mockResolvedValue({data: [{id: 1, status: 'Live'}]});
  await act(async () => {
    renderer = TestRenderer.create(<VehiclesProvider><Probe /></VehiclesProvider>);
  });
  await flush();
  mockGet.mockResolvedValue({data: [{id: 1, status: 'Sold'}]});
  await act(async () => {
    mockAppStateListener?.('active');
  });
  await flush();
  expect(ctx.vehicles[0].status).toBe('Sold');
});

test('keeps the last known list when the server is unreachable', async () => {
  mockStorage[VEHICLES_CACHE_KEY] = JSON.stringify([{id: 2, status: 'Sold'}]);
  mockGet.mockRejectedValue(new Error('Network Error'));
  await act(async () => {
    renderer = TestRenderer.create(<VehiclesProvider><Probe /></VehiclesProvider>);
  });
  await flush();
  expect(ctx.vehicles).toEqual([{id: 2, status: 'Sold'}]);
});

test('applies offline edits locally', async () => {
  mockGet.mockResolvedValue({data: [{id: 1, status: 'Live'}, {id: 2, status: 'Live'}]});
  await act(async () => {
    renderer = TestRenderer.create(<VehiclesProvider><Probe /></VehiclesProvider>);
  });
  await flush();
  await act(async () => {
    ctx.updateVehicleLocally('2', {status: 'Sold'});
  });
  expect(ctx.vehicles.map(v => v.status)).toEqual(['Live', 'Sold']);
});
