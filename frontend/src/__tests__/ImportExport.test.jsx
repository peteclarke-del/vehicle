import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import ImportExport from '../pages/ImportExport';
import '@testing-library/jest-dom';

jest.mock('react-i18next', () => ({
  useTranslation: () => ({ t: (k) => k }),
}));

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

    render(<ImportExport />);

    // wait for initial vehicles fetch
    await waitFor(() => expect(global.fetch).toHaveBeenCalledWith('/vehicles', expect.any(Object)));

    const btnAll = screen.getByText('importExport.downloadAll');
    fireEvent.click(btnAll);

    await waitFor(() => {
      expect(global.fetch).toHaveBeenCalledTimes(2);
      expect(global.fetch.mock.calls[1][0]).toEqual(expect.stringContaining('/vehicles/export'));
      expect(global.fetch.mock.calls[1][0]).toEqual(expect.stringContaining('all=1'));
    });
  });

  test('Download Selected triggers filtered JSON export', async () => {
    global.fetch = jest.fn()
      // vehicles list
      .mockImplementationOnce(() => Promise.resolve({ ok: true, json: () => Promise.resolve([{ id: 1, name: 'V1' }]) }))
      // filtered export
      .mockImplementationOnce(() => Promise.resolve({ ok: true, json: () => Promise.resolve({}) }));

    render(<ImportExport />);

    await waitFor(() => expect(global.fetch).toHaveBeenCalledWith('/vehicles', expect.any(Object)));

    const btnJson = screen.getByText('importExport.downloadJson');
    fireEvent.click(btnJson);

    await waitFor(() => {
      expect(global.fetch).toHaveBeenCalledTimes(2);
      const calledUrl = global.fetch.mock.calls[1][0];
      expect(calledUrl).toEqual(expect.stringContaining('/vehicles/export'));
      expect(calledUrl).not.toEqual(expect.stringContaining('all=1'));
    });
  });

  test('Download Images ZIP calls export-zip endpoint', async () => {
    global.fetch = jest.fn()
      // vehicles list
      .mockImplementationOnce(() => Promise.resolve({ ok: true, json: () => Promise.resolve([{ id: 1, name: 'V1' }]) }))
      // images zip (blob)
      .mockImplementationOnce(() => Promise.resolve({ ok: true, blob: () => Promise.resolve(new Blob(['zip'])) }));

    render(<ImportExport />);

    await waitFor(() => expect(global.fetch).toHaveBeenCalledWith('/vehicles', expect.any(Object)));

    const btnZip = screen.getByText('importExport.downloadZip');
    fireEvent.click(btnZip);

    await waitFor(() => {
      expect(global.fetch).toHaveBeenCalledTimes(2);
      expect(global.fetch.mock.calls[1][0]).toEqual(expect.stringContaining('/vehicles/export-zip'));
    });
  });
});
