export const authHeaders = () => ({ Authorization: 'Bearer ' + localStorage.getItem('token') });

export const buildUrl = (apiBase, path, paramsObj = {}) => {
  const params = new URLSearchParams();
  Object.entries(paramsObj).forEach(([k, v]) => {
    if (v === undefined || v === null) return;
    if (Array.isArray(v)) {
      if (v.length) params.set(k, v.join(','));
    } else if (v === true) {
      params.set(k, '1');
    } else if (v === false) {
      // skip
    } else {
      params.set(k, String(v));
    }
  });
  return params.toString() ? `${apiBase}${path}?${params.toString()}` : `${apiBase}${path}`;
};

export default {
  authHeaders,
  buildUrl,
};
