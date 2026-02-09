export interface LanguageConfig {
  code: string;
  name: string;
  nativeName: string;
  flag: string;
  defaultCurrency: string;
  defaultDistanceUnit: 'km' | 'mi';
  defaultVolumeUnit: 'l' | 'gal';
}

export const LANGUAGES: LanguageConfig[] = [
  {code: 'en', name: 'English', nativeName: 'English', flag: '🇬🇧', defaultCurrency: 'GBP', defaultDistanceUnit: 'mi', defaultVolumeUnit: 'l'},
  {code: 'ar', name: 'Arabic', nativeName: 'العربية', flag: '🇸🇦', defaultCurrency: 'SAR', defaultDistanceUnit: 'km', defaultVolumeUnit: 'l'},
  {code: 'cs', name: 'Czech', nativeName: 'Čeština', flag: '🇨🇿', defaultCurrency: 'CZK', defaultDistanceUnit: 'km', defaultVolumeUnit: 'l'},
  {code: 'da', name: 'Danish', nativeName: 'Dansk', flag: '🇩🇰', defaultCurrency: 'DKK', defaultDistanceUnit: 'km', defaultVolumeUnit: 'l'},
  {code: 'de', name: 'German', nativeName: 'Deutsch', flag: '🇩🇪', defaultCurrency: 'EUR', defaultDistanceUnit: 'km', defaultVolumeUnit: 'l'},
  {code: 'es', name: 'Spanish', nativeName: 'Español', flag: '🇪🇸', defaultCurrency: 'EUR', defaultDistanceUnit: 'km', defaultVolumeUnit: 'l'},
  {code: 'fi', name: 'Finnish', nativeName: 'Suomi', flag: '🇫🇮', defaultCurrency: 'EUR', defaultDistanceUnit: 'km', defaultVolumeUnit: 'l'},
  {code: 'fr', name: 'French', nativeName: 'Français', flag: '🇫🇷', defaultCurrency: 'EUR', defaultDistanceUnit: 'km', defaultVolumeUnit: 'l'},
  {code: 'hi', name: 'Hindi', nativeName: 'हिन्दी', flag: '🇮🇳', defaultCurrency: 'INR', defaultDistanceUnit: 'km', defaultVolumeUnit: 'l'},
  {code: 'it', name: 'Italian', nativeName: 'Italiano', flag: '🇮🇹', defaultCurrency: 'EUR', defaultDistanceUnit: 'km', defaultVolumeUnit: 'l'},
  {code: 'ja', name: 'Japanese', nativeName: '日本語', flag: '🇯🇵', defaultCurrency: 'JPY', defaultDistanceUnit: 'km', defaultVolumeUnit: 'l'},
  {code: 'ko', name: 'Korean', nativeName: '한국어', flag: '🇰🇷', defaultCurrency: 'KRW', defaultDistanceUnit: 'km', defaultVolumeUnit: 'l'},
  {code: 'nl', name: 'Dutch', nativeName: 'Nederlands', flag: '🇳🇱', defaultCurrency: 'EUR', defaultDistanceUnit: 'km', defaultVolumeUnit: 'l'},
  {code: 'no', name: 'Norwegian', nativeName: 'Norsk', flag: '🇳🇴', defaultCurrency: 'NOK', defaultDistanceUnit: 'km', defaultVolumeUnit: 'l'},
  {code: 'pl', name: 'Polish', nativeName: 'Polski', flag: '🇵🇱', defaultCurrency: 'PLN', defaultDistanceUnit: 'km', defaultVolumeUnit: 'l'},
  {code: 'pt', name: 'Portuguese', nativeName: 'Português', flag: '🇵🇹', defaultCurrency: 'EUR', defaultDistanceUnit: 'km', defaultVolumeUnit: 'l'},
  {code: 'ru', name: 'Russian', nativeName: 'Русский', flag: '🇷🇺', defaultCurrency: 'RUB', defaultDistanceUnit: 'km', defaultVolumeUnit: 'l'},
  {code: 'sv', name: 'Swedish', nativeName: 'Svenska', flag: '🇸🇪', defaultCurrency: 'SEK', defaultDistanceUnit: 'km', defaultVolumeUnit: 'l'},
  {code: 'tr', name: 'Turkish', nativeName: 'Türkçe', flag: '🇹🇷', defaultCurrency: 'TRY', defaultDistanceUnit: 'km', defaultVolumeUnit: 'l'},
  {code: 'zh', name: 'Chinese', nativeName: '简体中文', flag: '🇨🇳', defaultCurrency: 'CNY', defaultDistanceUnit: 'km', defaultVolumeUnit: 'l'},
];

export const getLanguageByCode = (code: string): LanguageConfig | undefined =>
  LANGUAGES.find(l => l.code === code);

export const getDefaultCurrency = (languageCode: string): string =>
  getLanguageByCode(languageCode)?.defaultCurrency ?? 'GBP';
