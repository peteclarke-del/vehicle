// jest-dom adds custom jest matchers for asserting on DOM nodes.
// allows you to do things like:
//   expect(element).toHaveTextContent(/react/i)
// learn more: https://github.com/testing-library/jest-dom
import '@testing-library/jest-dom';

// Polyfill ResizeObserver for jsdom
global.ResizeObserver = class ResizeObserver {
  constructor(cb) { this.cb = cb; }
  observe() {}
  unobserve() {}
  disconnect() {}
};

// Polyfill matchMedia for jsdom (needed by MUI useMediaQuery)
global.matchMedia = jest.fn().mockImplementation((query) => ({
  matches: false,
  media: query,
  onchange: null,
  addListener: jest.fn(),
  removeListener: jest.fn(),
  addEventListener: jest.fn(),
  removeEventListener: jest.fn(),
  dispatchEvent: jest.fn(),
}));

// Global mock for AuthContext / useAuth
jest.mock('./contexts/AuthContext', () => {
  const mockApi = {
    get: jest.fn().mockResolvedValue({ data: [] }),
    post: jest.fn().mockResolvedValue({ data: {} }),
    put: jest.fn().mockResolvedValue({ data: {} }),
    delete: jest.fn().mockResolvedValue({ data: {} }),
  };
  const defaultContextValue = {
    user: { id: 1, email: 'test@example.com' },
    api: mockApi,
    token: 'mock-token',
    isAuthenticated: true,
    login: jest.fn(),
    logout: jest.fn(),
  };
  const React = require('react');
  const AuthContext = React.createContext(defaultContextValue);
  return {
    AuthContext,
    AuthProvider: ({ children, value }) => {
      return React.createElement(AuthContext.Provider, { value: value || defaultContextValue }, children);
    },
    useAuth: () => {
      // Read from the context so AuthContext.Provider value overrides work
      // eslint-disable-next-line react-hooks/rules-of-hooks
      return React.useContext(AuthContext);
    },
  };
});
jest.mock('./i18n', () => ({
  __esModule: true,
  default: {
    use: function() { return this; },
    init: () => Promise.resolve(),
    t: (key) => key,
    language: 'en',
    changeLanguage: () => Promise.resolve(),
  },
  getAvailableLanguages: async () => [{ code: 'en', name: 'English', nativeName: 'English' }],
}));

// Global mock for VehiclesContext
jest.mock('./contexts/VehiclesContext', () => ({
  VehiclesProvider: ({ children }) => children,
  useVehicles: () => ({
    vehicles: [],
    loading: false,
    error: null,
    refreshVehicles: jest.fn(),
    fetchVehicles: jest.fn(),
    addVehicle: jest.fn(),
    updateVehicle: jest.fn(),
    deleteVehicle: jest.fn(),
    recordsVersion: 0,
    notifyRecordChange: jest.fn(),
  }),
}));

// Global mock for UserPreferencesContext
jest.mock('./contexts/UserPreferencesContext', () => ({
  UserPreferencesProvider: ({ children }) => children,
  useUserPreferences: () => ({
    preferences: {
      currency: 'GBP',
      distanceUnit: 'mi',
      theme: 'light',
      language: 'en',
    },
    updatePreferences: jest.fn(),
    loading: false,
    defaultVehicleId: null,
    setDefaultVehicle: jest.fn(),
  }),
}));

// Global mock for useDistance hook
jest.mock('./hooks/useDistance', () => ({
  useDistance: () => ({
    userUnit: 'mi',
    convert: (val) => val,
    toKm: (val) => val,
    format: (val) => `${val} mi`,
    getLabel: () => 'mi',
    convertFuelConsumption: (val) => val,
    getFuelConsumptionLabel: () => 'MPG',
  }),
}));

// Global mock for useApiData hook
jest.mock('./hooks/useApiData', () => ({
  useApiData: jest.fn(() => ({ data: [], loading: false, error: null, fetchData: jest.fn(), setData: jest.fn() })),
  fetchArrayData: jest.fn(() => Promise.resolve([])),
}));
