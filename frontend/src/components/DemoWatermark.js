import React from 'react';
import DEMO_MODE from '../utils/demoMode';

/**
 * Full-screen repeating "DEMO" watermark overlay.
 * Pure CSS — no SVG, no encoding, no stacking-context issues.
 */
const DemoWatermark = () => {
  if (!DEMO_MODE) return null;

  // Build a grid of rotated "DEMO" labels that tiles the viewport
  const cells = Array.from({ length: 20 }, (_, i) => (
    <span
      key={i}
      style={{
        display: 'inline-block',
        transform: 'rotate(-35deg)',
        fontSize: '3rem',
        fontWeight: 900,
        color: '#b40000',
        opacity: 0.07,
        letterSpacing: '0.15em',
        whiteSpace: 'nowrap',
        userSelect: 'none',
        padding: '40px 60px',
      }}
    >
      DEMO
    </span>
  ));

  return (
    <div
      style={{
        position: 'fixed',
        inset: 0,
        zIndex: 99999,
        pointerEvents: 'none',
        overflow: 'hidden',
        display: 'flex',
        flexWrap: 'wrap',
        alignContent: 'center',
        justifyContent: 'center',
      }}
    >
      {cells}
    </div>
  );
};

export default DemoWatermark;
