import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';
import FullImportDialog from '../components/FullImportDialog';

jest.mock('react-i18next', () => ({
  useTranslation: () => ({ t: () => '' }),
}));

const setup = () => {
  const onConfirm = jest.fn();
  render(<FullImportDialog open fileName="backup.zip" onClose={jest.fn()} onConfirm={onConfirm} />);
  return onConfirm;
};

describe('FullImportDialog', () => {
  test('shows the filename and defaults to no wipe', () => {
    const onConfirm = setup();
    expect(screen.getByText('File: backup.zip')).toBeInTheDocument();
    fireEvent.click(screen.getByRole('button', { name: 'Start full import' }));
    expect(onConfirm).toHaveBeenCalledWith({ wipeData: false, deleteFiles: false });
  });

  test('delete files is only available with wipe data', () => {
    const onConfirm = setup();
    const deleteFiles = screen.getByRole('checkbox', { name: /delete uploaded images/i });
    expect(deleteFiles).toBeDisabled();

    fireEvent.click(screen.getByRole('checkbox', { name: /wipe my data/i }));
    expect(deleteFiles).toBeEnabled();
    fireEvent.click(deleteFiles);
    expect(screen.getByText(/removed from disk/i)).toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: 'Start full import' }));
    expect(onConfirm).toHaveBeenCalledWith({ wipeData: true, deleteFiles: true });
  });

  test('unticking wipe data also clears delete files', () => {
    const onConfirm = setup();
    const wipe = screen.getByRole('checkbox', { name: /wipe my data/i });
    fireEvent.click(wipe);
    fireEvent.click(screen.getByRole('checkbox', { name: /delete uploaded images/i }));
    fireEvent.click(wipe);
    fireEvent.click(screen.getByRole('button', { name: 'Start full import' }));
    expect(onConfirm).toHaveBeenCalledWith({ wipeData: false, deleteFiles: false });
  });
});
