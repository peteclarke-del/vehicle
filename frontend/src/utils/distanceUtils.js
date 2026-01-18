// Conversion factor: 1 mile = 1.60934 km
const KM_TO_MILES = 0.621371;
const MILES_TO_KM = 1.60934;

/**
 * Convert kilometers to miles
 * @param {number} km - Distance in kilometers
 * @returns {number} Distance in miles
 */
export const kmToMiles = (km) => {
  if (km === null || km === undefined) return null;
  return Math.round(km * KM_TO_MILES);
};

/**
 * Convert miles to kilometers
 * @param {number} miles - Distance in miles
 * @returns {number} Distance in kilometers
 */
export const milesToKm = (miles) => {
  if (miles === null || miles === undefined) return null;
  return Math.round(miles * MILES_TO_KM);
};

/**
 * Convert distance based on user's preferred unit
 * Data is stored in kilometers in the database
 * @param {number} distanceInKm - Distance value in kilometers (from database)
 * @param {string} userUnit - User's preferred distance unit ('km' or 'miles')
 * @returns {number} Converted distance
 */
export const convertDistance = (distanceInKm, userUnit) => {
  if (distanceInKm === null || distanceInKm === undefined) return null;
  
  if (userUnit === 'miles') {
    return kmToMiles(distanceInKm);
  }
  
  return distanceInKm;
};

/**
 * Convert distance from user unit back to kilometers for storage
 * @param {number} distance - Distance in user's unit
 * @param {string} userUnit - User's preferred distance unit ('km' or 'miles')
 * @returns {number} Distance in kilometers
 */
export const convertToKm = (distance, userUnit) => {
  if (distance === null || distance === undefined) return null;
  
  if (userUnit === 'miles') {
    return milesToKm(distance);
  }
  
  return distance;
};

/**
 * Format distance with appropriate unit label
 * @param {number} distance - Distance value (already converted)
 * @param {string} unit - Unit to display ('km' or 'miles')
 * @returns {string} Formatted distance with unit
 */
export const formatDistance = (distance, unit) => {
  if (distance === null || distance === undefined) return 'N/A';
  
  const unitLabel = unit === 'miles' ? 'mi' : 'km';
  return `${distance.toLocaleString()} ${unitLabel}`;
};

/**
 * Get the unit label
 * @param {string} unit - Unit type ('km' or 'miles')
 * @returns {string} Short unit label
 */
export const getUnitLabel = (unit) => {
  return unit === 'miles' ? 'mi' : 'km';
};
