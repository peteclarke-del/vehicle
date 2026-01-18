import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';
import DatePicker from '../components/DatePicker';

describe('DatePicker', () => {
  const mockOnChange = jest.fn();

  beforeEach(() => {
    jest.clearAllMocks();
  });

  test('renders date picker with label', () => {
    render(<DatePicker label="Select Date" onChange={mockOnChange} />);

    expect(screen.getByLabelText(/select date/i)).toBeInTheDocument();
  });

  test('displays selected date', () => {
    render(<DatePicker label="Date" value="2024-03-15" onChange={mockOnChange} />);

    const input = screen.getByLabelText(/date/i);
    expect(input.value).toBe('2024-03-15');
  });

  test('calls onChange when date is selected', () => {
    render(<DatePicker label="Date" onChange={mockOnChange} />);

    const input = screen.getByLabelText(/date/i);
    fireEvent.change(input, { target: { value: '2024-03-15' } });

    expect(mockOnChange).toHaveBeenCalledWith('2024-03-15');
  });

  test('validates date format', () => {
    render(<DatePicker label="Date" onChange={mockOnChange} validateFormat={true} />);

    const input = screen.getByLabelText(/date/i);
    fireEvent.change(input, { target: { value: 'invalid' } });

    expect(screen.getByText(/invalid date format/i)).toBeInTheDocument();
  });

  test('enforces minimum date', () => {
    render(<DatePicker label="Date" minDate="2024-01-01" onChange={mockOnChange} />);

    const input = screen.getByLabelText(/date/i);
    fireEvent.change(input, { target: { value: '2023-12-31' } });

    expect(screen.getByText(/date must be after/i)).toBeInTheDocument();
  });

  test('enforces maximum date', () => {
    render(<DatePicker label="Date" maxDate="2024-12-31" onChange={mockOnChange} />);

    const input = screen.getByLabelText(/date/i);
    fireEvent.change(input, { target: { value: '2025-01-01' } });

    expect(screen.getByText(/date must be before/i)).toBeInTheDocument();
  });

  test('displays as required field', () => {
    render(<DatePicker label="Date" required onChange={mockOnChange} />);

    const input = screen.getByLabelText(/date/i);
    expect(input).toHaveAttribute('required');
  });

  test('shows helper text', () => {
    render(<DatePicker label="Date" helperText="Select a date" onChange={mockOnChange} />);

    expect(screen.getByText(/select a date/i)).toBeInTheDocument();
  });

  test('disables input when disabled prop is true', () => {
    render(<DatePicker label="Date" disabled onChange={mockOnChange} />);

    const input = screen.getByLabelText(/date/i);
    expect(input).toBeDisabled();
  });

  test('formats date for display', () => {
    render(<DatePicker label="Date" value="2024-03-15" displayFormat="DD/MM/YYYY" onChange={mockOnChange} />);

    expect(screen.getByText(/15\/03\/2024/i)).toBeInTheDocument();
  });

  test('opens calendar on click', () => {
    render(<DatePicker label="Date" onChange={mockOnChange} showCalendar />);

    const input = screen.getByLabelText(/date/i);
    fireEvent.click(input);

    expect(screen.getByRole('dialog')).toBeInTheDocument();
  });

  test('allows clearing date', () => {
    render(<DatePicker label="Date" value="2024-03-15" clearable onChange={mockOnChange} />);

    const clearButton = screen.getByRole('button', { name: /clear/i });
    fireEvent.click(clearButton);

    expect(mockOnChange).toHaveBeenCalledWith(null);
  });

  test('handles today button click', () => {
    render(<DatePicker label="Date" showTodayButton onChange={mockOnChange} />);

    const todayButton = screen.getByRole('button', { name: /today/i });
    fireEvent.click(todayButton);

    const today = new Date().toISOString().split('T')[0];
    expect(mockOnChange).toHaveBeenCalledWith(today);
  });

  test('validates date is not in future when disableFuture is true', () => {
    render(<DatePicker label="Date" disableFuture onChange={mockOnChange} />);

    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    const tomorrowStr = tomorrow.toISOString().split('T')[0];

    const input = screen.getByLabelText(/date/i);
    fireEvent.change(input, { target: { value: tomorrowStr } });

    expect(screen.getByText(/future dates not allowed/i)).toBeInTheDocument();
  });

  test('validates date is not in past when disablePast is true', () => {
    render(<DatePicker label="Date" disablePast onChange={mockOnChange} />);

    const yesterday = new Date();
    yesterday.setDate(yesterday.getDate() - 1);
    const yesterdayStr = yesterday.toISOString().split('T')[0];

    const input = screen.getByLabelText(/date/i);
    fireEvent.change(input, { target: { value: yesterdayStr } });

    expect(screen.getByText(/past dates not allowed/i)).toBeInTheDocument();
  });

  test('displays error message', () => {
    render(<DatePicker label="Date" error="This field is required" onChange={mockOnChange} />);

    expect(screen.getByText(/this field is required/i)).toBeInTheDocument();
  });

  test('shows different date formats', () => {
    const formats = [
      { format: 'YYYY-MM-DD', value: '2024-03-15', expected: '2024-03-15' },
      { format: 'DD/MM/YYYY', value: '2024-03-15', expected: '15/03/2024' },
      { format: 'MM/DD/YYYY', value: '2024-03-15', expected: '03/15/2024' }
    ];

    formats.forEach(({ format, value, expected }) => {
      const { unmount } = render(
        <DatePicker label="Date" value={value} displayFormat={format} onChange={mockOnChange} />
      );
      
      expect(screen.getByText(new RegExp(expected.replace(/\//g, '\\/'), 'i'))).toBeInTheDocument();
      unmount();
    });
  });

  test('handles keyboard navigation', () => {
    render(<DatePicker label="Date" onChange={mockOnChange} />);

    const input = screen.getByLabelText(/date/i);
    
    // Arrow up increases date
    fireEvent.keyDown(input, { key: 'ArrowUp' });
    
    // Arrow down decreases date
    fireEvent.keyDown(input, { key: 'ArrowDown' });

    // Escape clears focus
    fireEvent.keyDown(input, { key: 'Escape' });
  });

  test('validates leap year dates', () => {
    render(<DatePicker label="Date" validateFormat onChange={mockOnChange} />);

    const input = screen.getByLabelText(/date/i);
    
    // Invalid leap year date
    fireEvent.change(input, { target: { value: '2023-02-29' } });
    expect(screen.getByText(/invalid date/i)).toBeInTheDocument();

    // Valid leap year date
    fireEvent.change(input, { target: { value: '2024-02-29' } });
    expect(screen.queryByText(/invalid date/i)).not.toBeInTheDocument();
  });
});
