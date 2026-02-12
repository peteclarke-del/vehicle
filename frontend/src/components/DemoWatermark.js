import { useEffect } from 'react';
import DEMO_MODE from '../utils/demoMode';

/**
 * Injects a repeating "DEMO" watermark directly into document.body.
 *
 * This bypasses React's virtual DOM and all MUI stacking contexts by
 * creating a real DOM element appended to <body>.  The component
 * renders nothing itself (returns null).
 */
const DemoWatermark = () => {
  useEffect(() => {
    if (!DEMO_MODE) return;

    const el = document.createElement('div');
    el.id = 'demo-watermark';
    el.setAttribute('aria-hidden', 'true');

    // Container styles
    Object.assign(el.style, {
      position: 'fixed',
      top: '0',
      left: '0',
      width: '100vw',
      height: '100vh',
      zIndex: '2147483647',
      pointerEvents: 'none',
      overflow: 'hidden',
      display: 'flex',
      flexWrap: 'wrap',
      alignContent: 'center',
      justifyContent: 'center',
    });

    // Create tiled "DEMO" labels
    for (let i = 0; i < 24; i++) {
      const span = document.createElement('span');
      span.textContent = 'DEMO';
      Object.assign(span.style, {
        display: 'inline-block',
        transform: 'rotate(-35deg)',
        fontSize: '3rem',
        fontWeight: '900',
        color: '#b40000',
        opacity: '0.07',
        letterSpacing: '0.15em',
        whiteSpace: 'nowrap',
        userSelect: 'none',
        padding: '40px 60px',
      });
      el.appendChild(span);
    }

    document.body.appendChild(el);
    return () => el.remove();
  }, []);

  return null;
};

export default DemoWatermark;
