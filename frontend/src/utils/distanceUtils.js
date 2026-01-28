// Constants
const KM_TO_MILES = 0.621371;
const MILES_TO_KM = 1.60934;

/**
 * Convert kilometers to miles
 * @param {number|null|undefined} km - Distance in kilometers
 * @param {boolean} round - Whether to round the result (default: true)
 * @param {number} decimals - Number of decimal places if rounding (default: 0)
 * @returns {number|null} Distance in miles or null if input is null/undefined
 */
export const kmToMiles = (km, round = true, decimals = 0) => {
  if (km === null || km === undefined) return null;
  if (typeof km !== 'number' || isNaN(km)) return null;
  
  const result = km * KM_TO_MILES;
  
  if (!round) return result;
  if (decimals === 0) return Math.round(result);
  
  const factor = Math.pow(10, decimals);
  return Math.round(result * factor) / factor;
};

/**
 * Convert miles to kilometers
 * @param {number|null|undefined} miles - Distance in miles
 * @param {boolean} round - Whether to round the result (default: true)
 * @param {number} decimals - Number of decimal places if rounding (default: 0)
 * @returns {number|null} Distance in kilometers or null if input is null/undefined
 */
export const milesToKm = (miles, round = true, decimals = 0) => {
  if (miles === null || miles === undefined) return null;
  if (typeof miles !== 'number' || isNaN(miles)) return null;
  
  const result = miles * MILES_TO_KM;
  
  if (!round) return result;
  if (decimals === 0) return Math.round(result);
  
  const factor = Math.pow(10, decimals);
  return Math.round(result * factor) / factor;
};

/**
 * Convert distance based on user's preferred unit
 * Data is stored in kilometers in the database
 * @param {number|null|undefined} distanceInKm - Distance value in kilometers (from database)
 * @param {string} userUnit - User's preferred distance unit ('km' or 'miles')
 * @param {boolean} round - Whether to round the result (default: true)
 * @param {number} decimals - Number of decimal places if rounding (default: 0)
 * @returns {number|null} Converted distance or null if input is null/undefined
 */
export const convertDistance = (distanceInKm, userUnit, round = true, decimals = 0) => {
  if (distanceInKm === null || distanceInKm === undefined) return null;
  if (typeof distanceInKm !== 'number' || isNaN(distanceInKm)) return null;
  
  if (userUnit === 'miles') {
    return kmToMiles(distanceInKm, round, decimals);
  }
  
  if (!round) return distanceInKm;
  if (decimals === 0) return Math.round(distanceInKm);
  
  const factor = Math.pow(10, decimals);
  return Math.round(distanceInKm * factor) / factor;
};

/**
 * Convert distance from user unit back to kilometers for storage
 * @param {number|null|undefined} distance - Distance in user's unit
 * @param {string} userUnit - User's preferred distance unit ('km' or 'miles')
 * @param {boolean} round - Whether to round the result (default: true)
 * @param {number} decimals - Number of decimal places if rounding (default: 0)
 * @returns {number|null} Distance in kilometers or null if input is null/undefined
 */
export const convertToKm = (distance, userUnit, round = true, decimals = 0) => {
  if (distance === null || distance === undefined) return null;
  if (typeof distance !== 'number' || isNaN(distance)) return null;
  
  if (userUnit === 'miles') {
    return milesToKm(distance, round, decimals);
  }
  
  if (!round) return distance;
  if (decimals === 0) return Math.round(distance);
  
  const factor = Math.pow(10, decimals);
  return Math.round(distance * factor) / factor;
};

/**
 * Format distance with appropriate unit label
 * @param {number|null|undefined} distance - Distance value (already converted to user's unit)
 * @param {string} unit - Unit to display ('km' or 'miles')
 * @param {number} decimals - Number of decimal places to display (default: 1)
 * @param {object} i18n - i18n instance for translation
 * @returns {string} Formatted distance with unit
 */
export const formatDistance = (distance, unit, decimals = 1, i18n = null) => {
  if (distance === null || distance === undefined || isNaN(distance)) {
    return i18n ? i18n.t('na') : 'N/A';
  }
  
  // Format the number with specified decimal places
  const formattedNumber = Number(distance).toLocaleString(undefined, {
    minimumFractionDigits: 0,
    maximumFractionDigits: decimals
  });
  
  const unitLabel = getUnitLabel(unit);
  return `${formattedNumber} ${unitLabel}`;
};

/**
 * Get the unit label
 * @param {string} unit - Unit type ('km' or 'miles')
 * @returns {string} Short unit label
 */
export const getUnitLabel = (unit) => {
  return unit === 'miles' ? 'mi' : 'km';
};

/**
 * Format distance with automatic unit conversion based on user preference
 * @param {number} distanceInKm - Distance in kilometers (from database)
 * @param {string} userUnit - User's preferred unit ('km' or 'miles')
 * @param {object} options - Formatting options
 * @param {number} options.decimals - Decimal places (default: 1)
 * @param {object} options.i18n - i18n instance for translation
 * @returns {string} Formatted distance with unit
 */
export const formatDistanceFromKm = (distanceInKm, userUnit, options = {}) => {
  const { decimals = 1, i18n = null } = options;
  
  const convertedDistance = convertDistance(distanceInKm, userUnit, true, decimals);
  return formatDistance(convertedDistance, userUnit, decimals, i18n);
};
