import i18n from 'i18next';
import { initReactI18next } from 'react-i18next';
import LanguageDetector from 'i18next-browser-languagedetector';
import HttpBackend from 'i18next-http-backend';

// Get available languages by checking for translation files
export const getAvailableLanguages = async () => {
  try {
    const resp = await fetch('/locales/manifest.json');
    if (!resp.ok) throw new Error('manifest not found');
    const json = await resp.json();
    return json.languages || [];
  } catch (e) {
    // Fallback to English if manifest isn't available
    return [
      { code: 'en', name: 'English', nativeName: 'English' },
    ];
  }
};

i18n
  .use(HttpBackend)
  .use(LanguageDetector)
  .use(initReactI18next)
  .init({
    fallbackLng: 'en',
    debug: false,
    
    // Map language variants to base languages
    load: 'languageOnly', // This strips region codes (en-GB -> en, es-ES -> es)
  
    interpolation: {
      escapeValue: false,
    },

    backend: {
      loadPath: '/locales/{{lng}}/translation.json',
    },

    detection: {
      order: ['localStorage', 'navigator'],
      caches: ['localStorage'],
    },

    react: {
      useSuspense: true,
    }
  });

export default i18n;
