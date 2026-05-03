import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';
import VehicleSelector from '../components/VehicleSelector';
import '@testing-library/jest-dom';

// Mock i18next
jest.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: (key) => key,
  }),
}));

describe('VehicleSelector Component', () => {
  const mockVehicles = [
    {
      id: 1,
      registration: 'ABC123',
      make: { name: 'Toyota' },
      model: { name: 'Corolla' },
      year: 2020,
    },
    {
      id: 2,
      registration: 'DEF456',
      make: { name: 'Honda' },
      model: { name: 'Civic' },
      year: 2019,
    },
  ];

  test('renders vehicle selector', () => {
    render(<VehicleSelector vehicles={mockVehicles} />);
    expect(screen.getByRole('combobox')).toBeInTheDocument();
  });

  test('displays all vehicles in dropdown', () => {
    render(<VehicleSelector vehicles={mockVehicles} />);
    
    const select = screen.getByRole('combobox');
    fireEvent.click(select);

    expect(screen.getByText(/ABC123 - 2020 Toyota Corolla/i)).toBeInTheDocument();
    expect(screen.getByText(/DEF456 - 2019 Honda Civic/i)).toBeInTheDocument();
  });

  test('calls onChange when vehicle selected', () => {
    const mockOnChange = jest.fn();
    render(<VehicleSelector vehicles={mockVehicles} onChange={mockOnChange} />);
    
    const select = screen.getByRole('combobox');
    fireEvent.change(select, { target: { value: '2' } });

    expect(mockOnChange).toHaveBeenCalledWith(2);
  });

  test('displays selected vehicle', () => {
    render(
      <VehicleSelector 
        vehicles={mockVehicles} 
        value={mockVehicles[0].id} 
      />
    );
    
    const select = screen.getByRole('combobox');
    expect(select).toHaveValue('1');
  });

  test('displays placeholder when no vehicle selected', () => {
    render(<VehicleSelector vehicles={mockVehicles} />);
    // i18n mock returns the key; the option renders 'common.selectVehicle'
    expect(screen.getByText('common.selectVehicle')).toBeInTheDocument();
  });

  test('displays empty state when no vehicles', () => {
    render(<VehicleSelector vehicles={[]} />);
    // i18n mock returns the key
    expect(screen.getByText('common.noVehicles')).toBeInTheDocument();
  });

  test('displays add vehicle button', () => {
    render(<VehicleSelector vehicles={mockVehicles} showAddButton={true} />);
    // i18n mock returns the key
    expect(screen.getByRole('button', { name: /vehicles\.addVehicle/i })).toBeInTheDocument();
  });

  test('calls onAddVehicle when add button clicked', () => {
    const mockOnAdd = jest.fn();
    render(
      <VehicleSelector 
        vehicles={mockVehicles} 
        showAddButton={true}
        onAddVehicle={mockOnAdd}
      />
    );
    
    fireEvent.click(screen.getByRole('button', { name: /vehicles\.addVehicle/i }));

    expect(mockOnAdd).toHaveBeenCalled();
  });

  test('displays vehicle with custom format', () => {
    const formatVehicle = (vehicle) => `${vehicle.registration} (${vehicle.year})`;
    
    render(
      <VehicleSelector 
        vehicles={mockVehicles}
        formatVehicle={formatVehicle}
      />
    );
    
    const select = screen.getByRole('combobox');
    fireEvent.click(select);

    expect(screen.getByText('ABC123 (2020)')).toBeInTheDocument();
  });

  test('filters vehicles by search term', () => {
    render(<VehicleSelector vehicles={mockVehicles} enableSearch={true} />);
    
    const searchInput = screen.getByPlaceholderText(/search/i);
    fireEvent.change(searchInput, { target: { value: 'Toyota' } });

    expect(screen.getByText(/Toyota/i)).toBeInTheDocument();
    expect(screen.queryByText(/Honda/i)).not.toBeInTheDocument();
  });

  test('displays vehicle count', () => {
    render(<VehicleSelector vehicles={mockVehicles} showCount={true} />);
    // i18n mock returns the key; rendered as '2 vehicles.title'
    expect(screen.getByText(/^2 /)).toBeInTheDocument();
  });

  test('disables selector when disabled prop is true', () => {
    render(<VehicleSelector vehicles={mockVehicles} disabled={true} />);
    
    const select = screen.getByRole('combobox');
    expect(select).toBeDisabled();
  });

  test('displays loading state', () => {
    render(<VehicleSelector vehicles={[]} loading={true} />);
    expect(screen.getByText(/loading/i)).toBeInTheDocument();
  });

  test('displays error state', () => {
    render(<VehicleSelector vehicles={[]} error="Failed to load vehicles" />);
    expect(screen.getByText(/failed to load vehicles/i)).toBeInTheDocument();
  });

  test('groups vehicles by make', () => {
    const { container } = render(<VehicleSelector vehicles={mockVehicles} groupByMake={true} />);

    // groupByMake renders <optgroup label="Make"> elements
    expect(container.querySelector('optgroup[label="Toyota"]')).toBeInTheDocument();
    expect(container.querySelector('optgroup[label="Honda"]')).toBeInTheDocument();
  });

  test('displays vehicle images in dropdown', () => {
    const vehiclesWithImages = mockVehicles.map(v => ({
      ...v,
      imageUrl: `https://example.com/${v.id}.jpg`,
    }));

    render(<VehicleSelector vehicles={vehiclesWithImages} showImages={true} />);
    
    const images = screen.getAllByRole('img');
    expect(images).toHaveLength(2);
  });

  test('sorts vehicles by vehicle type then year then make/model ascending', () => {
    const unsortedVehicles = [
      { id: 1, name: 'Transit', registrationNumber: 'ZZZ999', vehicleType: { name: 'Van' }, year: 2020, make: { name: 'Ford' }, model: { name: 'Transit' } },
      { id: 2, name: 'Civic', registrationNumber: 'AAA111', vehicleType: { name: 'Car' }, year: 2022, make: { name: 'Honda' }, model: { name: 'Civic' } },
      { id: 3, name: 'Corolla', registrationNumber: 'BBB222', vehicleType: { name: 'Car' }, year: 2020, make: { name: 'Toyota' }, model: { name: 'Corolla' } },
      { id: 4, name: 'Civic', registrationNumber: 'AAA222', vehicleType: { name: 'Car' }, year: 2020, make: { name: 'Honda' }, model: { name: 'Civic' } },
    ];

    render(
      <VehicleSelector
        vehicles={unsortedVehicles}
        formatVehicle={(vehicle) => `${vehicle.vehicleType.name} - ${vehicle.year} ${vehicle.make.name} ${vehicle.model.name}`}
      />
    );

    const options = Array.from(screen.getByRole('combobox').querySelectorAll('option')).map((option) => option.textContent);
    expect(options.slice(1)).toEqual([
      'Car - 2020 Honda Civic',
      'Car - 2020 Toyota Corolla',
      'Car - 2022 Honda Civic',
      'Van - 2020 Ford Transit',
    ]);
  });
});
