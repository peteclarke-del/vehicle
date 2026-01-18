import React from 'react';
import { render, screen } from '@testing-library/react';
import { BrowserRouter } from 'react-router-dom';
import Layout from '../components/Layout';
import { AuthContext } from '../context/AuthContext';

const renderWithProviders = (component, authValue = {}) => {
  const mockAuthContext = {
    token: 'mock-token',
    user: { id: 1, email: 'test@example.com' },
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

describe('Layout', () => {
  test('renders layout with children', () => {
    renderWithProviders(
      <Layout>
        <div>Test Content</div>
      </Layout>
    );

    expect(screen.getByText('Test Content')).toBeInTheDocument();
  });

  test('includes navigation component', () => {
    renderWithProviders(
      <Layout>
        <div>Content</div>
      </Layout>
    );

    expect(screen.getByRole('navigation')).toBeInTheDocument();
  });

  test('includes footer component', () => {
    renderWithProviders(
      <Layout>
        <div>Content</div>
      </Layout>
    );

    expect(screen.getByRole('contentinfo')).toBeInTheDocument();
  });

  test('applies container class to main content', () => {
    renderWithProviders(
      <Layout>
        <div>Content</div>
      </Layout>
    );

    const main = screen.getByRole('main');
    expect(main).toHaveClass('container');
  });

  test('renders sidebar when provided', () => {
    renderWithProviders(
      <Layout sidebar={<div>Sidebar Content</div>}>
        <div>Main Content</div>
      </Layout>
    );

    expect(screen.getByText('Sidebar Content')).toBeInTheDocument();
    expect(screen.getByText('Main Content')).toBeInTheDocument();
  });

  test('supports full width mode', () => {
    renderWithProviders(
      <Layout fullWidth>
        <div>Content</div>
      </Layout>
    );

    const main = screen.getByRole('main');
    expect(main).toHaveClass('full-width');
  });

  test('displays loading state', () => {
    renderWithProviders(
      <Layout loading>
        <div>Content</div>
      </Layout>
    );

    expect(screen.getByRole('progressbar')).toBeInTheDocument();
  });

  test('shows error message', () => {
    renderWithProviders(
      <Layout error="Failed to load data">
        <div>Content</div>
      </Layout>
    );

    expect(screen.getByText(/failed to load data/i)).toBeInTheDocument();
  });

  test('renders breadcrumbs', () => {
    renderWithProviders(
      <Layout breadcrumbs={['Home', 'Vehicles', 'Details']}>
        <div>Content</div>
      </Layout>
    );

    expect(screen.getByText('Home')).toBeInTheDocument();
    expect(screen.getByText('Vehicles')).toBeInTheDocument();
    expect(screen.getByText('Details')).toBeInTheDocument();
  });

  test('applies custom className', () => {
    renderWithProviders(
      <Layout className="custom-layout">
        <div>Content</div>
      </Layout>
    );

    const layout = screen.getByRole('main').parentElement;
    expect(layout).toHaveClass('custom-layout');
  });

  test('renders page title', () => {
    renderWithProviders(
      <Layout title="Vehicle Details">
        <div>Content</div>
      </Layout>
    );

    expect(screen.getByText('Vehicle Details')).toBeInTheDocument();
  });

  test('shows back button when onBack is provided', () => {
    const mockOnBack = jest.fn();
    
    renderWithProviders(
      <Layout onBack={mockOnBack}>
        <div>Content</div>
      </Layout>
    );

    const backButton = screen.getByRole('button', { name: /back/i });
    expect(backButton).toBeInTheDocument();
  });

  test('renders action buttons', () => {
    renderWithProviders(
      <Layout actions={[
        { label: 'Edit', onClick: jest.fn() },
        { label: 'Delete', onClick: jest.fn() }
      ]}>
        <div>Content</div>
      </Layout>
    );

    expect(screen.getByRole('button', { name: /edit/i })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /delete/i })).toBeInTheDocument();
  });

  test('supports centered content', () => {
    renderWithProviders(
      <Layout centered>
        <div>Content</div>
      </Layout>
    );

    const main = screen.getByRole('main');
    expect(main).toHaveClass('centered');
  });

  test('hides navigation when hideNav is true', () => {
    renderWithProviders(
      <Layout hideNav>
        <div>Content</div>
      </Layout>
    );

    expect(screen.queryByRole('navigation')).not.toBeInTheDocument();
  });

  test('hides footer when hideFooter is true', () => {
    renderWithProviders(
      <Layout hideFooter>
        <div>Content</div>
      </Layout>
    );

    expect(screen.queryByRole('contentinfo')).not.toBeInTheDocument();
  });
});
