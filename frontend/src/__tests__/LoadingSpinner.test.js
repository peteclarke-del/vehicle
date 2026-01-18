import React from 'react';
import { render, screen } from '@testing-library/react';
import LoadingSpinner from '../components/LoadingSpinner';

describe('LoadingSpinner', () => {
  test('renders loading spinner', () => {
    render(<LoadingSpinner />);

    expect(screen.getByRole('progressbar')).toBeInTheDocument();
  });

  test('displays default loading text', () => {
    render(<LoadingSpinner />);

    expect(screen.getByText(/loading/i)).toBeInTheDocument();
  });

  test('displays custom loading text', () => {
    render(<LoadingSpinner text="Please wait..." />);

    expect(screen.getByText(/please wait/i)).toBeInTheDocument();
  });

  test('supports different sizes', () => {
    const { rerender } = render(<LoadingSpinner size="small" />);
    let spinner = screen.getByRole('progressbar');
    expect(spinner).toHaveClass('small');

    rerender(<LoadingSpinner size="medium" />);
    spinner = screen.getByRole('progressbar');
    expect(spinner).toHaveClass('medium');

    rerender(<LoadingSpinner size="large" />);
    spinner = screen.getByRole('progressbar');
    expect(spinner).toHaveClass('large');
  });

  test('supports different variants', () => {
    const { rerender } = render(<LoadingSpinner variant="circular" />);
    let spinner = screen.getByRole('progressbar');
    expect(spinner).toHaveClass('circular');

    rerender(<LoadingSpinner variant="linear" />);
    spinner = screen.getByRole('progressbar');
    expect(spinner).toHaveClass('linear');
  });

  test('centers spinner by default', () => {
    render(<LoadingSpinner />);

    const container = screen.getByRole('progressbar').parentElement;
    expect(container).toHaveClass('centered');
  });

  test('supports inline mode', () => {
    render(<LoadingSpinner inline />);

    const container = screen.getByRole('progressbar').parentElement;
    expect(container).toHaveClass('inline');
  });

  test('renders fullscreen overlay', () => {
    render(<LoadingSpinner fullscreen />);

    const overlay = screen.getByRole('progressbar').closest('.overlay');
    expect(overlay).toHaveClass('fullscreen');
  });

  test('applies custom className', () => {
    render(<LoadingSpinner className="custom-spinner" />);

    const spinner = screen.getByRole('progressbar');
    expect(spinner).toHaveClass('custom-spinner');
  });

  test('hides text when hideText is true', () => {
    render(<LoadingSpinner hideText />);

    expect(screen.queryByText(/loading/i)).not.toBeInTheDocument();
  });

  test('supports different colors', () => {
    const { rerender } = render(<LoadingSpinner color="primary" />);
    let spinner = screen.getByRole('progressbar');
    expect(spinner).toHaveClass('primary');

    rerender(<LoadingSpinner color="secondary" />);
    spinner = screen.getByRole('progressbar');
    expect(spinner).toHaveClass('secondary');
  });

  test('shows progress percentage', () => {
    render(<LoadingSpinner progress={50} />);

    expect(screen.getByText('50%')).toBeInTheDocument();
  });

  test('renders with determinate progress', () => {
    render(<LoadingSpinner determinate progress={75} />);

    const progressBar = screen.getByRole('progressbar');
    expect(progressBar).toHaveAttribute('aria-valuenow', '75');
  });

  test('has accessibility attributes', () => {
    render(<LoadingSpinner />);

    const spinner = screen.getByRole('progressbar');
    expect(spinner).toHaveAttribute('aria-busy', 'true');
  });

  test('supports custom aria-label', () => {
    render(<LoadingSpinner ariaLabel="Loading data" />);

    const spinner = screen.getByRole('progressbar');
    expect(spinner).toHaveAttribute('aria-label', 'Loading data');
  });

  test('renders with delay', async () => {
    const { container } = render(<LoadingSpinner delay={100} />);

    expect(container.firstChild).toBeNull();

    await new Promise(resolve => setTimeout(resolve, 150));
    
    // After delay, spinner should appear
    expect(screen.getByRole('progressbar')).toBeInTheDocument();
  });
});
