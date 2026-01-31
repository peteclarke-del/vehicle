import { useState, useCallback } from 'react';

/**
 * Custom hook for handling drag and drop file uploads
 * Prevents browser from opening dropped files and provides visual feedback
 * 
 * @param {Function} onFilesDropped - Callback when files are dropped (receives File[])
 * @returns {Object} - { isDragging, dragHandlers }
 */
export const useDragDrop = (onFilesDropped) => {
  const [isDragging, setIsDragging] = useState(false);

  const handleDragEnter = useCallback((e) => {
    e.preventDefault();
    e.stopPropagation();
    setIsDragging(true);
  }, []);

  const handleDragLeave = useCallback((e) => {
    e.preventDefault();
    e.stopPropagation();
    // Only set to false if we're leaving the drop zone entirely
    // Check if the related target is not a child element
    if (!e.currentTarget.contains(e.relatedTarget)) {
      setIsDragging(false);
    }
  }, []);

  const handleDragOver = useCallback((e) => {
    e.preventDefault();
    e.stopPropagation();
    // Set the drop effect to copy
    e.dataTransfer.dropEffect = 'copy';
  }, []);

  const handleDrop = useCallback((e) => {
    e.preventDefault();
    e.stopPropagation();
    setIsDragging(false);

    const files = Array.from(e.dataTransfer.files || []);
    if (files.length > 0 && onFilesDropped) {
      onFilesDropped(files);
    }
  }, [onFilesDropped]);

  return {
    isDragging,
    dragHandlers: {
      onDragEnter: handleDragEnter,
      onDragLeave: handleDragLeave,
      onDragOver: handleDragOver,
      onDrop: handleDrop,
    },
  };
};

// Also prevent default drag/drop behavior on the entire document
// This can be called once in App.js or index.js
export const preventDefaultDragDrop = () => {
  const preventDefault = (e) => {
    e.preventDefault();
  };

  // Prevent default behavior for the entire window
  window.addEventListener('dragover', preventDefault, false);
  window.addEventListener('drop', preventDefault, false);

  // Return cleanup function
  return () => {
    window.removeEventListener('dragover', preventDefault);
    window.removeEventListener('drop', preventDefault);
  };
};
