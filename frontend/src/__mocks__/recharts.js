const React = require('react');

module.exports = {
  ResponsiveContainer: ({ children }) => React.createElement('div', { 'data-testid': 'responsive-container' }, children),
  LineChart: ({ children }) => React.createElement('div', { 'data-testid': 'line-chart' }, children),
  BarChart: ({ children }) => React.createElement('div', { 'data-testid': 'bar-chart' }, children),
  PieChart: ({ children }) => React.createElement('div', { 'data-testid': 'pie-chart' }, children),
  AreaChart: ({ children }) => React.createElement('div', { 'data-testid': 'area-chart' }, children),
  Line: () => null,
  Bar: () => null,
  Pie: () => null,
  Area: () => null,
  Cell: () => null,
  XAxis: () => null,
  YAxis: () => null,
  CartesianGrid: () => null,
  Tooltip: () => null,
  Legend: () => null,
  ReferenceLine: () => null,
};
