import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import Layout from '../components/Layout';

// Mock MUI's useMediaQuery at the source level (isMobile = false)
jest.mock('@mui/system/useMediaQuery', () => ({
  __esModule: true,
  default: jest.fn(() => false),
}));

// Mock @dnd-kit to avoid infinite update loops in test
jest.mock('@dnd-kit/core', () => ({
  DndContext: ({ children }) => <>{children}</>,
  closestCenter: jest.fn(),
  PointerSensor: jest.fn(),
  useSensor: jest.fn(),
  useSensors: jest.fn(() => []),
}));
jest.mock('@dnd-kit/sortable', () => ({
  arrayMove: jest.fn((arr) => arr),
  SortableContext: ({ children }) => <>{children}</>,
  verticalListSortingStrategy: {},
  useSortable: () => ({
    attributes: {},
    listeners: {},
    setNodeRef: jest.fn(),
    transform: null,
    transition: null,
    isDragging: false,
  }),
}));
jest.mock('@dnd-kit/utilities', () => ({
  CSS: { Transform: { toString: () => null } },
}));

// Mock logger to silence any log output
jest.mock('../utils/logger', () => ({
  __esModule: true,
  default: { warn: jest.fn(), error: jest.fn(), info: jest.fn(), debug: jest.fn() },
}));

// Mock i18next — t must be a stable reference (used in useEffect deps)
const mockT = (key) => key;
jest.mock('react-i18next', () => ({
  useTranslation: () => ({ t: mockT }),
}));

// Mock AuthContext — api must be a stable reference to avoid infinite re-render
// (Layout.js has [user, api, t] in a useEffect dependency array)
const mockApi = { get: jest.fn().mockResolvedValue({ data: {} }), post: jest.fn().mockResolvedValue({ data: {} }) };
const mockUser = { id: 1, email: 'test@example.com', roles: ['ROLE_USER'] };
const mockLogout = jest.fn();
jest.mock('../contexts/AuthContext', () => ({
  useAuth: () => ({
    user: mockUser,
    token: 'mock-token',
    logout: mockLogout,
    api: mockApi,
    updateProfile: jest.fn(),
  }),
}));

// Mock ThemeContext
const mockToggleTheme = jest.fn();
jest.mock('../contexts/ThemeContext', () => ({
  useTheme: () => ({
    mode: 'light',
    toggleTheme: mockToggleTheme,
  }),
}));

// Mock PermissionsContext
jest.mock('../contexts/PermissionsContext', () => ({
  usePermissions: () => ({
    isAdmin: false,
    features: {},
    can: jest.fn(() => true),
    canAccessVehicle: jest.fn(() => true),
    canEditVehicle: jest.fn(() => true),
    canDeleteVehicle: jest.fn(() => true),
    canAddRecordsToVehicle: jest.fn(() => true),
  }),
}));

// Mock useNotifications
jest.mock('../hooks/useNotifications', () => ({
  useNotifications: () => ({
    notifications: [],
    dismissNotification: jest.fn(),
    snoozeNotification: jest.fn(),
    clearAllNotifications: jest.fn(),
  }),
}));

// Mock child components
jest.mock('../components/NotificationMenu', () => () => <div data-testid="notification-menu" />);
jest.mock('../components/PreferencesDialog', () => () => null);

const renderLayout = () =>
  render(
    <MemoryRouter>
      <Layout />
    </MemoryRouter>
  );

describe('Layout', () => {
  test('renders app bar, main content, theme toggle, settings, and notification menu', () => {
    renderLayout();
    expect(screen.getByRole('banner')).toBeInTheDocument();
    expect(screen.getByRole('main')).toBeInTheDocument();
    expect(screen.getByText('app.title')).toBeInTheDocument();
    expect(screen.getByTestId('notification-menu')).toBeInTheDocument();
    // mode is 'light' → renders DarkMode icon
    expect(screen.getByTestId('DarkModeIcon')).toBeInTheDocument();
    expect(screen.getByTestId('SettingsIcon')).toBeInTheDocument();
  });

  test('hides admin link for non-admin users', () => {
    renderLayout();
    expect(screen.queryByText('nav.admin')).not.toBeInTheDocument();
  });
});
