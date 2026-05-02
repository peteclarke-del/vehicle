// Compatibility re-export: tests import from '../context/AuthContext'
// but the real module lives in '../contexts/AuthContext'.
export { AuthContext, AuthProvider, useAuth } from '../contexts/AuthContext';
