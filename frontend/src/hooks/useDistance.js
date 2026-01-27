import { useAuth } from '../contexts/AuthContext';
import { convertDistance, convertToKm, formatDistance, getUnitLabel } from '../utils/distanceUtils';
import { getUserDefaultDistanceUnit } from '../utils/countryUtils';
import { useEffect, useState } from 'react';

/**
 * Custom hook to handle distance conversion based on user preferences
 * All distances are stored in kilometers in the database
 */
export const useDistance = () => {
  const { user, api } = useAuth();
  const [userUnit, setUserUnit] = useState('km');

  // Load distance unit preference from server; fall back to locale default
  useEffect(() => {
    let mounted = true;
    (async () => {
      try {
        if (api) {
          const resp = await api.get('/user/preferences?key=distanceUnit');
          const raw = resp?.data?.value;
          const unit = raw ? (raw === 'mi' ? 'miles' : 'km') : getUserDefaultDistanceUnit();
          if (mounted) setUserUnit(unit === 'miles' ? 'miles' : 'km');
          return;
        }
      } catch (e) {
        // ignore and fall back
      }

      // fallback to locale-based default
      const fallback = getUserDefaultDistanceUnit();
      if (mounted) setUserUnit(fallback === 'mi' ? 'miles' : 'km');
    })();

    return () => { mounted = false; };
  }, [api, user]);

  /**
   * Convert a distance from kilometers (database) to user's preferred unit
   * @param {number} distanceInKm - Distance in kilometers from database
   * @returns {number} Distance in user's preferred unit
   */
  const convert = (distanceInKm) => {
    return convertDistance(distanceInKm, userUnit);
  };

  /**
   * Convert a distance from user's unit back to kilometers for saving
   * @param {number} distance - Distance in user's preferred unit
   * @returns {number} Distance in kilometers for database
   */
  const toKm = (distance) => {
    return convertToKm(distance, userUnit);
  };

  /**
   * Format a distance value with the appropriate unit label
   * @param {number} distance - Distance value (already converted to user's unit)
   * @returns {string} Formatted distance string (e.g., "12,345 km" or "7,500 mi")
   */
  const format = (distance) => {
    return formatDistance(distance, userUnit);
  };

  /**
   * Get the short label for the current unit
   * @returns {string} Unit label ('km' or 'mi')
   */
  const getLabel = () => {
    return getUnitLabel(userUnit);
  };

  /**
   * Convert fuel consumption from L/100km to MPG or keep as L/100km
   * @param {number} litersPer100km - Fuel consumption in L/100km from database
   * @returns {number} Fuel consumption in appropriate unit
   */
  const convertFuelConsumption = (litersPer100km) => {
    if (userUnit === 'miles') {
      // Convert L/100km to MPG (US)
      // MPG = 235.214 / (L/100km)
      return 235.214 / litersPer100km;
    }
    return litersPer100km; // Keep as L/100km
  };

  /**
   * Get fuel consumption unit label
   * @returns {string} 'MPG' or 'L/100km'
   */
  const getFuelConsumptionLabel = () => {
    return userUnit === 'miles' ? 'MPG' : 'L/100km';
  };

  return {
    userUnit,
    convert,
    toKm,
    format,
    getLabel,
    convertFuelConsumption,
    getFuelConsumptionLabel,
  };
};
