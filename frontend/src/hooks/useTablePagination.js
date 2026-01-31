import { useEffect, useMemo, useState } from 'react';

const useTablePagination = (rows = [], initialRowsPerPage = 10) => {
  const [page, setPage] = useState(0);
  const [rowsPerPage, setRowsPerPage] = useState(initialRowsPerPage);

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
    setRowsPerPage(parseInt(event.target.value, 10));
    setPage(0);
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
