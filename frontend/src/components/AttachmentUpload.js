import React, { useRef, useState } from 'react';
import { uploadAttachment } from '../api/attachmentApi';
import { useAuth } from '../contexts/AuthContext';

const DEFAULT_MAX_SIZE = 10 * 1024 * 1024; // 10 MB

function formatBytes(bytes) {
  if (bytes == null) return '';
  if (bytes < 1024) return `${bytes} B`;
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(0)} KB`;
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

function formatDate(isoString) {
  if (!isoString) return '';
  const d = new Date(isoString);
  const day = String(d.getDate()).padStart(2, '0');
  const month = String(d.getMonth() + 1).padStart(2, '0');
  const year = d.getFullYear();
  return `${day}/${month}/${year}`;
}

function AttachmentUpload({
  // New-style callback API
  onUploadComplete,
  onError,
  onDelete,
  multiple = false,
  accept,
  maxSize = DEFAULT_MAX_SIZE,
  existingAttachments = [],
  showCategory = false,
  showDescription = false,
  // Backward-compatible props (original component API)
  entityType,
  entityId,
  vehicleId,
  compact: _compact = false,  // accepted for backward-compat; compact layout not yet implemented
  onChange,     // (attachments: array) => void — called after each upload
}) {
  const { api } = useAuth();
  const inputRef = useRef(null);
  // Ref mirrors uploadedResults synchronously so processFiles can always read the
  // accumulated list without relying on the stale closure captured at render time.
  const uploadAccRef = useRef([]);
  const [uploading, setUploading] = useState(false);
  const [uploadedResults, setUploadedResults] = useState([]);
  const [thumbnails, setThumbnails] = useState({});
  const [category, setCategory] = useState('');
  const [description, setDescription] = useState('');

  const isValidType = (file) => {
    if (!accept) return true;
    const accepted = accept.split(',').map((t) => t.trim());
    return accepted.some((type) => {
      if (type.startsWith('.')) return file.name.toLowerCase().endsWith(type.toLowerCase());
      // Handle wildcard patterns like image/*, audio/*
      if (type.endsWith('/*')) return file.type.startsWith(type.slice(0, -1));
      return file.type === type;
    });
  };

  const generateThumbnail = (file) =>
    new Promise((resolve) => {
      const reader = new FileReader();
      reader.onload = (e) => resolve(e.target.result);
      reader.readAsDataURL(file);
    });

  const processFiles = async (files) => {
    const fileList = Array.from(files);

    for (const file of fileList) {
      if (!isValidType(file)) {
        onError?.('Invalid file type');
        return;
      }
      if (file.size > maxSize) {
        onError?.(`File too large. Maximum size is ${formatBytes(maxSize)}`);
        return;
      }
    }

    setUploading(true);

    for (const file of fileList) {
      if (file.type.startsWith('image/')) {
        const url = await generateThumbnail(file);
        setThumbnails((prev) => ({ ...prev, [file.name]: url }));
      }

      try {
        const opts = {
          category: category || undefined,
          description: description || undefined,
          entityType: entityType || undefined,
          entityId: entityId || undefined,
          vehicleId: vehicleId || undefined,
        };
        const result = await uploadAttachment(file, opts, api);

        if (result?.virusScanStatus === 'infected') {
          onError?.('Virus detected in uploaded file');
          setUploading(false);
          return;
        }

        const newEntry = { ...result, _localName: file.name, _localType: file.type };
        // Accumulate synchronously via ref so the next loop iteration and onChange
        // both see the complete list, not the stale closure value.
        uploadAccRef.current = [...uploadAccRef.current, newEntry];
        setUploadedResults(uploadAccRef.current);
        onChange?.([...existingAttachments, ...uploadAccRef.current]);
        onUploadComplete?.(result);
      } catch (err) {
        onError?.(err?.message || 'Upload failed');
      }
    }

    setUploading(false);
  };

  // Await processFiles so the input is only cleared after all uploads finish,
  // and use finally so re-selecting the same file always triggers change events.
  const handleChange = async (e) => {
    const input = e.target;
    if (!input.files?.length) return;
    try {
      await processFiles(input.files);
    } finally {
      input.value = '';
    }
  };

  const allAttachments = [...existingAttachments, ...uploadedResults];

  return (
    <div>
      <p>Upload Attachment</p>

      {showCategory && (
        <div>
          <label htmlFor="attachment-category">Category</label>
          <select
            id="attachment-category"
            value={category}
            onChange={(e) => setCategory(e.target.value)}
          >
            <option value="">Select category</option>
            <option value="receipt">Receipt</option>
            <option value="certificate">Certificate</option>
            <option value="photo">Photo</option>
            <option value="other">Other</option>
          </select>
        </div>
      )}

      {showDescription && (
        <div>
          <label htmlFor="attachment-description">Description</label>
          <input
            id="attachment-description"
            type="text"
            value={description}
            onChange={(e) => setDescription(e.target.value)}
            placeholder="Add a description..."
          />
        </div>
      )}

      <input
        ref={inputRef}
        type="file"
        aria-label="choose file"
        accept={accept}
        multiple={multiple}
        style={{ position: 'absolute', opacity: 0, width: 0, height: 0 }}
        onChange={handleChange}
      />
      <button type="button" onClick={() => inputRef.current?.click()}>
        Choose File
      </button>

      {uploading && (
        <div role="progressbar" aria-label="uploading" aria-busy="true" />
      )}

      {allAttachments.map((att) => (
        <div key={att.id ?? att.filename}>
          <span>{att.filename}</span>
          {att.size != null && <span> ({formatBytes(att.size)})</span>}
          {att.uploadedAt && <span>{formatDate(att.uploadedAt)}</span>}
          {att.virusScanStatus && <span>Virus scan: {att.virusScanStatus}</span>}
          {att.url && (
            <a href={att.url} aria-label="download" download>
              Download
            </a>
          )}
          {onDelete && (
            <button type="button" aria-label="delete" onClick={() => onDelete(att.id)}>
              Delete
            </button>
          )}
          {thumbnails[att._localName] && (
            <img src={thumbnails[att._localName]} alt="thumbnail" style={{ maxWidth: 80 }} />
          )}
          {(att.filename?.endsWith('.pdf') || att._localType === 'application/pdf') &&
            !thumbnails[att._localName] && (
              <span data-testid="pdf-icon" aria-label="PDF">PDF</span>
            )}
        </div>
      ))}
    </div>
  );
}

export default AttachmentUpload;

