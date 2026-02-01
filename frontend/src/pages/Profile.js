import React, { useState } from 'react';
import logger from '../utils/logger';
import {
  Box,
  Card,
  CardContent,
  TextField,
  Button,
  Typography,
  Grid,
  MenuItem,
  Alert,
  Divider,
} from '@mui/material';
import LockIcon from '@mui/icons-material/Lock';
import logger from '../utils/logger';
import { useAuth } from '../contexts/AuthContext';
import logger from '../utils/logger';
import { useTranslation } from 'react-i18next';
import logger from '../utils/logger';
import { useTheme } from '../contexts/ThemeContext';
import logger from '../utils/logger';
import PasswordChangeDialog from '../components/PasswordChangeDialog';
import logger from '../utils/logger';
import KnightRiderLoader from '../components/KnightRiderLoader';
import logger from '../utils/logger';

const Profile = () => {
  const { user, updateProfile } = useAuth();
  const { mode, toggleTheme } = useTheme();
  const { t } = useTranslation();
  const [formData, setFormData] = useState({
    firstName: user?.firstName || '',
    lastName: user?.lastName || '',
    theme: user?.theme || 'light',
  });
  const [loading, setLoading] = useState(false);
  const [success, setSuccess] = useState(false);
  const [showPasswordDialog, setShowPasswordDialog] = useState(false);

  const handleChange = (e) => {
    const { name, value } = e.target;
    setFormData({ ...formData, [name]: value });
    
    if (name === 'theme') {
      if ((value === 'dark' && mode === 'light') || (value === 'light' && mode === 'dark')) {
        toggleTheme();
      }
    }
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setLoading(true);
    setSuccess(false);
    try {
      await updateProfile(formData);
      setSuccess(true);
    } catch (error) {
      logger.error('Error updating profile:', error);
    } finally {
      setLoading(false);
    }
  };

  return (
    <Box>
      <Typography variant="h4" gutterBottom>
        {t('nav.profile')}
      </Typography>
      
      <Card sx={{ maxWidth: 600, mt: 3 }}>
        <CardContent>
          <Typography variant="h6" gutterBottom>
            {t('profile.userInformation')}
          </Typography>
          {success && (
            <Alert severity="success" sx={{ mb: 2 }}>
              Profile updated successfully!
            </Alert>
          )}
          <form onSubmit={handleSubmit}>
            <Grid container spacing={2}>
              <Grid item xs={12}>
                <TextField
                  fullWidth
                  disabled
                  label={t('auth.email')}
                  value={user?.email || ''}
                />
              </Grid>
              <Grid item xs={12} sm={6}>
                <TextField
                  fullWidth
                  required
                  name="firstName"
                  label={t('auth.firstName')}
                  value={formData.firstName}
                  onChange={handleChange}
                />
              </Grid>
              <Grid item xs={12} sm={6}>
                <TextField
                  fullWidth
                  required
                  name="lastName"
                  label={t('auth.lastName')}
                  value={formData.lastName}
                  onChange={handleChange}
                />
              </Grid>
              <Grid item xs={12}>
                <TextField
                  fullWidth
                  select
                  name="theme"
                  label={t('common.theme')}
                  value={formData.theme}
                  onChange={handleChange}
                >
                  <MenuItem value="light">{t('common.light')}</MenuItem>
                  <MenuItem value="dark">{t('common.dark')}</MenuItem>
                </TextField>
              </Grid>
              <Grid item xs={12}>
                <Button
                  type="submit"
                  variant="contained"
                  disabled={loading}
                  fullWidth
                >
                  {loading ? <KnightRiderLoader size={18} /> : t('profile.updateProfile')}
                </Button>
              </Grid>
            </Grid>
          </form>

          <Divider sx={{ my: 3 }} />

          <Typography variant="h6" gutterBottom>
            {t('profile.security')}
          </Typography>
          <Button
            variant="outlined"
            startIcon={<LockIcon />}
            onClick={() => setShowPasswordDialog(true)}
            fullWidth
          >
            {t('profile.changePassword')}
          </Button>
        </CardContent>
      </Card>

      <PasswordChangeDialog
        open={showPasswordDialog}
        onClose={() => setShowPasswordDialog(false)}
        required={false}
      />
    </Box>
  );
};

export default Profile;
