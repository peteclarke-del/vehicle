import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import ImportExport from '../pages/ImportExport';
import '@testing-library/jest-dom';

jest.mock('react-i18next', () => ({
  useTranslation: () => ({ t: (k) => k }),
}));

jest.mock('../contexts/VehiclesContext', () => ({
  useVehicles: () => ({ refreshVehicles: jest.fn() }),
}));

jest.mock('../components/ImportPreview', () => () => null);
jest.mock('../components/ImportHelpers', () => ({
  buildUrl: (apiBase, path, paramsObj = {}) => {
    const params = new URLSearchParams();
    Object.entries(paramsObj).forEach(([k, v]) => {
      if (v !== undefined && v !== null) params.set(k, Array.isArray(v) ? v.join(',') : v === true ? '1' : String(v));
    });
    return params.toString() ? `${apiBase}${path}?${params}` : `${apiBase}${path}`;
  },
  authHeaders: () => ({}),
}));
jest.mock('../components/DownloadHelpers', () => ({
  saveBlob: jest.fn(),
  downloadJsonObject: jest.fn(),
}));
jest.mock('../utils/demoMode', () => ({
  demoGuard: () => false,
}));

const renderWithRouter = (ui) =>
  render(<MemoryRouter>{ui}</MemoryRouter>);

describe('ImportExport page', () => {
  const originalCreateObjectURL = window.URL.createObjectURL;

  beforeAll(() => {
    window.URL.createObjectURL = jest.fn(() => 'blob:dummy');
  });

  afterAll(() => {
    window.URL.createObjectURL = originalCreateObjectURL;
  });

  beforeEach(() => {
    jest.clearAllMocks();
  });

  test('Download All triggers full JSON export endpoint', async () => {
    global.fetch = jest.fn()
      // vehicles list
      .mockImplementationOnce(() => Promise.resolve({ ok: true, json: () => Promise.resolve([{ id: 1, name: 'V1' }]) }))
      // export all
      .mockImplementationOnce(() => Promise.resolve({ ok: true, json: () => Promise.resolve({}) }));

    renderWithRouter(<ImportExport />);

    // wait for initial vehicles fetch
    await waitFor(() => expect(global.fetch).toHaveBeenCalledWith('/api/vehicles', expect.any(Object)));

    const btnAll = screen.getByText('importExport.downloadAll');
    fireEvent.click(btnAll);

    await waitFor(() => {
      expect(global.fetch).toHaveBeenCalledTimes(2);
      expect(global.fetch.mock.calls[1][0]).toEqual(expect.stringContaining('/api/vehicles/export'));
      expect(global.fetch.mock.calls[1][0]).toEqual(expect.stringContaining('all=1'));
    });
  });

  test('Download Selected triggers filtered JSON export', async () => {
    global.fetch = jest.fn()
      // vehicles list
      .mockImplementationOnce(() => Promise.resolve({ ok: true, json: () => Promise.resolve([{ id: 1, name: 'V1' }]) }))
      // filtered export
      .mockImplementationOnce(() => Promise.resolve({ ok: true, json: () => Promise.resolve({}) }));

    renderWithRouter(<ImportExport />);

    await waitFor(() => expect(global.fetch).toHaveBeenCalledWith('/api/vehicles', expect.any(Object)));

    const btnJson = screen.getByText('importExport.downloadJson');
    fireEvent.click(btnJson);

    await waitFor(() => {
      expect(global.fetch).toHaveBeenCalledTimes(2);
      const calledUrl = global.fetch.mock.calls[1][0];
      expect(calledUrl).toEqual(expect.stringContaining('/api/vehicles/export'));
      expect(calledUrl).not.toEqual(expect.stringContaining('all=1'));
    });
  });

  test('Download Images ZIP calls export-zip endpoint', async () => {
    global.fetch = jest.fn()
      // vehicles list
      .mockImplementationOnce(() => Promise.resolve({ ok: true, json: () => Promise.resolve([{ id: 1, name: 'V1' }]) }))
      // images zip (blob)
      .mockImplementationOnce(() => Promise.resolve({ ok: true, blob: () => Promise.resolve(new Blob(['zip'])) }));

    renderWithRouter(<ImportExport />);

    await waitFor(() => expect(global.fetch).toHaveBeenCalledWith('/api/vehicles', expect.any(Object)));

    const btnZip = screen.getByText('importExport.downloadZip');
    fireEvent.click(btnZip);

    await waitFor(() => {
      expect(global.fetch).toHaveBeenCalledTimes(2);
      expect(global.fetch.mock.calls[1][0]).toEqual(expect.stringContaining('/api/vehicles/export-zip'));
    });
  });

  test('ZIP import surfaces status and text for non-JSON error responses', async () => {
    const htmlBody = '<html><body>Request Entity Too Large</body></html>';
    global.fetch = jest.fn()
      // vehicles list
      .mockImplementationOnce(() => Promise.resolve({ ok: true, json: () => Promise.resolve([{ id: 1, name: 'V1' }]) }))
      // zip import fails with non-JSON body
      .mockImplementationOnce(() => Promise.resolve({
        ok: false,
        status: 413,
        statusText: 'Payload Too Large',
        clone: () => ({ json: () => Promise.reject(new Error('not-json')) }),
        text: () => Promise.resolve(htmlBody),
      }));

    const { container } = renderWithRouter(<ImportExport />);

    await waitFor(() => expect(global.fetch).toHaveBeenCalledWith('/api/vehicles', expect.any(Object)));

    const zipInput = container.querySelector('#import-zip-file-top');
    const file = new File(['zip-bytes'], 'vehicles.zip', { type: 'application/zip' });
    fireEvent.change(zipInput, { target: { files: [file] } });

    await waitFor(() => {
      expect(screen.getByText(/Zip import failed \(413 Payload Too Large\):/i)).toBeInTheDocument();
      expect(screen.getByText(/Request Entity Too Large/i)).toBeInTheDocument();
    });
  });
});
