/**
 * Demo-mode detection and guard utilities.
 *
 * Detection (any one match activates demo mode):
 *  1. Build-time env var  REACT_APP_DEMO_MODE === 'true'
 *  2. Runtime hostname starting with 'demo.'
 *
 * The runtime fallback ensures demo features work even when Docker
 * build-arg propagation fails.
 */

/** True when the app is running as the public demo instance. */
const DEMO_MODE =
  process.env.REACT_APP_DEMO_MODE === 'true' ||
  (typeof window !== 'undefined' && window.location.hostname.startsWith('demo.'));

/**
 * Guard for destructive actions.  Call at the top of any handler that
 * should be blocked in demo mode.
 *
 * @param {Function} t - i18next `t` function (optional — falls back to English)
 * @returns {boolean}  true if action was blocked (caller should return early)
 *
 * Usage:
 *   const handleDelete = () => {
 *     if (demoGuard(t)) return;
 *     // … normal delete logic
 *   };
 */
export const demoGuard = (t) => {
  if (!DEMO_MODE) return false;
  const msg = t
    ? t('demo.actionBlocked', 'This action is disabled in the demo')
    : 'This action is disabled in the demo';
  window.alert(msg);
  return true;
};

export default DEMO_MODE;
