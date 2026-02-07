import React from 'react';
import { Box } from '@mui/material';
import KnightRiderLoader from './KnightRiderLoader';

/**
 * Centered loading spinner for full-page loading states
 * 
 * @param {Object} props
 * @param {number} props.size - Size of the loader (default: 32)
 * @param {string} props.minHeight - Minimum height of the container (default: '60vh')
 * @returns {JSX.Element}
 */
const CenteredLoader = ({ size = 32, minHeight = '60vh' }) => {
  return (
    <Box display="flex" justifyContent="center" alignItems="center" minHeight={minHeight}>
      <KnightRiderLoader size={size} />
    </Box>
  );
};

export default CenteredLoader;
