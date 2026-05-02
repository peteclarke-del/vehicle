import React, { useState } from 'react';
import { ResponsiveContainer, PieChart, BarChart, LineChart } from 'recharts';
import { useTranslation } from 'react-i18next';

const fmt = (n) =>
  '£' + Number(n).toLocaleString('en-GB', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

function CostChart({
  data = [],
  title,
  chartType = 'pie',
  showTotal = false,
  showLegend = false,
  colors = ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF'],
  showComparison = false,
  onExport,
  viewType,
}) {
  const { t } = useTranslation();
  const [hovered, setHovered] = useState(null);

  if (!data || data.length === 0) {
    return <div>No data available</div>;
  }

  const total = data.reduce((sum, d) => sum + (d.amount || 0), 0);

  const renderChart = () => {
    if (chartType === 'bar') {
      return (
        <ResponsiveContainer>
          <BarChart data={data} />
        </ResponsiveContainer>
      );
    }
    if (chartType === 'line') {
      return (
        <ResponsiveContainer>
          <LineChart data={data} />
        </ResponsiveContainer>
      );
    }
    return (
      <ResponsiveContainer>
        <PieChart data={data} />
      </ResponsiveContainer>
    );
  };

  return (
    <div>
      {title && <h3>{title}</h3>}
      {renderChart()}
      {showTotal && <div>{fmt(total)}</div>}
      {showComparison && data.map((d) => {
        if (d.previousAmount == null) return null;
        const pct = ((d.amount - d.previousAmount) / d.previousAmount) * 100;
        const sign = pct >= 0 ? '+' : '';
        return (
          <div key={d.category}>
            {d.category}: {sign}{pct.toFixed(1)}%
          </div>
        );
      })}
      <div>
        {data.map((d, i) => (
          <div
            key={d.category || d.month || d.year || i}
            style={{ color: colors[i % colors.length] }}
            onMouseEnter={() => setHovered(d)}
            onMouseLeave={() => setHovered(null)}
          >
            {d.category || d.month || d.year}
            {d.amount != null && <span> {fmt(d.amount)}</span>}
            {hovered === d && d.percentage != null && (
              <span> ({d.percentage}%)</span>
            )}
          </div>
        ))}
      </div>
      {onExport && (
        <button aria-label="export" type="button" onClick={onExport}>
          Export
        </button>
      )}
    </div>
  );
}

export default CostChart;
