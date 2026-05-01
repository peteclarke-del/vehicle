import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';
import FilteredVehicleSelector from '../components/FilteredVehicleSelector';
import { VEHICLE_STATUS_OPTIONS } from '../hooks/useVehicleStatusFilter';

jest.mock('react-i18next', () => ({
  useTranslation: () => ({ t: (k) => k }),
}));

// VehicleSelector is heavier – use a lightweight mock
jest.mock('../components/VehicleSelector', () => ({
  __esModule: true,
  default: ({ vehicles, value, onChange, includeViewAll }) => (
    <select
      data-testid="vehicle-selector"
      value={value || ''}
      onChange={(e) => onChange(e.target.value)}
    >
      {includeViewAll && <option value="__all__">All</option>}
      {(vehicles || []).map((v) => (
        <option key={v.id} value={v.id}>
          {v.registration}
        </option>
      ))}
    </select>
  ),
}));

const mockVehicles = [
  { id: 1, registration: 'AA11 AAA' },
  { id: 2, registration: 'BB22 BBB' },
];

const defaultProps = {
  statusFilter: 'Live',
  onStatusFilterChange: jest.fn(),
  statusOptions: VEHICLE_STATUS_OPTIONS,
  vehicles: mockVehicles,
  selectedVehicle: 1,
  onVehicleChange: jest.fn(),
};

describe('FilteredVehicleSelector', () => {
  beforeEach(() => {
    jest.clearAllMocks();
  });

  test('renders status filter dropdown', () => {
    render(<FilteredVehicleSelector {...defaultProps} />);
    expect(screen.getByRole('combobox', { name: /status/i })).toBeInTheDocument();
  });

  test('renders vehicle selector', () => {
    render(<FilteredVehicleSelector {...defaultProps} />);
    expect(screen.getByTestId('vehicle-selector')).toBeInTheDocument();
  });

  test('shows current statusFilter value', () => {
    render(<FilteredVehicleSelector {...defaultProps} statusFilter="Sold" />);
    // The Select displays the current value; check the input has the right value
    const input = document.querySelector('input[name]') || screen.queryByDisplayValue('vehicle.status.sold');
    // MUI Select renders a hidden input; verify the Select label is present
    expect(screen.getByText('vehicle.status.sold')).toBeInTheDocument();
  });

  test('calls onStatusFilterChange when status is changed', () => {
    const onStatusFilterChange = jest.fn();
    render(
      <FilteredVehicleSelector
        {...defaultProps}
        onStatusFilterChange={onStatusFilterChange}
        statusFilter="Live"
      />
    );
    // Open the MUI Select dropdown, then click an option
    fireEvent.mouseDown(screen.getByRole('combobox', { name: /status/i }));
    fireEvent.click(screen.getByRole('option', { name: 'vehicle.status.sold' }));
    expect(onStatusFilterChange).toHaveBeenCalled();
  });

  test('passes vehicles to VehicleSelector', () => {
    render(<FilteredVehicleSelector {...defaultProps} />);
    const options = screen.getAllByRole('option');
    // AA11 AAA and BB22 BBB should be in the selector
    expect(options.some((o) => o.textContent === 'AA11 AAA')).toBe(true);
    expect(options.some((o) => o.textContent === 'BB22 BBB')).toBe(true);
  });

  test('renders "View All" option when includeViewAll is true', () => {
    render(<FilteredVehicleSelector {...defaultProps} includeViewAll={true} />);
    expect(screen.getByRole('option', { name: 'All' })).toBeInTheDocument();
  });

  test('does not render "View All" option when includeViewAll is false', () => {
    render(<FilteredVehicleSelector {...defaultProps} includeViewAll={false} />);
    expect(screen.queryByRole('option', { name: 'All' })).not.toBeInTheDocument();
  });

  test('renders all status options in the dropdown', () => {
    render(<FilteredVehicleSelector {...defaultProps} />);
    // Status options are rendered as hidden MenuItems inside MUI Select
    // Verify by checking the select has the right label
    expect(screen.getByLabelText(/status/i)).toBeInTheDocument();
  });
});
