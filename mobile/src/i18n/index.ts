import i18n from 'i18next';
import {initReactI18next} from 'react-i18next';

import en from './locales/en.json';
import ar from './locales/ar.json';
import cs from './locales/cs.json';
import da from './locales/da.json';
import de from './locales/de.json';
import es from './locales/es.json';
import fi from './locales/fi.json';
import fr from './locales/fr.json';
import hi from './locales/hi.json';
import it from './locales/it.json';
import ja from './locales/ja.json';
import ko from './locales/ko.json';
import nl from './locales/nl.json';
import no from './locales/no.json';
import pl from './locales/pl.json';
import pt from './locales/pt.json';
import ru from './locales/ru.json';
import sv from './locales/sv.json';
import tr from './locales/tr.json';
import zh from './locales/zh.json';

const resources = {
  en: {translation: en},
  ar: {translation: ar},
  cs: {translation: cs},
  da: {translation: da},
  de: {translation: de},
  es: {translation: es},
  fi: {translation: fi},
  fr: {translation: fr},
  hi: {translation: hi},
  it: {translation: it},
  ja: {translation: ja},
  ko: {translation: ko},
  nl: {translation: nl},
  no: {translation: no},
  pl: {translation: pl},
  pt: {translation: pt},
  ru: {translation: ru},
  sv: {translation: sv},
  tr: {translation: tr},
  zh: {translation: zh},
};

i18n.use(initReactI18next).init({
  resources,
  lng: 'en',
  fallbackLng: 'en',
  interpolation: {
    escapeValue: false,
  },
  compatibilityJSON: 'v3',
});

export default i18n;
