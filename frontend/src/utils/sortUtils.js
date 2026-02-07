/**
 * Generic sort comparator factory for table sorting
 * Handles common data types: strings, numbers, dates, and custom fields
 * 
 * @param {string} order - Sort order ('asc' or 'desc')
 * @param {string} orderBy - Field to sort by
 * @param {Object} options - Configuration options
 * @param {Object} options.fieldConfig - Custom configuration per field
 * @param {Function} options.getRegistration - Function to get registration for a row (for vehicle lookups)
 * @returns {Function} Comparator function for Array.sort()
 * 
 * @example
 * const comparator = createSortComparator('asc', 'date', {
 *   fieldConfig: {
 *     date: { type: 'date' },
 *     cost: { type: 'number' },
 *     registration: { type: 'custom', getValue: (row) => getVehicleReg(row.vehicleId) }
 *   }
 * });
 * const sorted = [...data].sort(comparator);
 */
export const createSortComparator = (order, orderBy, options = {}) => {
  const { fieldConfig = {}, getRegistration } = options;
  
  return (a, b) => {
    const config = fieldConfig[orderBy] || {};
    let aValue, bValue;
    
    // Handle registration field specially (common pattern)
    if (orderBy === 'registration' && getRegistration) {
      aValue = getRegistration(a) || '';
      bValue = getRegistration(b) || '';
    } else if (config.getValue) {
      // Custom getValue function
      aValue = config.getValue(a);
      bValue = config.getValue(b);
    } else {
      // Default: get field directly
      aValue = a[orderBy];
      bValue = b[orderBy];
    }
    
    // Handle by type
    const type = config.type || 'string';
    
    switch (type) {
      case 'date':
        aValue = aValue ? new Date(aValue).getTime() : 0;
        bValue = bValue ? new Date(bValue).getTime() : 0;
        break;
      case 'number':
        aValue = parseFloat(aValue) || 0;
        bValue = parseFloat(bValue) || 0;
        break;
      case 'boolean':
        aValue = aValue ? 1 : 0;
        bValue = bValue ? 1 : 0;
        break;
      default:
        // String comparison - handle null/undefined
        aValue = aValue || '';
        bValue = bValue || '';
    }
    
    // Compare values
    if (aValue === bValue) return 0;
    
    const comparison = aValue < bValue ? -1 : 1;
    return order === 'asc' ? comparison : -comparison;
  };
};

/**
 * Common field configurations for reuse
 */
export const commonFieldConfigs = {
  date: { type: 'date' },
  cost: { type: 'number' },
  mileage: { type: 'number' },
  litres: { type: 'number' },
  price: { type: 'number' },
  quantity: { type: 'number' },
  done: { type: 'boolean' },
  sorn: { type: 'boolean' },
};

export default createSortComparator;
