/**
 * Safe wrapper for localStorage operations
 * Handles errors gracefully (private browsing mode, quota exceeded, etc.)
 */

const SafeStorage = {
  /**
   * Get item from localStorage
   * @param {string} key - Storage key
   * @param {*} defaultValue - Default value if key doesn't exist or error occurs
   * @returns {*} - Stored value or default value
   */
  get(key, defaultValue = null) {
    try {
      const item = localStorage.getItem(key);
      if (item === null) return defaultValue;
      
      // Try to parse JSON, fallback to raw string
      try {
        return JSON.parse(item);
      } catch {
        return item;
      }
    } catch (error) {
      console.warn(`SafeStorage.get("${key}") failed:`, error.message);
      return defaultValue;
    }
  },

  /**
   * Set item in localStorage
   * @param {string} key - Storage key
   * @param {*} value - Value to store (will be JSON stringified)
   * @returns {boolean} - True if successful, false otherwise
   */
  set(key, value) {
    try {
      const item = typeof value === 'string' ? value : JSON.stringify(value);
      localStorage.setItem(key, item);
      return true;
    } catch (error) {
      console.warn(`SafeStorage.set("${key}") failed:`, error.message);
      return false;
    }
  },

  /**
   * Remove item from localStorage
   * @param {string} key - Storage key
   * @returns {boolean} - True if successful, false otherwise
   */
  remove(key) {
    try {
      localStorage.removeItem(key);
      return true;
    } catch (error) {
      console.warn(`SafeStorage.remove("${key}") failed:`, error.message);
      return false;
    }
  },

  /**
   * Clear all items from localStorage
   * @returns {boolean} - True if successful, false otherwise
   */
  clear() {
    try {
      localStorage.clear();
      return true;
    } catch (error) {
      console.warn('SafeStorage.clear() failed:', error.message);
      return false;
    }
  },

  /**
   * Check if localStorage is available
   * @returns {boolean} - True if localStorage is available
   */
  isAvailable() {
    try {
      const testKey = '__storage_test__';
      localStorage.setItem(testKey, 'test');
      localStorage.removeItem(testKey);
      return true;
    } catch {
      return false;
    }
  }
};

export default SafeStorage;
