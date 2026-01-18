/**
 * Get the user's country code from browser locale
 * @returns {string} Two-letter country code (e.g., 'GB', 'US', 'FR')
 */
export const getCountryFromLocale = () => {
  try {
    // Try to get country from navigator.language (e.g., 'en-GB', 'fr-FR')
    const locale = navigator.language || navigator.userLanguage;
    const parts = locale.split('-');
    
    if (parts.length > 1) {
      return parts[1].toUpperCase();
    }
    
    // Fallback to language code mapping
    const languageToCountry = {
      'en': 'GB', // Default English to UK
      'fr': 'FR',
      'de': 'DE',
      'es': 'ES',
      'it': 'IT',
      'pt': 'PT',
      'nl': 'NL',
      'pl': 'PL',
      'ru': 'RU',
      'ja': 'JP',
      'zh': 'CN',
      'ko': 'KR',
    };
    
    const lang = parts[0].toLowerCase();
    return languageToCountry[lang] || 'GB';
  } catch (e) {
    return 'GB'; // Default to UK
  }
};

/**
 * Determine the default distance unit based on country
 * Only UK, US, Myanmar (MM), and Liberia (LR) use miles
 * All other countries use kilometers
 * @param {string} countryCode - Two-letter country code
 * @returns {string} 'mi' or 'km'
 */
export const getDefaultDistanceUnit = (countryCode) => {
  const milesCountries = ['GB', 'US', 'MM', 'LR'];
  return milesCountries.includes(countryCode) ? 'mi' : 'km';
};

/**
 * Get the default distance unit for the current user based on their locale
 * @returns {string} 'mi' or 'km'
 */
export const getUserDefaultDistanceUnit = () => {
  const countryCode = getCountryFromLocale();
  return getDefaultDistanceUnit(countryCode);
};
