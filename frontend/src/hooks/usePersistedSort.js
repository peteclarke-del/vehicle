import { useState, useCallback } from 'react';
import SafeStorage from '../utils/SafeStorage';

/**
 * Custom hook for managing table sorting with persistence to SafeStorage
 * @param {string} storageKey - Base key for storage (e.g., 'motRecords' -> 'motRecordsSortBy', 'motRecordsSortOrder')
 * @param {string} defaultField - Default field to sort by
 * @param {string} defaultOrder - Default sort order ('asc' or 'desc')
 * @returns {Object} { orderBy, order, handleRequestSort }
 */
const usePersistedSort = (storageKey, defaultField = 'date', defaultOrder = 'desc') => {
  const [orderBy, setOrderBy] = useState(() => SafeStorage.get(`${storageKey}SortBy`, defaultField));
  const [order, setOrder] = useState(() => SafeStorage.get(`${storageKey}SortOrder`, defaultOrder));

  const handleRequestSort = useCallback((property) => {
    const isAsc = orderBy === property && order === 'asc';
    const newOrder = isAsc ? 'desc' : 'asc';
    setOrder(newOrder);
    setOrderBy(property);
    SafeStorage.set(`${storageKey}SortBy`, property);
    SafeStorage.set(`${storageKey}SortOrder`, newOrder);
  }, [orderBy, order, storageKey]);

  return { orderBy, order, handleRequestSort };
};

export default usePersistedSort;
