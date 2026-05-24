import React from 'react';
import { render, screen } from '@testing-library/react';
import TodoDialog from '../components/TodoDialog';

describe('TodoDialog accessibility', () => {
  it('renders all fields with accessible labels', () => {
    render(<TodoDialog open={true} onClose={() => {}} vehicleId={1} />);
    expect(screen.getByLabelText(/title/i)).toBeInTheDocument();
    expect(screen.getByLabelText(/description/i)).toBeInTheDocument();
    expect(screen.getByLabelText(/parts/i)).toBeInTheDocument();
    expect(screen.getByLabelText(/consumables/i)).toBeInTheDocument();
    expect(screen.getByLabelText(/due/i)).toBeInTheDocument();
    // In tests, i18n may render key names, so assert checkbox role rather than label text.
    expect(screen.getByRole('checkbox')).toBeInTheDocument();
  });

  it('has Save and Cancel buttons', () => {
    render(<TodoDialog open={true} onClose={() => {}} vehicleId={1} />);
    expect(screen.getByRole('button', { name: /save/i })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /cancel/i })).toBeInTheDocument();
  });
});
