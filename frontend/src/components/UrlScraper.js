import React, { useState } from 'react';
import {
  Box,
  Typography,
  TextField,
  Button,
  CircularProgress
} from '@mui/material';
import { Search as SearchIcon } from '@mui/icons-material';
import { useTranslation } from 'react-i18next';
import { useAuth } from '../contexts/AuthContext';

export default function UrlScraper({ onDataScraped, endpoint }) {
  const { t } = useTranslation();
  const { api } = useAuth();
  const [url, setUrl] = useState('');
  const [scraping, setScraping] = useState(false);

  const handleScrape = async () => {
    if (!url) return;

    setScraping(true);
    try {
      const response = await api.post(endpoint, { url });
      onDataScraped(response.data, url);
      setUrl('');
    } catch (error) {
      console.error('URL scraping failed:', error);
      // Extract error message from response or use default
      const errorMessage = error.response?.data?.error || t('scraper.failed');
      alert(errorMessage);
    } finally {
      setScraping(false);
    }
  };

  return (
    <Box sx={{ mt: 2 }}>
      <Typography variant="subtitle2" gutterBottom>
        {t('scraper.productUrl')}
      </Typography>
      <Box sx={{ display: 'flex', gap: 1 }}>
        <TextField
          fullWidth
          size="small"
          value={url}
          onChange={(e) => setUrl(e.target.value)}
          placeholder={t('scraper.pasteUrl')}
          disabled={scraping}
        />
        <Button
          variant="outlined"
          onClick={handleScrape}
          disabled={!url || scraping}
          startIcon={scraping ? <CircularProgress size={20} /> : <SearchIcon />}
        >
          {t('scraper.scrape')}
        </Button>
      </Box>
      <Typography variant="caption" color="textSecondary" sx={{ mt: 0.5 }}>
        {t('scraper.helper')}
      </Typography>
    </Box>
  );
}
