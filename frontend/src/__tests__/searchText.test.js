import { buildSearchText, matchesFreeText } from '../utils/searchText';

describe('searchText utility', () => {
  test('buildSearchText flattens nested objects and arrays', () => {
    const value = {
      id: 7,
      name: 'Oil Filter',
      vehicle: {
        registration: 'AB12 CDE',
      },
      tags: ['service', 'consumable'],
    };

    const text = buildSearchText('prefix', value, ['extra', null, undefined]);

    expect(text).toContain('prefix');
    expect(text).toContain('oil filter');
    expect(text).toContain('ab12 cde');
    expect(text).toContain('service');
    expect(text).toContain('extra');
  });

  test('matchesFreeText is true for empty needle', () => {
    expect(matchesFreeText('', { a: 1 })).toBe(true);
    expect(matchesFreeText('   ', { a: 1 })).toBe(true);
  });

  test('matchesFreeText finds case-insensitive text across values', () => {
    const row = {
      description: 'Bosch Oil Filter',
      category: 'Engine',
      supplier: 'Motor Factors',
    };

    expect(matchesFreeText('bosch', row)).toBe(true);
    expect(matchesFreeText('ENGINE', row)).toBe(true);
    expect(matchesFreeText('factors', row)).toBe(true);
    expect(matchesFreeText('not-present', row)).toBe(false);
  });
});
