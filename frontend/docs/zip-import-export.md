Overview

This document describes how to export vehicles + attachments as a ZIP from the backend and how to upload the ZIP to re-import.

Endpoints

- Export ZIP (download): GET /api/vehicles/export-zip
  - Returns: application/zip (attachment)
- Import ZIP (upload): POST /api/vehicles/import-zip
  - Form field: `file` (multipart/form-data)
  - Auth: Bearer token required

Frontend examples

1) Download ZIP (browser)

Use a link/button that navigates to the export endpoint. The browser will download the ZIP automatically.

Example (plain anchor):

<a href="/api/vehicles/export-zip" target="_blank" rel="noopener">Download vehicles ZIP</a>

Example (programmatic fetch -> save):

```js
// using fetch and creating a download link
async function downloadVehiclesZip() {
  const resp = await fetch(process.env.REACT_APP_API_URL + '/vehicles/export-zip', {
    credentials: 'include', // or add Authorization header
    headers: {
      'Authorization': 'Bearer ' + localStorage.getItem('token'),
    }
  });
  if (!resp.ok) throw new Error('Export failed');
  const blob = await resp.blob();
  const url = window.URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = 'vehicles-export.zip';
  document.body.appendChild(a);
  a.click();
  a.remove();
  window.URL.revokeObjectURL(url);
}
```

2) Upload ZIP to import (React example)

This example shows a simple file input + POST using `fetch`.

```jsx
import React, {useState} from 'react';

export default function ImportZip() {
  const [file, setFile] = useState(null);
  const [status, setStatus] = useState('');

  async function handleSubmit(e) {
    e.preventDefault();
    if (!file) return setStatus('Select a ZIP file first');

    const formData = new FormData();
    formData.append('file', file);

    try {
      setStatus('Uploading...');
      const resp = await fetch(process.env.REACT_APP_API_URL + '/vehicles/import-zip', {
        method: 'POST',
        headers: {
          'Authorization': 'Bearer ' + localStorage.getItem('token'),
        },
        body: formData,
      });
      const json = await resp.json();
      if (!resp.ok) throw new Error(json.error || 'Import failed');
      setStatus('Imported: ' + JSON.stringify(json));
    } catch (err) {
      setStatus('Error: ' + err.message);
    }
  }

  return (
    <form onSubmit={handleSubmit}>
      <input type="file" accept=".zip,application/zip" onChange={e => setFile(e.target.files?.[0] ?? null)} />
      <button type="submit">Upload ZIP</button>
      <div>{status}</div>
    </form>
  );
}
```

Notes and recommendations

- Authentication: use the same auth flow as your app (include `Authorization` header or cookies).
- File size: large ZIPs may hit server timeouts or PHP upload limits. Adjust `upload_max_filesize` and `post_max_size` and `max_execution_time` in PHP-FPM / PHP config if needed.
- Progress: show upload progress using `XMLHttpRequest` or `fetch` with `ReadableStream`/`onprogress` (XHR is simpler).
- Validation: the backend checks for `manifest.json` and `vehicles.json` inside the ZIP. Do not rename those files.
- Security: only allow authenticated users to import; the import process maps attachments to the importing user.

Troubleshooting

- If import returns errors, download and inspect the backend logs and ensure `uploads` directory is writable by the PHP process.
- If files are missing after import, check the ZIP manifest `manifest.json` for `manifestName` entries and verify they were included.

