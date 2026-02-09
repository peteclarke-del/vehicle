/**
 * Utility functions for formatting values
 */

/**
 * Format a number as currency
 */
export const formatCurrency = (
  amount: number | string | null | undefined,
  currency: string = 'GBP',
  locale: string = 'en-GB',
): string => {
  const safeAmount = Number(amount) || 0;
  try {
    return new Intl.NumberFormat(locale, {
      style: 'currency',
      currency,
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    }).format(safeAmount);
  } catch {
    // Fallback if Intl is not available
    const symbols: Record<string, string> = {
      GBP: '£',
      USD: '$',
      EUR: '€',
      AUD: 'A$',
      CAD: 'C$',
    };
    const symbol = symbols[currency] || currency;
    return `${symbol}${safeAmount.toFixed(2)}`;
  }
};

/**
 * Format a date string for display
 */
export const formatDate = (
  dateString: string | null | undefined,
  options?: Intl.DateTimeFormatOptions,
): string => {
  if (!dateString) return '-';

  try {
    const date = new Date(dateString);
    if (isNaN(date.getTime())) return dateString;

    return date.toLocaleDateString('en-GB', {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
      ...options,
    });
  } catch {
    return dateString;
  }
};

/**
 * Format a date string as relative time (e.g., "2 days ago")
 */
export const formatRelativeTime = (dateString: string | null | undefined): string => {
  if (!dateString) return '-';

  try {
    const date = new Date(dateString);
    if (isNaN(date.getTime())) return dateString;

    const now = new Date();
    const diffMs = now.getTime() - date.getTime();
    const diffDays = Math.floor(diffMs / (1000 * 60 * 60 * 24));

    if (diffDays === 0) return 'Today';
    if (diffDays === 1) return 'Yesterday';
    if (diffDays < 7) return `${diffDays} days ago`;
    if (diffDays < 30) return `${Math.floor(diffDays / 7)} weeks ago`;
    if (diffDays < 365) return `${Math.floor(diffDays / 30)} months ago`;
    return `${Math.floor(diffDays / 365)} years ago`;
  } catch {
    return dateString;
  }
};

/**
 * Format a number with thousand separators
 */
export const formatNumber = (num: number | null | undefined): string => {
  if (num === null || num === undefined) return '-';
  return num.toLocaleString('en-GB');
};

/**
 * Convert distance from km (DB storage) to user's preferred unit.
 * The database stores all distances in kilometres.
 * If user prefers miles, convert km → miles.
 * If user prefers km, return as-is.
 */
export const convertDistance = (
  valueInKm: number | null | undefined,
  userUnit: 'mi' | 'km' = 'km',
): number | null => {
  if (valueInKm === null || valueInKm === undefined) return null;
  if (userUnit === 'mi') {
    return Math.round(valueInKm / 1.60934);
  }
  return valueInKm;
};

/**
 * Format mileage with unit, converting from km (DB) to user preference.
 */
export const formatMileage = (
  mileageInKm: number | null | undefined,
  unit: 'mi' | 'km' = 'km',
): string => {
  const converted = convertDistance(mileageInKm, unit);
  if (converted === null) return '-';
  return `${formatNumber(converted)} ${unit === 'mi' ? 'miles' : 'km'}`;
};

/**
 * Format volume (litres or gallons)
 */
export const formatVolume = (
  volume: number | null | undefined,
  unit: 'l' | 'gal' = 'l',
): string => {
  if (volume === null || volume === undefined) return '-';
  const unitLabel = unit === 'l' ? 'L' : 'gal';
  return `${volume.toFixed(2)} ${unitLabel}`;
};

/**
 * Format fuel efficiency (mpg or l/100km)
 */
export const formatFuelEfficiency = (
  mpg: number | null | undefined,
  displayUnit: 'mpg' | 'l100km' = 'mpg',
): string => {
  if (mpg === null || mpg === undefined) return '-';

  if (displayUnit === 'l100km') {
    // Convert mpg to l/100km
    // 1 mpg (UK) = 282.481 / mpg L/100km
    const l100km = 282.481 / mpg;
    return `${l100km.toFixed(1)} L/100km`;
  }

  return `${mpg.toFixed(1)} mpg`;
};

/**
 * Parse a date string and return ISO format
 */
export const parseDate = (dateString: string): string | null => {
  if (!dateString) return null;

  try {
    const date = new Date(dateString);
    if (isNaN(date.getTime())) return null;
    return date.toISOString().split('T')[0];
  } catch {
    return null;
  }
};

/**
 * Get the status color for a vehicle
 */
export const getStatusColor = (status: string | null | undefined): string => {
  switch (status?.toLowerCase()) {
    case 'live':
    case 'active':
      return '#4caf50'; // Green
    case 'sold':
      return '#ff9800'; // Orange
    case 'scrapped':
    case 'disposed':
      return '#f44336'; // Red
    case 'sorn':
      return '#9e9e9e'; // Grey
    default:
      return '#2196f3'; // Blue
  }
};

/**
 * Truncate text to a maximum length
 */
export const truncateText = (text: string, maxLength: number = 50): string => {
  if (!text || text.length <= maxLength) return text;
  return `${text.substring(0, maxLength - 3)}...`;
};

/**
 * Look up a vehicle display label from a vehicles array.
 * Returns name (preferred), registration, or 'Unknown'/'General'.
 */
export const getVehicleLabel = (
  vehicleId: number | null | undefined,
  vehicles: Array<{id: number; registration: string; name: string | null}>,
): string => {
  if (!vehicleId) return 'General';
  const vehicle = vehicles.find(v => v.id === vehicleId);
  return vehicle?.name || vehicle?.registration || 'Unknown';
};
