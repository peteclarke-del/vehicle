import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';
import CostChart from '../components/CostChart';
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

describe('CostChart Component', () => {
  const mockCostData = [
    {
      category: 'Insurance',
      amount: 1000,
      percentage: 25,
    },
    {
      category: 'Service',
      amount: 1500,
      percentage: 37.5,
    },
    {
      category: 'Fuel',
      amount: 1000,
      percentage: 25,
    },
    {
      category: 'Parts',
      amount: 500,
      percentage: 12.5,
    },
  ];

  test('renders cost chart', () => {
    render(<CostChart data={mockCostData} />);
    expect(screen.getByTestId('responsive-container')).toBeInTheDocument();
  });

  test('displays chart title', () => {
    render(<CostChart data={mockCostData} title="Cost Breakdown" />);
    expect(screen.getByText('Cost Breakdown')).toBeInTheDocument();
  });

  test('renders pie chart by default', () => {
    render(<CostChart data={mockCostData} chartType="pie" />);
    expect(screen.getByTestId('responsive-container')).toBeInTheDocument();
  });

  test('renders bar chart when specified', () => {
    render(<CostChart data={mockCostData} chartType="bar" />);
    expect(screen.getByTestId('responsive-container')).toBeInTheDocument();
  });

  test('renders line chart when specified', () => {
    render(<CostChart data={mockCostData} chartType="line" />);
    expect(screen.getByTestId('responsive-container')).toBeInTheDocument();
  });

  test('displays total cost', () => {
    render(<CostChart data={mockCostData} showTotal={true} />);
    // Total: £4000
    expect(screen.getByText(/£4,000.00/i)).toBeInTheDocument();
  });

  test('displays legend', () => {
    render(<CostChart data={mockCostData} showLegend={true} />);
    expect(screen.getByText('Insurance')).toBeInTheDocument();
    expect(screen.getByText('Service')).toBeInTheDocument();
    expect(screen.getByText('Fuel')).toBeInTheDocument();
    expect(screen.getByText('Parts')).toBeInTheDocument();
  });

  test('displays percentages on hover', () => {
    render(<CostChart data={mockCostData} />);
    
    const insuranceSection = screen.getByText('Insurance');
    fireEvent.mouseEnter(insuranceSection);

    expect(screen.getByText(/25%/i)).toBeInTheDocument();
  });

  test('handles empty data', () => {
    render(<CostChart data={[]} />);
    expect(screen.getByText(/no data/i)).toBeInTheDocument();
  });

  test('formats currency correctly', () => {
    render(<CostChart data={mockCostData} />);
    expect(screen.getByText(/£1,000.00/i)).toBeInTheDocument();
    expect(screen.getByText(/£1,500.00/i)).toBeInTheDocument();
  });

  test('allows chart type switching', () => {
    const { rerender } = render(<CostChart data={mockCostData} chartType="pie" />);
    expect(screen.getByTestId('responsive-container')).toBeInTheDocument();

    rerender(<CostChart data={mockCostData} chartType="bar" />);
    expect(screen.getByTestId('responsive-container')).toBeInTheDocument();
  });

  test('displays custom colors', () => {
    const colors = ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0'];
    render(<CostChart data={mockCostData} colors={colors} />);
    expect(screen.getByTestId('responsive-container')).toBeInTheDocument();
  });

  test('displays tooltip on hover', () => {
    render(<CostChart data={mockCostData} />);
    
    const insuranceSection = screen.getByText('Insurance');
    fireEvent.mouseEnter(insuranceSection);

    expect(screen.getByText(/£1,000.00/i)).toBeInTheDocument();
  });

  test('displays cost comparison', () => {
    const comparisonData = [
      { category: 'Insurance', amount: 1000, previousAmount: 950 },
      { category: 'Service', amount: 1500, previousAmount: 1400 },
    ];

    render(<CostChart data={comparisonData} showComparison={true} />);
    expect(screen.getByText(/+5.3%/i)).toBeInTheDocument(); // Insurance increase
    expect(screen.getByText(/+7.1%/i)).toBeInTheDocument(); // Service increase
  });

  test('exports chart as image', () => {
    const mockExport = jest.fn();
    render(<CostChart data={mockCostData} onExport={mockExport} />);
    
    const exportButton = screen.getByLabelText(/export/i);
    fireEvent.click(exportButton);

    expect(mockExport).toHaveBeenCalled();
  });

  test('displays monthly view', () => {
    const monthlyData = [
      { month: 'Jan', amount: 400 },
      { month: 'Feb', amount: 500 },
      { month: 'Mar', amount: 450 },
    ];

    render(<CostChart data={monthlyData} viewType="monthly" />);
    expect(screen.getByText('Jan')).toBeInTheDocument();
    expect(screen.getByText('Feb')).toBeInTheDocument();
  });

  test('displays annual view', () => {
    const annualData = [
      { year: '2022', amount: 5000 },
      { year: '2023', amount: 5500 },
      { year: '2024', amount: 6000 },
    ];

    render(<CostChart data={annualData} viewType="annual" />);
    expect(screen.getByText('2022')).toBeInTheDocument();
    expect(screen.getByText('2023')).toBeInTheDocument();
  });
});
