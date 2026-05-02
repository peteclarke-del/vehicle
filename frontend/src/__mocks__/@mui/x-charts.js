const React = require('react');

const PieChart = ({ children }) => React.createElement('div', { 'data-testid': 'pie-chart' }, children);
const BarChart = ({ children }) => React.createElement('div', { 'data-testid': 'bar-chart' }, children);
const LineChart = ({ children }) => React.createElement('div', { 'data-testid': 'line-chart' }, children);
const ScatterChart = ({ children }) => React.createElement('div', { 'data-testid': 'scatter-chart' }, children);
const SparkLineChart = ({ children }) => React.createElement('div', { 'data-testid': 'sparkline-chart' }, children);

module.exports = {
  PieChart,
  BarChart,
  LineChart,
  ScatterChart,
  SparkLineChart,
};
