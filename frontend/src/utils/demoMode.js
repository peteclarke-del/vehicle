/**
 * Detect whether the app is running in demo mode.
 *
 * Checks (in order):
 *  1. Build-time env var  REACT_APP_DEMO_MODE === 'true'
 *  2. Runtime hostname starting with 'demo.'
 *
 * The runtime fallback ensures the watermark and demo credentials
 * appear even when Docker build-arg propagation fails.
 */
const DEMO_MODE =
  process.env.REACT_APP_DEMO_MODE === 'true' ||
  (typeof window !== 'undefined' && window.location.hostname.startsWith('demo.'));

export default DEMO_MODE;
