import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';
import PolicyDialog from '../components/PolicyDialog';

jest.mock('react-i18next', () => ({ useTranslation: () => ({ t: (k) => k }) }));

jest.mock('../contexts/AuthContext', () => ({
  useAuth: () => ({ api: { post: jest.fn().mockResolvedValue({}), put: jest.fn().mockResolvedValue({}) } }),
}));

describe('PolicyDialog', () => {
  test('renders create mode', () => {
    render(<PolicyDialog open={true} policy={null} vehicles={[]} onClose={() => {}} />);
    expect(screen.getByText('insurance.policies.addPolicy')).toBeInTheDocument();
  });

  test('renders edit mode', () => {
    const p = { id: 1, provider: 'A', vehicles: [] };
    render(<PolicyDialog open={true} policy={p} vehicles={[]} onClose={() => {}} />);
    expect(screen.getByText('insurance.policies.editPolicy')).toBeInTheDocument();
  });
});
