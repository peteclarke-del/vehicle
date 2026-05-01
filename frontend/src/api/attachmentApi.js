import axios from 'axios';

// Matches the REACT_APP_API_URL pattern used in AuthContext (already includes /api).
// Append /attachments — not /api/attachments — to avoid a doubled /api segment.
const API_BASE = process.env.REACT_APP_API_URL || 'http://localhost:8081/api';

/**
 * Upload a file as an attachment.
 *
 * Pass the authenticated `api` axios instance (from `useAuth()`) as the third
 * argument so the request includes the Authorization header.  Falls back to a
 * bare axios call only when no instance is provided (e.g. in unit tests).
 *
 * @param {File} file
 * @param {object} [opts] - { category, description, entityType, entityId, vehicleId, onProgress }
 * @param {import('axios').AxiosInstance} [apiClient] - authenticated axios instance
 * @returns {Promise<object>}
 */
export async function uploadAttachment(file, opts, apiClient) {
  const onProgress = typeof opts === 'function' ? opts : opts?.onProgress;
  const { category, description, entityType, entityId, vehicleId } = opts ?? {};

  const formData = new FormData();
  formData.append('file', file);
  if (category)    formData.append('category', category);
  if (description) formData.append('description', description);
  if (entityType)  formData.append('entityType', entityType);
  if (entityId)    formData.append('entityId', entityId);
  if (vehicleId)   formData.append('vehicleId', vehicleId);

  const config = {
    headers: { 'Content-Type': 'multipart/form-data' },
    onUploadProgress: onProgress
      ? (evt) => { if (evt.total) onProgress(Math.round((evt.loaded * 100) / evt.total)); }
      : undefined,
  };

  const client = apiClient ?? axios;
  const url = apiClient ? '/attachments' : `${API_BASE}/attachments`;
  const response = await client.post(url, formData, config);
  return response.data;
}

/**
 * Delete an attachment by ID.
 * @param {number} id
 * @param {import('axios').AxiosInstance} [apiClient] - authenticated axios instance
 */
export async function deleteAttachment(id, apiClient) {
  const client = apiClient ?? axios;
  const url = apiClient ? `/attachments/${id}` : `${API_BASE}/attachments/${id}`;
  await client.delete(url);
}
