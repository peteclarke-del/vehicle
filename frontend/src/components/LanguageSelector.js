import React, { useState, useEffect } from 'react';
import { MenuItem, TextField } from '@mui/material';
import { useTranslation } from 'react-i18next';
import { getAvailableLanguages } from '../i18n';

export default function LanguageSelector({ value, onChange, fullWidth = false, ...props }) {
  const { t } = useTranslation();
  const [languages, setLanguages] = useState([]);

  useEffect(() => {
    loadLanguages();
  }, []);

  const loadLanguages = async () => {
    try {
      const availableLanguages = await getAvailableLanguages();
      setLanguages(availableLanguages);
    } catch (error) {
      console.error('Error loading languages:', error);
      // Fallback to default languages
      setLanguages([
        { code: 'en', name: 'English', nativeName: 'English' },
        { code: 'es', name: 'Spanish', nativeName: 'Español' },
        { code: 'fr', name: 'French', nativeName: 'Français' }
      ]);
    }
  };

  return (
    <TextField
      select
      fullWidth={fullWidth}
      label={t('languages.selectLanguage') || 'Language'}
      value={value}
      onChange={onChange}
      {...props}
    >
      {languages.map((lang) => (
        <MenuItem key={lang.code} value={lang.code}>
          {lang.nativeName}
        </MenuItem>
      ))}
    </TextField>
  );
}
