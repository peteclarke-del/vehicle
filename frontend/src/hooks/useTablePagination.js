import { useEffect, useMemo, useState, useContext } from 'react';
import UserPreferencesContext from '../contexts/UserPreferencesContext';

const useTablePagination = (rows = [], initialRowsPerPage = null) => {
  // Use context directly to avoid throwing error when used outside provider
  const prefsCtx = useContext(UserPreferencesContext);
  const defaultRowsPerPage = prefsCtx?.defaultRowsPerPage ?? 10;
  const setDefaultRowsPerPage = prefsCtx?.setDefaultRowsPerPage;
  
  // Use initialRowsPerPage if explicitly provided, otherwise use the user preference
  const effectiveInitialRowsPerPage = initialRowsPerPage ?? defaultRowsPerPage ?? 10;
  
  const [page, setPage] = useState(0);
  const [rowsPerPage, setRowsPerPage] = useState(effectiveInitialRowsPerPage);

  // Sync rowsPerPage when defaultRowsPerPage changes (e.g., on initial load)
  useEffect(() => {
    if (initialRowsPerPage === null && defaultRowsPerPage) {
      setRowsPerPage(defaultRowsPerPage);
    }
  }, [defaultRowsPerPage, initialRowsPerPage]);

  useEffect(() => {
    if (page > 0 && page * rowsPerPage >= rows.length) {
      setPage(0);
    }
  }, [rows.length, page, rowsPerPage]);

  const paginatedRows = useMemo(() => {
    const start = page * rowsPerPage;
    const end = start + rowsPerPage;
    return rows.slice(start, end);
  }, [rows, page, rowsPerPage]);

  const handleChangePage = (_, newPage) => {
    setPage(newPage);
  };

  const handleChangeRowsPerPage = (event) => {
    const newValue = parseInt(event.target.value, 10);
    setRowsPerPage(newValue);
    setPage(0);
    
    // Also update the user preference when they change rows per page
    if (initialRowsPerPage === null && setDefaultRowsPerPage) {
      setDefaultRowsPerPage(newValue);
    }
  };

  return {
    page,
    rowsPerPage,
    paginatedRows,
    handleChangePage,
    handleChangeRowsPerPage,
    setPage,
    setRowsPerPage,
  };
};

export default useTablePagination;
