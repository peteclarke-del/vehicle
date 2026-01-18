import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';
import { BrowserRouter } from 'react-router-dom';
import Navigation from '../components/Navigation';
import { AuthContext } from '../context/AuthContext';

const renderWithProviders = (component, authValue = {}) => {
  const mockAuthContext = {
    token: 'mock-token',
    user: { id: 1, email: 'test@example.com', fullName: 'Test User' },
    logout: jest.fn(),
    ...authValue
  };

  return render(
    <BrowserRouter>
      <AuthContext.Provider value={mockAuthContext}>
        {component}
      </AuthContext.Provider>
    </BrowserRouter>
  );
};

describe('Navigation', () => {
  test('renders navigation bar', () => {
    renderWithProviders(<Navigation />);

    expect(screen.getByRole('navigation')).toBeInTheDocument();
  });

  test('displays app title', () => {
    renderWithProviders(<Navigation />);

    expect(screen.getByText(/vehicle tracker/i)).toBeInTheDocument();
  });

  test('shows navigation links when authenticated', () => {
    renderWithProviders(<Navigation />);

    expect(screen.getByText(/dashboard/i)).toBeInTheDocument();
    expect(screen.getByText(/vehicles/i)).toBeInTheDocument();
    expect(screen.getByText(/service records/i)).toBeInTheDocument();
  });

  test('hides navigation links when not authenticated', () => {
    renderWithProviders(<Navigation />, { token: null, user: null });

    expect(screen.queryByText(/dashboard/i)).not.toBeInTheDocument();
    expect(screen.queryByText(/vehicles/i)).not.toBeInTheDocument();
  });

  test('displays user menu when authenticated', () => {
    renderWithProviders(<Navigation />);

    expect(screen.getByText(/test user/i)).toBeInTheDocument();
  });

  test('shows login button when not authenticated', () => {
    renderWithProviders(<Navigation />, { token: null, user: null });

    expect(screen.getByText(/log in/i)).toBeInTheDocument();
  });

  test('opens user menu on click', () => {
    renderWithProviders(<Navigation />);

    const userButton = screen.getByText(/test user/i);
    fireEvent.click(userButton);

    expect(screen.getByText(/profile/i)).toBeInTheDocument();
    expect(screen.getByText(/settings/i)).toBeInTheDocument();
    expect(screen.getByText(/logout/i)).toBeInTheDocument();
  });

  test('calls logout on logout click', () => {
    const mockLogout = jest.fn();
    renderWithProviders(<Navigation />, { logout: mockLogout });

    const userButton = screen.getByText(/test user/i);
    fireEvent.click(userButton);

    const logoutButton = screen.getByText(/logout/i);
    fireEvent.click(logoutButton);

    expect(mockLogout).toHaveBeenCalled();
  });

  test('highlights active route', () => {
    renderWithProviders(<Navigation />);

    const dashboardLink = screen.getByText(/dashboard/i).closest('a');
    expect(dashboardLink).toHaveClass('active');
  });

  test('shows notification badge', () => {
    renderWithProviders(<Navigation />, { unreadNotifications: 3 });

    expect(screen.getByText('3')).toBeInTheDocument();
  });

  test('opens notifications panel', () => {
    renderWithProviders(<Navigation />, { unreadNotifications: 3 });

    const notificationButton = screen.getByRole('button', { name: /notifications/i });
    fireEvent.click(notificationButton);

    expect(screen.getByText(/your notifications/i)).toBeInTheDocument();
  });

  test('displays mobile menu button on small screens', () => {
    renderWithProviders(<Navigation />);

    const menuButton = screen.getByRole('button', { name: /menu/i });
    expect(menuButton).toBeInTheDocument();
  });

  test('toggles mobile menu', () => {
    renderWithProviders(<Navigation />);

    const menuButton = screen.getByRole('button', { name: /menu/i });
    fireEvent.click(menuButton);

    const mobileMenu = screen.getByRole('navigation');
    expect(mobileMenu).toHaveClass('open');
  });

  test('shows search bar', () => {
    renderWithProviders(<Navigation />);

    expect(screen.getByPlaceholderText(/search/i)).toBeInTheDocument();
  });

  test('handles search input', () => {
    const mockOnSearch = jest.fn();
    renderWithProviders(<Navigation onSearch={mockOnSearch} />);

    const searchInput = screen.getByPlaceholderText(/search/i);
    fireEvent.change(searchInput, { target: { value: 'AB12 CDE' } });

    expect(searchInput.value).toBe('AB12 CDE');
  });

  test('displays breadcrumbs', () => {
    renderWithProviders(<Navigation breadcrumbs={['Dashboard', 'Vehicles', 'AB12 CDE']} />);

    expect(screen.getByText('Dashboard')).toBeInTheDocument();
    expect(screen.getByText('Vehicles')).toBeInTheDocument();
    expect(screen.getByText('AB12 CDE')).toBeInTheDocument();
  });

  test('shows theme toggle', () => {
    renderWithProviders(<Navigation />);

    const themeToggle = screen.getByRole('button', { name: /theme/i });
    expect(themeToggle).toBeInTheDocument();
  });

  test('toggles dark mode', () => {
    const mockOnThemeChange = jest.fn();
    renderWithProviders(<Navigation onThemeChange={mockOnThemeChange} />);

    const themeToggle = screen.getByRole('button', { name: /theme/i });
    fireEvent.click(themeToggle);

    expect(mockOnThemeChange).toHaveBeenCalledWith('dark');
  });
});
