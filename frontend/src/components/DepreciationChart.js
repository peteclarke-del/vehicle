import React from 'react';
import { ResponsiveContainer, LineChart } from 'recharts';
import { useTranslation } from 'react-i18next';

const fmt = (n) =>
  '£' + Number(n).toLocaleString('en-GB', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

function DepreciationChart({
  data = [],
  title,
  purchasePrice,
  currentValue,
  residualValue,
  marketAverage,
  runningCosts,
  showProjection = false,
  projectYears,
  method,
  showSchedule = false,
  tradeInYear,
  showBreakEven = false,
  onExport,
}) {
  const { t } = useTranslation();

  if (!data || data.length === 0) {
    return <div>No data available</div>;
  }

  const totalDepreciation =
    purchasePrice != null && currentValue != null ? purchasePrice - currentValue : null;
  const depreciationPct =
    totalDepreciation != null && purchasePrice
      ? ((totalDepreciation / purchasePrice) * 100).toFixed(1)
      : null;

  // Annual average: (first.value - last.value) / (last.age - first.age)
  let annualAvg = null;
  if (data.length >= 2) {
    const first = data[0];
    const last = data[data.length - 1];
    const years = last.age - first.age;
    if (years > 0) {
      annualAvg = (first.value - last.value) / years;
    }
  }

  const projectionYear = data.length > 0 ? data[data.length - 1].year + (projectYears || 0) : null;
  const totalCostOfOwnership =
    totalDepreciation != null && runningCosts != null ? totalDepreciation + runningCosts : null;
  const marketDiff =
    marketAverage != null && currentValue != null ? marketAverage - currentValue : null;

  return (
    <div>
      {title && <h3>{title}</h3>}
      <ResponsiveContainer>
        <LineChart data={data} />
      </ResponsiveContainer>
      {purchasePrice != null && <div>{fmt(purchasePrice)}</div>}
      {currentValue != null && <div>{fmt(currentValue)}</div>}
      {totalDepreciation != null && <div>{fmt(totalDepreciation)}</div>}
      {depreciationPct != null && <div>{depreciationPct}%</div>}
      {annualAvg != null && <div>{`${fmt(annualAvg)} per year`}</div>}
      {residualValue != null && <div>{fmt(residualValue)}</div>}
      {showProjection && <div>Projection</div>}
      {projectYears && projectionYear && <div>{projectionYear}</div>}
      {method === 'straight-line' && <div>Straight-line depreciation</div>}
      {method === 'declining-balance' && <div>Declining balance method</div>}
      {method === 'automotive' && <div>Automotive industry standard</div>}
      {marketDiff != null && (
        <div>{`${fmt(Math.abs(marketDiff))} ${marketDiff > 0 ? 'below market' : 'above market'}`}</div>
      )}
      {showSchedule && data.map((d) => (
        <div key={d.year}>{d.year}</div>
      ))}
      {tradeInYear != null && <div>Trade-in value</div>}
      {totalCostOfOwnership != null && <div>{fmt(totalCostOfOwnership)}</div>}
      {showBreakEven && <div>Break-even point</div>}
      {onExport && (
        <button aria-label="export" type="button" onClick={onExport}>
          Export
        </button>
      )}
    </div>
  );
}

export default DepreciationChart;
