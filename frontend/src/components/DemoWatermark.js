import React from 'react';
import DEMO_MODE from '../utils/demoMode';

/**
 * Full-screen repeating "DEMO" watermark overlay.
 * Uses an inline SVG as a CSS background-image so it tiles across
 * the entire viewport — no stacking-context issues with MUI.
 */
const DemoWatermark = () => {
  if (!DEMO_MODE) return null;

  // Inline SVG tile: rotated "DEMO" text, repeated via background-repeat
  const svg = `<svg xmlns='http://www.w3.org/2000/svg' width='320' height='240'>
    <text x='50%' y='50%' dominant-baseline='middle' text-anchor='middle'
          font-size='48' font-weight='900' fill='rgba(180,0,0,0.07)'
          letter-spacing='6' transform='rotate(-35,160,120)'>DEMO</text>
  </svg>`;

  const encoded = `data:image/svg+xml,${encodeURIComponent(svg)}`;

  return (
    <div
      style={{
        position: 'fixed',
        inset: 0,
        zIndex: 99999,
        pointerEvents: 'none',
        backgroundImage: `url("${encoded}")`,
        backgroundRepeat: 'repeat',
        backgroundSize: '320px 240px',
      }}
    />
  );
};

export default DemoWatermark;
