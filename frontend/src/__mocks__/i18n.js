// Stubbed i18n module for tests.
// The real i18n.js calls i18next.use(initReactI18next) which throws
// when react-i18next is mocked and initReactI18next becomes undefined.
const i18n = {
  use: () => i18n,
  init: () => Promise.resolve(),
  t: (key) => key,
  language: 'en',
  changeLanguage: () => Promise.resolve(),
};

export const getAvailableLanguages = async () => [
  { code: 'en', name: 'English', nativeName: 'English' },
];

export default i18n;
