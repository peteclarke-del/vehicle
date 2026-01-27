export default function formatCurrency(value, currency = 'GBP', locale) {
  const number = Number(value ?? 0);
  if (Number.isNaN(number)) return '';
  try {
    return new Intl.NumberFormat(locale || undefined, {
      style: 'currency',
      currency,
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    }).format(number);
  } catch (e) {
    return `${currency} ${number.toFixed(2)}`;
  }
}
