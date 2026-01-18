import React from 'react';
import { render, screen } from '@testing-library/react';
import FuelEconomyChart from '../components/FuelEconomyChart';
import '@testing-library/jest-dom';

// Mock recharts
jest.mock('recharts', () => {
  const OriginalModule = jest.requireActual('recharts');
  return {
    ...OriginalModule,
    ResponsiveContainer: ({ children }) => (
      <div data-testid="responsive-container">{children}</div>
    ),
  };
});

// Mock i18next
jest.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: (key) => key,
  }),
}));

describe('FuelEconomyChart Component', () => {
  const mockFuelData = [
    { date: '2024-01-15', mpg: 48.5, litres: 45.0, cost: 68.25 },
    { date: '2024-02-10', mpg: 50.2, litres: 44.0, cost: 66.00 },
    { date: '2024-03-05', mpg: 49.8, litres: 45.5, cost: 70.00 },
    { date: '2024-04-12', mpg: 51.0, litres: 43.5, cost: 65.25 },
  ];

  test('renders fuel economy chart', () => {
    render(<FuelEconomyChart data={mockFuelData} />);
    expect(screen.getByTestId('responsive-container')).toBeInTheDocument();
  });

  test('displays chart title', () => {
    render(<FuelEconomyChart data={mockFuelData} title="Fuel Economy Trend" />);
    expect(screen.getByText('Fuel Economy Trend')).toBeInTheDocument();
  });

  test('displays average MPG', () => {
    render(<FuelEconomyChart data={mockFuelData} />);
    // Average: (48.5 + 50.2 + 49.8 + 51.0) / 4 = 49.875 ≈ 49.9
    expect(screen.getByText(/49.9 mpg/i)).toBeInTheDocument();
  });

  test('displays best MPG', () => {
    render(<FuelEconomyChart data={mockFuelData} />);
    expect(screen.getByText(/51.0 mpg/i)).toBeInTheDocument();
  });

  test('displays worst MPG', () => {
    render(<FuelEconomyChart data={mockFuelData} />);
    expect(screen.getByText(/48.5 mpg/i)).toBeInTheDocument();
  });

  test('displays total fuel cost', () => {
    render(<FuelEconomyChart data={mockFuelData} />);
    // £68.25 + £66.00 + £70.00 + £65.25 = £269.50
    expect(screen.getByText(/£269.50/i)).toBeInTheDocument();
  });

  test('displays total litres used', () => {
    render(<FuelEconomyChart data={mockFuelData} />);
    // 45.0 + 44.0 + 45.5 + 43.5 = 178.0
    expect(screen.getByText(/178.0 litres/i)).toBeInTheDocument();
  });

  test('calculates average L/100km', () => {
    render(<FuelEconomyChart data={mockFuelData} />);
    // Average MPG: 49.9, convert to L/100km: 282.48 / 49.9 ≈ 5.7
    expect(screen.getByText(/5.7 L\/100km/i)).toBeInTheDocument();
  });

  test('displays trend line', () => {
    render(<FuelEconomyChart data={mockFuelData} showTrend={true} />);
    expect(screen.getByTestId('responsive-container')).toBeInTheDocument();
  });

  test('handles empty data', () => {
    render(<FuelEconomyChart data={[]} />);
    expect(screen.getByText(/no data/i)).toBeInTheDocument();
  });

  test('displays date range', () => {
    render(<FuelEconomyChart data={mockFuelData} />);
    expect(screen.getByText(/2024-01-15/i)).toBeInTheDocument();
    expect(screen.getByText(/2024-04-12/i)).toBeInTheDocument();
  });

  test('switches between MPG and L/100km', () => {
    const { rerender } = render(<FuelEconomyChart data={mockFuelData} unit="mpg" />);
    expect(screen.getByText(/mpg/i)).toBeInTheDocument();

    rerender(<FuelEconomyChart data={mockFuelData} unit="l100km" />);
    expect(screen.getByText(/L\/100km/i)).toBeInTheDocument();
  });

  test('displays cost per mile', () => {
    render(<FuelEconomyChart data={mockFuelData} totalMiles={2000} />);
    // £269.50 / 2000 miles = £0.135 per mile
    expect(screen.getByText(/£0.14 per mile/i)).toBeInTheDocument();
  });

  test('displays monthly average', () => {
    render(<FuelEconomyChart data={mockFuelData} viewType="monthly" />);
    expect(screen.getByText(/monthly average/i)).toBeInTheDocument();
  });

  test('compares to manufacturer MPG', () => {
    render(<FuelEconomyChart data={mockFuelData} manufacturerMpg={55.0} />);
    // Average 49.9 vs manufacturer 55.0 = -9.3%
    expect(screen.getByText(/-9.3%/i)).toBeInTheDocument();
  });

  test('displays fuel efficiency rating', () => {
    render(<FuelEconomyChart data={mockFuelData} />);
    expect(screen.getByText(/efficiency rating/i)).toBeInTheDocument();
  });

  test('shows seasonal variations', () => {
    render(<FuelEconomyChart data={mockFuelData} showSeasons={true} />);
    expect(screen.getByText(/winter/i)).toBeInTheDocument();
  });

  test('displays driving style impact', () => {
    const dataWithStyle = mockFuelData.map(d => ({ ...d, drivingStyle: 'highway' }));
    render(<FuelEconomyChart data={dataWithStyle} groupByStyle={true} />);
    expect(screen.getByText(/highway/i)).toBeInTheDocument();
  });

  test('calculates improvement over time', () => {
    render(<FuelEconomyChart data={mockFuelData} />);
    // From 48.5 to 51.0 = +5.2% improvement
    expect(screen.getByText(/\+5.2%/i)).toBeInTheDocument();
  });
});
