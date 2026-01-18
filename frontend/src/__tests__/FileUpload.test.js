import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import FileUpload from '../components/FileUpload';

describe('FileUpload', () => {
  const mockOnUpload = jest.fn();
  const mockOnError = jest.fn();

  beforeEach(() => {
    jest.clearAllMocks();
  });

  test('renders file upload component', () => {
    render(<FileUpload onUpload={mockOnUpload} />);

    expect(screen.getByText(/choose file/i)).toBeInTheDocument();
  });

  test('displays upload zone with drag and drop', () => {
    render(<FileUpload onUpload={mockOnUpload} />);

    expect(screen.getByText(/drag.*drop/i)).toBeInTheDocument();
  });

  test('handles file selection', async () => {
    render(<FileUpload onUpload={mockOnUpload} />);

    const file = new File(['content'], 'document.pdf', { type: 'application/pdf' });
    const input = screen.getByLabelText(/choose file/i);
    
    fireEvent.change(input, { target: { files: [file] } });

    await waitFor(() => {
      expect(mockOnUpload).toHaveBeenCalledWith(file);
    });
  });

  test('validates file type', async () => {
    render(<FileUpload accept=".pdf,.jpg" onUpload={mockOnUpload} onError={mockOnError} />);

    const file = new File(['content'], 'document.txt', { type: 'text/plain' });
    const input = screen.getByLabelText(/choose file/i);
    
    fireEvent.change(input, { target: { files: [file] } });

    await waitFor(() => {
      expect(mockOnError).toHaveBeenCalledWith('Invalid file type');
    });
  });

  test('validates file size', async () => {
    render(<FileUpload maxSize={1024} onUpload={mockOnUpload} onError={mockOnError} />);

    const largeFile = new File([new ArrayBuffer(2048)], 'large.pdf', { type: 'application/pdf' });
    const input = screen.getByLabelText(/choose file/i);
    
    fireEvent.change(input, { target: { files: [largeFile] } });

    await waitFor(() => {
      expect(mockOnError).toHaveBeenCalledWith(expect.stringMatching(/file too large/i));
    });
  });

  test('displays file preview for images', async () => {
    render(<FileUpload onUpload={mockOnUpload} showPreview />);

    const file = new File(['image'], 'photo.jpg', { type: 'image/jpeg' });
    const input = screen.getByLabelText(/choose file/i);
    
    fireEvent.change(input, { target: { files: [file] } });

    await waitFor(() => {
      expect(screen.getByAltText(/preview/i)).toBeInTheDocument();
    });
  });

  test('shows upload progress', async () => {
    const { rerender } = render(<FileUpload onUpload={mockOnUpload} progress={0} />);

    expect(screen.queryByRole('progressbar')).not.toBeInTheDocument();

    rerender(<FileUpload onUpload={mockOnUpload} progress={50} />);
    
    expect(screen.getByRole('progressbar')).toBeInTheDocument();
    expect(screen.getByText(/50%/i)).toBeInTheDocument();
  });

  test('allows removing selected file', async () => {
    const mockOnRemove = jest.fn();
    render(<FileUpload onUpload={mockOnUpload} onRemove={mockOnRemove} />);

    const file = new File(['content'], 'document.pdf', { type: 'application/pdf' });
    const input = screen.getByLabelText(/choose file/i);
    
    fireEvent.change(input, { target: { files: [file] } });

    await waitFor(() => {
      expect(screen.getByText(/document.pdf/i)).toBeInTheDocument();
    });

    const removeButton = screen.getByRole('button', { name: /remove/i });
    fireEvent.click(removeButton);

    expect(mockOnRemove).toHaveBeenCalled();
  });

  test('handles drag and drop', async () => {
    render(<FileUpload onUpload={mockOnUpload} />);

    const file = new File(['content'], 'document.pdf', { type: 'application/pdf' });
    const dropZone = screen.getByText(/drag.*drop/i).closest('div');

    fireEvent.dragOver(dropZone);
    expect(dropZone).toHaveClass('drag-over');

    fireEvent.drop(dropZone, {
      dataTransfer: { files: [file] }
    });

    await waitFor(() => {
      expect(mockOnUpload).toHaveBeenCalledWith(file);
    });
  });

  test('displays file name after selection', async () => {
    render(<FileUpload onUpload={mockOnUpload} />);

    const file = new File(['content'], 'my-document.pdf', { type: 'application/pdf' });
    const input = screen.getByLabelText(/choose file/i);
    
    fireEvent.change(input, { target: { files: [file] } });

    await waitFor(() => {
      expect(screen.getByText(/my-document.pdf/i)).toBeInTheDocument();
    });
  });

  test('displays file size', async () => {
    render(<FileUpload onUpload={mockOnUpload} />);

    const file = new File([new ArrayBuffer(1024)], 'document.pdf', { type: 'application/pdf' });
    Object.defineProperty(file, 'size', { value: 1024 });
    
    const input = screen.getByLabelText(/choose file/i);
    fireEvent.change(input, { target: { files: [file] } });

    await waitFor(() => {
      expect(screen.getByText(/1(\.\d+)?\s*KB/i)).toBeInTheDocument();
    });
  });

  test('supports multiple file selection', async () => {
    render(<FileUpload multiple onUpload={mockOnUpload} />);

    const files = [
      new File(['content1'], 'doc1.pdf', { type: 'application/pdf' }),
      new File(['content2'], 'doc2.pdf', { type: 'application/pdf' })
    ];
    
    const input = screen.getByLabelText(/choose file/i);
    fireEvent.change(input, { target: { files } });

    await waitFor(() => {
      expect(mockOnUpload).toHaveBeenCalledWith(files);
    });
  });

  test('disables upload when disabled prop is true', () => {
    render(<FileUpload disabled onUpload={mockOnUpload} />);

    const input = screen.getByLabelText(/choose file/i);
    expect(input).toBeDisabled();
  });

  test('displays custom label', () => {
    render(<FileUpload label="Upload Receipt" onUpload={mockOnUpload} />);

    expect(screen.getByText(/upload receipt/i)).toBeInTheDocument();
  });

  test('displays accepted file types', () => {
    render(<FileUpload accept=".pdf,.jpg,.png" onUpload={mockOnUpload} />);

    expect(screen.getByText(/pdf, jpg, png/i)).toBeInTheDocument();
  });

  test('displays maximum file size', () => {
    render(<FileUpload maxSize={10485760} onUpload={mockOnUpload} />);

    expect(screen.getByText(/max.*10.*mb/i)).toBeInTheDocument();
  });

  test('shows error message', () => {
    render(<FileUpload error="Upload failed" onUpload={mockOnUpload} />);

    expect(screen.getByText(/upload failed/i)).toBeInTheDocument();
  });

  test('handles upload button click', () => {
    render(<FileUpload onUpload={mockOnUpload} />);

    const button = screen.getByRole('button', { name: /choose file/i });
    expect(button).toBeInTheDocument();
  });

  test('resets file selection', async () => {
    const mockOnReset = jest.fn();
    render(<FileUpload onUpload={mockOnUpload} onReset={mockOnReset} />);

    const file = new File(['content'], 'document.pdf', { type: 'application/pdf' });
    const input = screen.getByLabelText(/choose file/i);
    
    fireEvent.change(input, { target: { files: [file] } });

    await waitFor(() => {
      expect(screen.getByText(/document.pdf/i)).toBeInTheDocument();
    });

    const resetButton = screen.getByRole('button', { name: /reset/i });
    fireEvent.click(resetButton);

    expect(mockOnReset).toHaveBeenCalled();
  });
});
