import axios from 'axios';

const API_BASE = process.env.REACT_APP_API_URL || '';

/**
 * Upload a file as an attachment.
 * @param {File} file
 * @param {object} [opts] - optional options: { category, description, onProgress }
 * @returns {Promise<{id: number, filename: string, url: string}>}
 */
export async function uploadAttachment(file, opts) {
  const onProgress = typeof opts === 'function' ? opts : opts?.onProgress;
  const category = opts?.category;
  const description = opts?.description;

  const formData = new FormData();
  formData.append('file', file);
  if (category) formData.append('category', category);
  if (description) formData.append('description', description);

  const response = await axios.post(`${API_BASE}/api/attachments`, formData, {
    headers: { 'Content-Type': 'multipart/form-data' },
    onUploadProgress: onProgress
      ? (evt) => {
          if (evt.total) {
            onProgress(Math.round((evt.loaded * 100) / evt.total));
          }
        }
      : undefined,
  });

  return response.data;
}

/**
 * Delete an attachment by ID.
 * @param {number} id
 */
export async function deleteAttachment(id) {
  await axios.delete(`${API_BASE}/api/attachments/${id}`);
}
