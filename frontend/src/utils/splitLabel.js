import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';

/**
 * Splits a label into two parts for multi-line table headers
 * Useful for wrapping long labels like "Registration Number" into two lines
 * 
 * @param {string} labelText - The full label text to split
 * @param {string} fallbackFirst - Fallback for first line if splitting fails
 * @param {string} fallbackLast - Fallback for last line if splitting fails
 * @returns {Object} { first, last } - The two parts of the label
 * 
 * @example
 * const { first, last } = splitLabel('Registration Number', 'Registration', 'Number');
 * // Returns: { first: 'Registration', last: 'Number' }
 * 
 * const { first, last } = splitLabel('Mileage at Change', 'Mileage at', 'Change');
 * // Returns: { first: 'Mileage at', last: 'Change' }
 */
export const splitLabel = (labelText, fallbackFirst = '', fallbackLast = '') => {
  const words = (labelText || '').split(/\s+/).filter(Boolean);
  
  if (words.length === 0) {
    return { first: fallbackFirst, last: fallbackLast };
  }
  
  if (words.length === 1) {
    return { first: words[0], last: '' };
  }
  
  // Put the last word on the second line and everything else on the first
  const last = words[words.length - 1];
  const first = words.slice(0, words.length - 1).join(' ');
  
  return { first, last };
};

/**
 * React hook version that uses useTranslation for the label
 * @param {string} translationKey - The i18n translation key
 * @param {string} fallbackFirst - Fallback for first line
 * @param {string} fallbackLast - Fallback for last line
 * @returns {Object} { first, last }
 */
export const useSplitLabel = (translationKey, fallbackFirst = '', fallbackLast = '') => {
  const { t } = useTranslation();
  
  return useMemo(() => {
    const labelText = t(translationKey);
    return splitLabel(labelText, fallbackFirst, fallbackLast);
  }, [t, translationKey, fallbackFirst, fallbackLast]);
};

/**
 * Common registration number label splitter
 * @returns {Object} { regFirst, regLast }
 */
export const useRegistrationLabel = () => {
  const { first, last } = useSplitLabel('common.registrationNumber', 'Registration', 'Number');
  return { regFirst: first, regLast: last };
};

export default splitLabel;
