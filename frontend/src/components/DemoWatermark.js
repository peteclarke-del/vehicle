import React from 'react';
import { Box } from '@mui/material';
import DEMO_MODE from '../utils/demoMode';

const DemoWatermark = () => {
  if (!DEMO_MODE) return null;

  return (
    <Box
      sx={{
        position: 'fixed',
        top: 0,
        left: 0,
        width: '100%',
        height: '100%',
        pointerEvents: 'none',
        zIndex: 9999,
        overflow: 'hidden',
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
      }}
    >
      <Box
        sx={{
          transform: 'rotate(-35deg)',
          fontSize: { xs: '4rem', sm: '6rem', md: '8rem' },
          fontWeight: 900,
          color: 'rgba(128, 128, 128, 0.08)',
          letterSpacing: '0.15em',
          textTransform: 'uppercase',
          whiteSpace: 'nowrap',
          userSelect: 'none',
          lineHeight: 1,
        }}
      >
        DEMO
      </Box>
    </Box>
  );
};

export default DemoWatermark;
