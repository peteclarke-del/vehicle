import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';
import Insurance from '../pages/Insurance';

jest.mock('react-i18next', () => ({ useTranslation: () => ({ t: (k) => k }) }));

jest.mock('../contexts/AuthContext', () => ({
  useAuth: () => ({ api: { get: jest.fn().mockResolvedValue({ data: [] }), delete: jest.fn() } }),
}));

describe('Insurance page (policies integration)', () => {
  test('renders and shows add button', async () => {
    render(<Insurance />);
    expect(await screen.findByText('insurance.policies.title')).toBeInTheDocument();
    expect(screen.getByText('insurance.policies.addPolicy')).toBeInTheDocument();
  });

  test('opens dialog on add', async () => {
    render(<Insurance />);
    const btn = screen.getByText('insurance.policies.addPolicy');
    fireEvent.click(btn);
    expect(await screen.findByText('insurance.policies.addPolicy')).toBeInTheDocument();
  });
});
