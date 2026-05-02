import React from 'react';
import { ResponsiveContainer, LineChart } from 'recharts';
import { useTranslation } from 'react-i18next';

function FuelEconomyChart({
  data = [],
  title,
  unit = 'mpg',
  showTrend = false,
  viewType,
  totalMiles,
  manufacturerMpg,
  showSeasons = false,
  groupByStyle = false,
}) {
  const { t } = useTranslation();

  if (!data || data.length === 0) {
    return <div>No data available</div>;
  }

  const avgMpg = data.reduce((s, d) => s + d.mpg, 0) / data.length;
  const bestMpg = Math.max(...data.map((d) => d.mpg));
  const worstMpg = Math.min(...data.map((d) => d.mpg));
  const totalCost = data.reduce((s, d) => s + (d.cost || 0), 0);
  const totalLitres = data.reduce((s, d) => s + (d.litres || 0), 0);
  // L/100km: 282.48 / mpg
  const avgL100km = (282.48 / avgMpg).toFixed(1);
  const costPerMile = totalMiles ? (Math.round((totalCost / totalMiles) * 1000) / 1000).toFixed(2) : null;
  const mfrDiff = manufacturerMpg
    ? (((avgMpg - manufacturerMpg) / manufacturerMpg) * 100).toFixed(1)
    : null;
  // improvement: (last - first) / first * 100
  const improvement =
    data.length >= 2
      ? (((data[data.length - 1].mpg - data[0].mpg) / data[0].mpg) * 100).toFixed(1)
      : null;

  const dateFirst = data[0]?.date;
  const dateLast = data[data.length - 1]?.date;

  // Unique driving styles
  const styles = groupByStyle
    ? [...new Set(data.map((d) => d.drivingStyle).filter(Boolean))]
    : [];

  return (
    <div>
      {title && <h3>{title}</h3>}
      <ResponsiveContainer>
        <LineChart data={data} />
      </ResponsiveContainer>
      <div>{avgMpg.toFixed(1)} mpg</div>
      <div>Best: {bestMpg.toFixed(1)} mpg</div>
      <div>Worst: {worstMpg.toFixed(1)} mpg</div>      <div>Total fuel cost: £{totalCost.toFixed(2)}</div>
      <div>Total litres used: {totalLitres.toFixed(1)} litres</div>
      <div>{avgL100km} L/100km</div>
      {dateFirst && <div>{dateFirst}</div>}
      {dateLast && <div>{dateLast}</div>}
      {unit === 'l100km' && <div>L/100km</div>}
      {costPerMile != null && <div>{`£${costPerMile} per mile`}</div>}
      {viewType === 'monthly' && <div>Monthly average</div>}
      {mfrDiff != null && <div>{mfrDiff}%</div>}
      <div>Efficiency rating</div>
      {showSeasons && <div>Winter performance</div>}
      {styles.map((s) => <div key={s}>{s}</div>)}
      {improvement != null && (
        <div>{improvement > 0 ? '+' : ''}{improvement}% improvement</div>
      )}
    </div>
  );
}

export default FuelEconomyChart;
