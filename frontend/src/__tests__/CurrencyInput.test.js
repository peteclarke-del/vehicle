import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';
import CurrencyInput from '../components/CurrencyInput';

describe('CurrencyInput', () => {
  const mockOnChange = jest.fn();

  beforeEach(() => {
    jest.clearAllMocks();
  });

  test('renders currency input with label', () => {
    render(<CurrencyInput label="Amount" onChange={mockOnChange} />);

    expect(screen.getByLabelText(/amount/i)).toBeInTheDocument();
  });

  test('displays currency symbol', () => {
    render(<CurrencyInput label="Amount" currency="GBP" onChange={mockOnChange} />);

    expect(screen.getByText(/£/i)).toBeInTheDocument();
  });

  test('formats value with thousand separators', () => {
    render(<CurrencyInput label="Amount" value={1234.56} onChange={mockOnChange} />);

    const input = screen.getByLabelText(/amount/i);
    expect(input.value).toBe('1,234.56');
  });

  test('calls onChange with numeric value', () => {
    render(<CurrencyInput label="Amount" onChange={mockOnChange} />);

    const input = screen.getByLabelText(/amount/i);
    fireEvent.change(input, { target: { value: '45.99' } });

    expect(mockOnChange).toHaveBeenCalledWith(45.99);
  });

  test('handles decimal input', () => {
    render(<CurrencyInput label="Amount" onChange={mockOnChange} />);

    const input = screen.getByLabelText(/amount/i);
    fireEvent.change(input, { target: { value: '12.50' } });

    expect(mockOnChange).toHaveBeenCalledWith(12.50);
  });

  test('limits decimal places to 2', () => {
    render(<CurrencyInput label="Amount" onChange={mockOnChange} />);

    const input = screen.getByLabelText(/amount/i);
    fireEvent.change(input, { target: { value: '12.999' } });

    expect(mockOnChange).toHaveBeenCalledWith(12.99);
  });

  test('removes non-numeric characters', () => {
    render(<CurrencyInput label="Amount" onChange={mockOnChange} />);

    const input = screen.getByLabelText(/amount/i);
    fireEvent.change(input, { target: { value: '£12.50abc' } });

    expect(mockOnChange).toHaveBeenCalledWith(12.50);
  });

  test('handles negative values when allowed', () => {
    render(<CurrencyInput label="Amount" allowNegative onChange={mockOnChange} />);

    const input = screen.getByLabelText(/amount/i);
    fireEvent.change(input, { target: { value: '-45.99' } });

    expect(mockOnChange).toHaveBeenCalledWith(-45.99);
  });

  test('prevents negative values by default', () => {
    render(<CurrencyInput label="Amount" onChange={mockOnChange} />);

    const input = screen.getByLabelText(/amount/i);
    fireEvent.change(input, { target: { value: '-45.99' } });

    expect(mockOnChange).toHaveBeenCalledWith(45.99);
  });

  test('displays different currency symbols', () => {
    const currencies = [
      { code: 'GBP', symbol: '£' },
      { code: 'USD', symbol: '$' },
      { code: 'EUR', symbol: '€' }
    ];

    currencies.forEach(({ code, symbol }) => {
      const { unmount } = render(
        <CurrencyInput label="Amount" currency={code} onChange={mockOnChange} />
      );
      
      expect(screen.getByText(symbol)).toBeInTheDocument();
      unmount();
    });
  });

  test('validates minimum value', () => {
    render(<CurrencyInput label="Amount" min={10} onChange={mockOnChange} />);

    const input = screen.getByLabelText(/amount/i);
    fireEvent.change(input, { target: { value: '5' } });

    expect(screen.getByText(/must be at least/i)).toBeInTheDocument();
  });

  test('validates maximum value', () => {
    render(<CurrencyInput label="Amount" max={100} onChange={mockOnChange} />);

    const input = screen.getByLabelText(/amount/i);
    fireEvent.change(input, { target: { value: '150' } });

    expect(screen.getByText(/must not exceed/i)).toBeInTheDocument();
  });

  test('shows required indicator', () => {
    render(<CurrencyInput label="Amount" required onChange={mockOnChange} />);

    const input = screen.getByLabelText(/amount/i);
    expect(input).toHaveAttribute('required');
  });

  test('disables input when disabled prop is true', () => {
    render(<CurrencyInput label="Amount" disabled onChange={mockOnChange} />);

    const input = screen.getByLabelText(/amount/i);
    expect(input).toBeDisabled();
  });

  test('handles paste events', () => {
    render(<CurrencyInput label="Amount" onChange={mockOnChange} />);

    const input = screen.getByLabelText(/amount/i);
    fireEvent.paste(input, {
      clipboardData: {
        getData: () => '£1,234.56'
      }
    });

    expect(mockOnChange).toHaveBeenCalledWith(1234.56);
  });

  test('handles empty input', () => {
    render(<CurrencyInput label="Amount" onChange={mockOnChange} />);

    const input = screen.getByLabelText(/amount/i);
    fireEvent.change(input, { target: { value: '' } });

    expect(mockOnChange).toHaveBeenCalledWith(null);
  });

  test('displays helper text', () => {
    render(<CurrencyInput label="Amount" helperText="Enter amount" onChange={mockOnChange} />);

    expect(screen.getByText(/enter amount/i)).toBeInTheDocument();
  });

  test('displays error message', () => {
    render(<CurrencyInput label="Amount" error="Amount is required" onChange={mockOnChange} />);

    expect(screen.getByText(/amount is required/i)).toBeInTheDocument();
  });

  test('formats on blur', () => {
    render(<CurrencyInput label="Amount" onChange={mockOnChange} />);

    const input = screen.getByLabelText(/amount/i);
    fireEvent.change(input, { target: { value: '1234.5' } });
    fireEvent.blur(input);

    expect(input.value).toBe('1,234.50');
  });

  test('removes formatting on focus', () => {
    render(<CurrencyInput label="Amount" value={1234.56} onChange={mockOnChange} />);

    const input = screen.getByLabelText(/amount/i);
    fireEvent.focus(input);

    expect(input.value).toBe('1234.56');
  });

  test('handles zero value', () => {
    render(<CurrencyInput label="Amount" value={0} onChange={mockOnChange} />);

    const input = screen.getByLabelText(/amount/i);
    expect(input.value).toBe('0.00');
  });

  test('allows custom decimal places', () => {
    render(<CurrencyInput label="Amount" decimalPlaces={3} onChange={mockOnChange} />);

    const input = screen.getByLabelText(/amount/i);
    fireEvent.change(input, { target: { value: '12.555' } });

    expect(mockOnChange).toHaveBeenCalledWith(12.555);
  });

  test('handles very large numbers', () => {
    render(<CurrencyInput label="Amount" onChange={mockOnChange} />);

    const input = screen.getByLabelText(/amount/i);
    fireEvent.change(input, { target: { value: '1000000.00' } });

    expect(input.value).toContain('1,000,000');
  });
});
