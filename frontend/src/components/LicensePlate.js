import React from 'react';
import { Box, Typography } from '@mui/material';
import { getCountryFromLocale } from '../utils/countryUtils';

/**
 * License Plate Component
 * Displays a registration number styled as a license plate
 * Supports UK, US, and EU styles
 */
const LicensePlate = ({ registrationNumber, country }) => {
  // Determine country from locale if not provided
  const plateCountry = country || getCountryFromLocale();
  
  // UK style plate (yellow background, black text)
  if (plateCountry === 'GB') {
    return (
      <Box
        sx={{
          display: 'inline-flex',
          alignItems: 'center',
          justifyContent: 'center',
          backgroundColor: '#FFD500',
          border: '2px solid #000',
          borderRadius: '4px',
          padding: '4px 12px',
          fontFamily: '"Charles Wright", monospace',
          fontWeight: 'bold',
          fontSize: '1.1rem',
          letterSpacing: '0.1em',
          color: '#000',
          boxShadow: '0 2px 4px rgba(0,0,0,0.2)',
        }}
      >
        {registrationNumber}
      </Box>
    );
  }
  
  // US style plate (varies by state, using generic white/blue)
  if (plateCountry === 'US') {
    return (
      <Box
        sx={{
          display: 'inline-flex',
          alignItems: 'center',
          justifyContent: 'center',
          backgroundColor: '#fff',
          border: '3px solid #003a70',
          borderRadius: '6px',
          padding: '6px 16px',
          fontFamily: 'monospace',
          fontWeight: 'bold',
          fontSize: '1.1rem',
          letterSpacing: '0.15em',
          color: '#003a70',
          boxShadow: '0 2px 4px rgba(0,0,0,0.2)',
        }}
      >
        {registrationNumber}
      </Box>
    );
  }
  
  // EU style plate (white background, black text, blue stripe)
  return (
    <Box
      sx={{
        display: 'inline-flex',
        alignItems: 'center',
        backgroundColor: '#fff',
        border: '2px solid #000',
        borderRadius: '4px',
        overflow: 'hidden',
        boxShadow: '0 2px 4px rgba(0,0,0,0.2)',
      }}
    >
      <Box
        sx={{
          backgroundColor: '#003399',
          padding: '6px 4px',
          display: 'flex',
          flexDirection: 'column',
          alignItems: 'center',
          justifyContent: 'center',
          minWidth: '20px',
        }}
      >
        <Typography sx={{ color: '#fff', fontSize: '0.6rem', fontWeight: 'bold' }}>
          {plateCountry}
        </Typography>
      </Box>
      <Box
        sx={{
          padding: '4px 12px',
          fontFamily: 'monospace',
          fontWeight: 'bold',
          fontSize: '1.1rem',
          letterSpacing: '0.1em',
          color: '#000',
        }}
      >
        {registrationNumber}
      </Box>
    </Box>
  );
};

export default LicensePlate;
