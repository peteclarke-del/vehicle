import React from 'react';
import { Container, Typography, Box } from '@mui/material';
import { useTranslation } from 'react-i18next';

const Reports = () => {
  const { t } = useTranslation();

  return (
    <Container maxWidth="xl">
      <Box display="flex" justifyContent="space-between" alignItems="center" mb={3}>
        <Typography variant="h4">{t('reports.title')}</Typography>
      </Box>
      {/* Blank reports page for future content */}
    </Container>
  );
};

export default Reports;
