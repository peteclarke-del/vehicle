import React from 'react';
import { Box } from '@mui/material';

const KnightRiderLoader = ({
  size = 24,
  length,
  sx,
  trackColor = '#2b0000',
  glowColor = '#ff2b2b',
}) => {
  const trackWidth = Math.max(60, length ?? size * 4);
  const trackHeight = Math.max(6, Math.round(size / 2));
  const dotWidth = Math.max(14, Math.round(trackWidth * 0.35));
  const dotHeight = Math.max(6, trackHeight - 2);
  const sweepDistance = trackWidth - dotWidth;

  return (
    <Box
      component="span"
      sx={{
        display: 'inline-flex',
        alignItems: 'center',
        justifyContent: 'center',
        ...sx,
      }}
    >
      <Box
        sx={{
          position: 'relative',
          width: trackWidth,
          height: trackHeight,
          borderRadius: 999,
          backgroundColor: trackColor,
          overflow: 'hidden',
          boxShadow: 'inset 0 0 6px rgba(0,0,0,0.4)',
        }}
      >
        <Box
          sx={{
            position: 'absolute',
            top: 1,
            left: 0,
            width: dotWidth,
            height: dotHeight,
            borderRadius: 999,
            backgroundColor: glowColor,
            boxShadow: '0 0 10px rgba(255,43,43,0.9)',
            animation: 'knightRiderSweep 1.4s ease-in-out infinite',
            '@keyframes knightRiderSweep': {
              '0%': { transform: 'translateX(0)' },
              '50%': { transform: `translateX(${sweepDistance}px)` },
              '100%': { transform: 'translateX(0)' },
            },
          }}
        />
      </Box>
    </Box>
  );
};

export default KnightRiderLoader;
