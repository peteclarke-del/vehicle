import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';
import VehicleCard from '../components/VehicleCard';
import { BrowserRouter } from 'react-router-dom';
import '@testing-library/jest-dom';

// Mock i18next
jest.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: (key) => key,
  }),
}));

const renderWithRouter = (component) => {
  return render(<BrowserRouter>{component}</BrowserRouter>);
};

describe('VehicleCard Component', () => {
  const mockVehicle = {
    id: 1,
    registration: 'ABC123',
    make: { name: 'Toyota' },
    model: { name: 'Corolla' },
    year: 2020,
    colour: 'Silver',
    mileage: 50000,
    currentValue: 12000,
    imageUrl: 'https://example.com/car.jpg',
  };

  test('renders vehicle card', () => {
    renderWithRouter(<VehicleCard vehicle={mockVehicle} />);
    expect(screen.getByText('ABC123')).toBeInTheDocument();
  });

  test('displays vehicle information', () => {
    renderWithRouter(<VehicleCard vehicle={mockVehicle} />);
    
    expect(screen.getByText('2020 Toyota Corolla')).toBeInTheDocument();
    expect(screen.getByText('Silver')).toBeInTheDocument();
    expect(screen.getByText(/50,000/i)).toBeInTheDocument();
  });

  test('displays vehicle image', () => {
    renderWithRouter(<VehicleCard vehicle={mockVehicle} />);
    
    const image = screen.getByAlt(/Toyota Corolla/i);
    expect(image).toHaveAttribute('src', 'https://example.com/car.jpg');
  });

  test('displays fallback image when no image provided', () => {
    const vehicleWithoutImage = { ...mockVehicle, imageUrl: null };
    renderWithRouter(<VehicleCard vehicle={vehicleWithoutImage} />);
    
    const image = screen.getByAlt(/Toyota Corolla/i);
    expect(image).toHaveAttribute('src', expect.stringContaining('placeholder'));
  });

  test('displays current value', () => {
    renderWithRouter(<VehicleCard vehicle={mockVehicle} />);
    expect(screen.getByText(/£12,000.00/i)).toBeInTheDocument();
  });

  test('navigates to vehicle details on click', () => {
    renderWithRouter(<VehicleCard vehicle={mockVehicle} />);
    
    const card = screen.getByRole('button');
    fireEvent.click(card);

    expect(window.location.pathname).toContain('/vehicles/1');
  });

  test('displays MOT status badge', () => {
    const vehicleWithMot = {
      ...mockVehicle,
      motExpiry: '2025-06-15',
      motValid: true,
    };

    renderWithRouter(<VehicleCard vehicle={vehicleWithMot} />);
    expect(screen.getByText(/mot valid/i)).toBeInTheDocument();
  });

  test('displays MOT expired badge', () => {
    const vehicleWithExpiredMot = {
      ...mockVehicle,
      motExpiry: '2023-01-15',
      motValid: false,
    };

    renderWithRouter(<VehicleCard vehicle={vehicleWithExpiredMot} />);
    expect(screen.getByText(/mot expired/i)).toBeInTheDocument();
  });

  test('displays insurance status badge', () => {
    const vehicleWithInsurance = {
      ...mockVehicle,
      insuranceExpiry: '2025-03-20',
      insuranceValid: true,
    };

    renderWithRouter(<VehicleCard vehicle={vehicleWithInsurance} />);
    expect(screen.getByText(/insured/i)).toBeInTheDocument();
  });

  test('displays quick action buttons', () => {
    renderWithRouter(<VehicleCard vehicle={mockVehicle} showActions={true} />);
    
    expect(screen.getByLabelText(/edit/i)).toBeInTheDocument();
    expect(screen.getByLabelText(/delete/i)).toBeInTheDocument();
  });

  test('calls onEdit when edit button clicked', () => {
    const mockOnEdit = jest.fn();
    renderWithRouter(
      <VehicleCard vehicle={mockVehicle} showActions={true} onEdit={mockOnEdit} />
    );
    
    const editButton = screen.getByLabelText(/edit/i);
    fireEvent.click(editButton);

    expect(mockOnEdit).toHaveBeenCalledWith(mockVehicle);
  });

  test('calls onDelete when delete button clicked', () => {
    const mockOnDelete = jest.fn();
    renderWithRouter(
      <VehicleCard vehicle={mockVehicle} showActions={true} onDelete={mockOnDelete} />
    );
    
    const deleteButton = screen.getByLabelText(/delete/i);
    fireEvent.click(deleteButton);

    expect(mockOnDelete).toHaveBeenCalledWith(mockVehicle);
  });

  test('displays compact view', () => {
    renderWithRouter(<VehicleCard vehicle={mockVehicle} compact={true} />);
    
    expect(screen.getByText('ABC123')).toBeInTheDocument();
    expect(screen.queryByText('Silver')).not.toBeInTheDocument();
  });

  test('displays vehicle age', () => {
    renderWithRouter(<VehicleCard vehicle={mockVehicle} />);
    
    const age = new Date().getFullYear() - 2020;
    expect(screen.getByText(new RegExp(`${age} years`, 'i'))).toBeInTheDocument();
  });

  test('displays next service due', () => {
    const vehicleWithService = {
      ...mockVehicle,
      nextServiceMileage: 60000,
    };

    renderWithRouter(<VehicleCard vehicle={vehicleWithService} />);
    expect(screen.getByText(/next service: 60,000/i)).toBeInTheDocument();
  });

  test('displays fuel type badge', () => {
    const vehicleWithFuel = {
      ...mockVehicle,
      fuelType: 'Petrol',
    };

    renderWithRouter(<VehicleCard vehicle={vehicleWithFuel} />);
    expect(screen.getByText('Petrol')).toBeInTheDocument();
  });

  test('displays transmission type', () => {
    const vehicleWithTransmission = {
      ...mockVehicle,
      transmission: 'Manual',
    };

    renderWithRouter(<VehicleCard vehicle={vehicleWithTransmission} />);
    expect(screen.getByText('Manual')).toBeInTheDocument();
  });
});
