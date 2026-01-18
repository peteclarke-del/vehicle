import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import AttachmentUpload from '../components/AttachmentUpload';

jest.mock('../api/attachmentApi');
const { uploadAttachment } = require('../api/attachmentApi');

describe('AttachmentUpload', () => {
  const mockOnUploadComplete = jest.fn();
  const mockOnError = jest.fn();

  beforeEach(() => {
    jest.clearAllMocks();
    uploadAttachment.mockResolvedValue({
      id: 1,
      filename: 'receipt.pdf',
      url: 'https://example.com/uploads/receipt.pdf'
    });
  });

  test('renders attachment upload component', () => {
    render(<AttachmentUpload onUploadComplete={mockOnUploadComplete} />);

    expect(screen.getByText(/upload attachment/i)).toBeInTheDocument();
  });

  test('uploads file and calls onUploadComplete', async () => {
    render(<AttachmentUpload onUploadComplete={mockOnUploadComplete} />);

    const file = new File(['content'], 'receipt.pdf', { type: 'application/pdf' });
    const input = screen.getByLabelText(/choose file/i);
    
    fireEvent.change(input, { target: { files: [file] } });

    await waitFor(() => {
      expect(uploadAttachment).toHaveBeenCalledWith(file);
      expect(mockOnUploadComplete).toHaveBeenCalledWith({
        id: 1,
        filename: 'receipt.pdf',
        url: 'https://example.com/uploads/receipt.pdf'
      });
    });
  });

  test('displays upload progress', async () => {
    render(<AttachmentUpload onUploadComplete={mockOnUploadComplete} />);

    const file = new File(['content'], 'receipt.pdf', { type: 'application/pdf' });
    const input = screen.getByLabelText(/choose file/i);
    
    fireEvent.change(input, { target: { files: [file] } });

    await waitFor(() => {
      expect(screen.getByRole('progressbar')).toBeInTheDocument();
    });
  });

  test('handles upload error', async () => {
    uploadAttachment.mockRejectedValue(new Error('Upload failed'));

    render(<AttachmentUpload onUploadComplete={mockOnUploadComplete} onError={mockOnError} />);

    const file = new File(['content'], 'receipt.pdf', { type: 'application/pdf' });
    const input = screen.getByLabelText(/choose file/i);
    
    fireEvent.change(input, { target: { files: [file] } });

    await waitFor(() => {
      expect(mockOnError).toHaveBeenCalledWith('Upload failed');
    });
  });

  test('generates thumbnail for images', async () => {
    render(<AttachmentUpload onUploadComplete={mockOnUploadComplete} />);

    const file = new File(['image'], 'photo.jpg', { type: 'image/jpeg' });
    const input = screen.getByLabelText(/choose file/i);
    
    fireEvent.change(input, { target: { files: [file] } });

    await waitFor(() => {
      expect(screen.getByAltText(/thumbnail/i)).toBeInTheDocument();
    });
  });

  test('displays PDF icon for PDF files', async () => {
    render(<AttachmentUpload onUploadComplete={mockOnUploadComplete} />);

    const file = new File(['content'], 'document.pdf', { type: 'application/pdf' });
    const input = screen.getByLabelText(/choose file/i);
    
    fireEvent.change(input, { target: { files: [file] } });

    await waitFor(() => {
      expect(screen.getByTestId('pdf-icon')).toBeInTheDocument();
    });
  });

  test('allows multiple file uploads', async () => {
    render(<AttachmentUpload multiple onUploadComplete={mockOnUploadComplete} />);

    const files = [
      new File(['content1'], 'receipt1.pdf', { type: 'application/pdf' }),
      new File(['content2'], 'receipt2.pdf', { type: 'application/pdf' })
    ];
    
    const input = screen.getByLabelText(/choose file/i);
    fireEvent.change(input, { target: { files } });

    await waitFor(() => {
      expect(uploadAttachment).toHaveBeenCalledTimes(2);
    });
  });

  test('displays list of uploaded files', async () => {
    render(<AttachmentUpload onUploadComplete={mockOnUploadComplete} />);

    const file = new File(['content'], 'receipt.pdf', { type: 'application/pdf' });
    const input = screen.getByLabelText(/choose file/i);
    
    fireEvent.change(input, { target: { files: [file] } });

    await waitFor(() => {
      expect(screen.getByText(/receipt.pdf/i)).toBeInTheDocument();
    });
  });

  test('allows deleting uploaded attachment', async () => {
    const mockOnDelete = jest.fn();
    
    render(
      <AttachmentUpload 
        onUploadComplete={mockOnUploadComplete} 
        onDelete={mockOnDelete}
        existingAttachments={[
          { id: 1, filename: 'receipt.pdf', url: 'https://example.com/receipt.pdf' }
        ]}
      />
    );

    const deleteButton = screen.getByRole('button', { name: /delete/i });
    fireEvent.click(deleteButton);

    expect(mockOnDelete).toHaveBeenCalledWith(1);
  });

  test('displays file size for each attachment', async () => {
    render(
      <AttachmentUpload 
        onUploadComplete={mockOnUploadComplete}
        existingAttachments={[
          { id: 1, filename: 'receipt.pdf', size: 102400 }
        ]}
      />
    );

    expect(screen.getByText(/100.*kb/i)).toBeInTheDocument();
  });

  test('allows downloading attachment', () => {
    render(
      <AttachmentUpload 
        onUploadComplete={mockOnUploadComplete}
        existingAttachments={[
          { id: 1, filename: 'receipt.pdf', url: 'https://example.com/receipt.pdf' }
        ]}
      />
    );

    const downloadLink = screen.getByRole('link', { name: /download/i });
    expect(downloadLink).toHaveAttribute('href', 'https://example.com/receipt.pdf');
  });

  test('validates file size before upload', async () => {
    render(<AttachmentUpload maxSize={1024} onUploadComplete={mockOnUploadComplete} onError={mockOnError} />);

    const largeFile = new File([new ArrayBuffer(2048)], 'large.pdf', { type: 'application/pdf' });
    const input = screen.getByLabelText(/choose file/i);
    
    fireEvent.change(input, { target: { files: [largeFile] } });

    await waitFor(() => {
      expect(mockOnError).toHaveBeenCalledWith(expect.stringMatching(/file too large/i));
      expect(uploadAttachment).not.toHaveBeenCalled();
    });
  });

  test('validates file type before upload', async () => {
    render(
      <AttachmentUpload 
        accept=".pdf,.jpg" 
        onUploadComplete={mockOnUploadComplete} 
        onError={mockOnError} 
      />
    );

    const file = new File(['content'], 'document.txt', { type: 'text/plain' });
    const input = screen.getByLabelText(/choose file/i);
    
    fireEvent.change(input, { target: { files: [file] } });

    await waitFor(() => {
      expect(mockOnError).toHaveBeenCalledWith('Invalid file type');
      expect(uploadAttachment).not.toHaveBeenCalled();
    });
  });

  test('displays category selector', () => {
    render(<AttachmentUpload onUploadComplete={mockOnUploadComplete} showCategory />);

    expect(screen.getByLabelText(/category/i)).toBeInTheDocument();
  });

  test('sends category with upload', async () => {
    render(<AttachmentUpload onUploadComplete={mockOnUploadComplete} showCategory />);

    const categorySelect = screen.getByLabelText(/category/i);
    fireEvent.change(categorySelect, { target: { value: 'receipt' } });

    const file = new File(['content'], 'receipt.pdf', { type: 'application/pdf' });
    const input = screen.getByLabelText(/choose file/i);
    
    fireEvent.change(input, { target: { files: [file] } });

    await waitFor(() => {
      expect(uploadAttachment).toHaveBeenCalledWith(file, { category: 'receipt' });
    });
  });

  test('allows adding description to attachment', () => {
    render(<AttachmentUpload onUploadComplete={mockOnUploadComplete} showDescription />);

    const descriptionField = screen.getByLabelText(/description/i);
    fireEvent.change(descriptionField, { target: { value: 'MOT certificate' } });

    expect(descriptionField.value).toBe('MOT certificate');
  });

  test('shows upload date for existing attachments', () => {
    render(
      <AttachmentUpload 
        onUploadComplete={mockOnUploadComplete}
        existingAttachments={[
          { id: 1, filename: 'receipt.pdf', uploadedAt: '2024-03-15T10:30:00Z' }
        ]}
      />
    );

    expect(screen.getByText(/15\/03\/2024/i)).toBeInTheDocument();
  });

  test('displays virus scan status', () => {
    render(
      <AttachmentUpload 
        onUploadComplete={mockOnUploadComplete}
        existingAttachments={[
          { id: 1, filename: 'receipt.pdf', virusScanStatus: 'clean' }
        ]}
      />
    );

    expect(screen.getByText(/virus scan: clean/i)).toBeInTheDocument();
  });

  test('prevents uploading infected files', async () => {
    uploadAttachment.mockResolvedValue({
      id: 1,
      filename: 'infected.pdf',
      virusScanStatus: 'infected'
    });

    render(<AttachmentUpload onUploadComplete={mockOnUploadComplete} onError={mockOnError} />);

    const file = new File(['content'], 'infected.pdf', { type: 'application/pdf' });
    const input = screen.getByLabelText(/choose file/i);
    
    fireEvent.change(input, { target: { files: [file] } });

    await waitFor(() => {
      expect(mockOnError).toHaveBeenCalledWith(expect.stringMatching(/virus detected/i));
    });
  });
});
