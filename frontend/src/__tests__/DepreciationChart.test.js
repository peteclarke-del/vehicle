import React from 'react';
import { render, screen } from '@testing-library/react';
import DepreciationChart from '../components/DepreciationChart';
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

describe('DepreciationChart Component', () => {
  const mockDepreciationData = [
    { year: 2020, value: 15000, age: 0 },
    { year: 2021, value: 13500, age: 1 },
    { year: 2022, value: 12000, age: 2 },
    { year: 2023, value: 10500, age: 3 },
    { year: 2024, value: 9000, age: 4 },
  ];

  test('renders depreciation chart', () => {
    render(<DepreciationChart data={mockDepreciationData} />);
    expect(screen.getByTestId('responsive-container')).toBeInTheDocument();
  });

  test('displays chart title', () => {
    render(<DepreciationChart data={mockDepreciationData} title="Vehicle Depreciation" />);
    expect(screen.getByText('Vehicle Depreciation')).toBeInTheDocument();
  });

  test('displays current value', () => {
    render(<DepreciationChart data={mockDepreciationData} currentValue={9000} />);
    expect(screen.getByText(/£9,000.00/i)).toBeInTheDocument();
  });

  test('displays purchase price', () => {
    render(<DepreciationChart data={mockDepreciationData} purchasePrice={15000} />);
    expect(screen.getByText(/£15,000.00/i)).toBeInTheDocument();
  });

  test('calculates total depreciation', () => {
    render(<DepreciationChart data={mockDepreciationData} purchasePrice={15000} currentValue={9000} />);
    // £15000 - £9000 = £6000
    expect(screen.getByText(/£6,000.00/i)).toBeInTheDocument();
  });

  test('calculates depreciation percentage', () => {
    render(<DepreciationChart data={mockDepreciationData} purchasePrice={15000} currentValue={9000} />);
    // (£6000 / £15000) * 100 = 40%
    expect(screen.getByText(/40.0%/i)).toBeInTheDocument();
  });

  test('displays annual depreciation rate', () => {
    render(<DepreciationChart data={mockDepreciationData} />);
    // Average annual: £1500 per year
    expect(screen.getByText(/£1,500.00 per year/i)).toBeInTheDocument();
  });

  test('handles empty data', () => {
    render(<DepreciationChart data={[]} />);
    expect(screen.getByText(/no data/i)).toBeInTheDocument();
  });

  test('displays projection line', () => {
    render(<DepreciationChart data={mockDepreciationData} showProjection={true} />);
    expect(screen.getByText(/projection/i)).toBeInTheDocument();
  });

  test('projects future value', () => {
    render(<DepreciationChart data={mockDepreciationData} projectYears={2} />);
    // 2026 projection
    expect(screen.getByText(/2026/i)).toBeInTheDocument();
  });

  test('displays straight-line depreciation method', () => {
    render(<DepreciationChart data={mockDepreciationData} method="straight-line" />);
    expect(screen.getByText(/straight-line/i)).toBeInTheDocument();
  });

  test('displays declining balance method', () => {
    render(<DepreciationChart data={mockDepreciationData} method="declining-balance" />);
    expect(screen.getByText(/declining balance/i)).toBeInTheDocument();
  });

  test('displays automotive industry method', () => {
    render(<DepreciationChart data={mockDepreciationData} method="automotive" />);
    expect(screen.getByText(/automotive/i)).toBeInTheDocument();
  });

  test('displays residual value', () => {
    render(<DepreciationChart data={mockDepreciationData} residualValue={7000} />);
    expect(screen.getByText(/£7,000.00/i)).toBeInTheDocument();
  });

  test('compares to market average', () => {
    render(<DepreciationChart data={mockDepreciationData} marketAverage={10000} currentValue={9000} />);
    // £1000 below market
    expect(screen.getByText(/£1,000.00 below market/i)).toBeInTheDocument();
  });

  test('displays depreciation schedule', () => {
    render(<DepreciationChart data={mockDepreciationData} showSchedule={true} />);
    expect(screen.getByText('2020')).toBeInTheDocument();
    expect(screen.getByText('2021')).toBeInTheDocument();
    expect(screen.getByText('2022')).toBeInTheDocument();
  });

  test('calculates remaining value at trade-in', () => {
    render(<DepreciationChart data={mockDepreciationData} tradeInYear={2025} />);
    expect(screen.getByText(/trade-in value/i)).toBeInTheDocument();
  });

  test('displays total cost of ownership', () => {
    render(
      <DepreciationChart 
        data={mockDepreciationData} 
        purchasePrice={15000}
        currentValue={9000}
        runningCosts={5000}
      />
    );
    // £6000 depreciation + £5000 running = £11000 total
    expect(screen.getByText(/£11,000.00/i)).toBeInTheDocument();
  });

  test('shows break-even point', () => {
    render(<DepreciationChart data={mockDepreciationData} showBreakEven={true} />);
    expect(screen.getByText(/break-even/i)).toBeInTheDocument();
  });

  test('exports chart data', () => {
    const mockExport = jest.fn();
    render(<DepreciationChart data={mockDepreciationData} onExport={mockExport} />);
    
    const exportButton = screen.getByLabelText(/export/i);
    fireEvent.click(exportButton);

    expect(mockExport).toHaveBeenCalled();
  });
});
