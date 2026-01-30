import React from 'react';
import { TablePagination } from '@mui/material';

const TablePaginationBar = ({
  count,
  page,
  rowsPerPage,
  onPageChange,
  onRowsPerPageChange,
  rowsPerPageOptions = [10, 25, 50, 100],
}) => (
  <TablePagination
    component="div"
    count={count}
    page={page}
    onPageChange={onPageChange}
    rowsPerPage={rowsPerPage}
    onRowsPerPageChange={onRowsPerPageChange}
    rowsPerPageOptions={rowsPerPageOptions}
    showFirstButton
    showLastButton
  />
);

export default TablePaginationBar;
