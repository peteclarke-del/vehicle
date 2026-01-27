export function saveBlob(blob, filename) {
  try {
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename || 'download';
    document.body.appendChild(a);
    a.click();
    a.remove();
    window.URL.revokeObjectURL(url);
  } catch (err) {
    // Fallback: try using FileSaver-like behavior or log
    // Keep errors non-fatal for the UI caller
    // eslint-disable-next-line no-console
    console.error('saveBlob failed', err);
  }
}

export function downloadJsonObject(obj, filenamePrefix = 'export') {
  const blob = new Blob([JSON.stringify(obj, null, 2)], { type: 'application/json' });
  const filename = `${filenamePrefix}_${new Date().toISOString().split('T')[0]}.json`;
  saveBlob(blob, filename);
}

export default {
  saveBlob,
  downloadJsonObject,
};
