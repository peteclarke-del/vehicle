import React, { useEffect, useState } from 'react';
import {
  Dialog,
  DialogTitle,
  DialogContent,
  DialogActions,
  Button,
  TextField,
  Grid,
  MenuItem,
  CircularProgress,
  IconButton,
  Checkbox,
  FormControlLabel,
  Autocomplete,
} from '@mui/material';
import SearchIcon from '@mui/icons-material/Search';
import { useAuth } from '../contexts/AuthContext';
import { useTranslation } from 'react-i18next';
import { fetchArrayData } from '../hooks/useApiData';
import { useDistance } from '../hooks/useDistance';

const VehicleDialog = ({ open, vehicle, onClose }) => {
  const { convert, toKm, getLabel } = useDistance();
  const initialForm = {
    name: '',
    make: '',
    model: '',
    year: new Date().getFullYear(),
    vin: '',
    registrationNumber: '',
    engineNumber: '',
    v5DocumentNumber: '',
    purchaseCost: '',
    purchaseDate: new Date().toISOString().split('T')[0],
    purchaseMileage: '',
    motExempt: false,
    roadTaxExempt: false,
    securityFeatures: [],
    vehicleColor: '',
    serviceIntervalMonths: 12,
    serviceIntervalMiles: 4000,
    vehicleTypeId: '',
    depreciationMethod: 'declining_balance',
    depreciationYears: 10,
    depreciationRate: 5,
  };

  const [formData, setFormData] = useState(initialForm);
  const [vehicleTypes, setVehicleTypes] = useState([]);
  const [makes, setMakes] = useState([]);
  const [models, setModels] = useState([]);
  const [loading, setLoading] = useState(false);
  const [lookingUpReg, setLookingUpReg] = useState(false);
  const { api } = useAuth();
  const { t } = useTranslation();

  // Generate year options once
  const yearOptions = React.useMemo(() => {
    const currentYear = new Date().getFullYear();
    return Array.from({ length: currentYear - 1969 }, (_, i) => currentYear - i);
  }, []);

  // Common vehicle colors
  const vehicleColors = [
    'Black',
    'White',
    'Silver',
    'Grey',
    'Red',
    'Blue',
    'Green',
    'Yellow',
    'Orange',
    'Brown',
    'Beige',
    'Gold',
    'Burgundy',
    'Purple',
    'Maroon',
    'Navy Blue',
    'Sky Blue',
    'Pearl White',
    'Metallic Grey',
    'Matte Black',
  ];

  const [extraColors, setExtraColors] = useState([]);

  const colorOptions = React.useMemo(() => {
    return [...vehicleColors, ...extraColors];
  }, [extraColors]);

  const capitalizeFirst = (s) => {
    if (!s) return s;
    const str = String(s).trim();
    return str.charAt(0).toUpperCase() + str.slice(1).toLowerCase();
  };

  // Map color values to translation keys
  const getColorTranslationKey = (color) => {
    const colorMap = {
      'Black': 'black',
      'White': 'white',
      'Silver': 'silver',
      'Grey': 'grey',
      'Red': 'red',
      'Blue': 'blue',
      'Green': 'green',
      'Yellow': 'yellow',
      'Orange': 'orange',
      'Brown': 'brown',
      'Beige': 'beige',
      'Gold': 'gold',
      'Burgundy': 'burgundy',
      'Purple': 'purple',
      'Maroon': 'maroon',
      'Navy Blue': 'navyBlue',
      'Sky Blue': 'skyBlue',
      'Pearl White': 'pearlWhite',
      'Metallic Grey': 'metallicGrey',
      'Matte Black': 'matteBlack',
    };
    return `colors.${colorMap[color] || color.toLowerCase()}`;
  };

  // Security features by vehicle type
  const securityFeaturesByType = {
    'Car': [
      'Alarm System',
      'Immobiliser',
      'Central Locking',
      'Steering Lock',
      'GPS Tracking',
      'Dash Cam',
      'Kill Switch',
      'Wheel Lock',
      'VIN Etching',
      'Deadlocks',
      'Anti-Theft Marking',
      'Thatcham Approved Alarm',
      'OBD Port Lock',
    ],
    'Motorcycle': [
      'Disc Lock',
      'Chain and Padlock',
      'U-Lock',
      'Alarm System',
      'GPS Tracking',
      'Immobiliser',
      'Ground Anchor',
      'Bike Cover',
      'Kill Switch',
      'Steering Lock',
      'Datatag/Security Marking',
      'Brake Lever Lock',
    ],
    'Van': [
      'Alarm System',
      'Immobiliser',
      'Central Locking',
      'Deadlocks',
      'Slam Locks',
      'Hook Locks',
      'GPS Tracking',
      'Steering Lock',
      'Dash Cam',
      'Load Area Alarm',
      'Security Cages',
      'Window Grills',
      'OBD Port Lock',
      'Catalytic Converter Lock',
    ],
    'Truck': [
      'Alarm System',
      'Immobiliser',
      'GPS Tracking',
      'Air Cuff Lock',
      'Kingpin Lock',
      'Glad Hand Lock',
      'Wheel Clamp',
      'Dash Cam',
      'Load Security',
      'Trailer Lock',
      'Fifth Wheel Lock',
      'OBD Port Lock',
    ],
  };

  // Map security feature values to translation keys
  const getSecurityFeatureTranslationKey = (feature) => {
    const featureMap = {
      'Alarm System': 'alarmSystem',
      'Immobiliser': 'immobiliser',
      'Central Locking': 'centralLocking',
      'Steering Lock': 'steeringLock',
      'GPS Tracking': 'gpsTracking',
      'Dash Cam': 'dashCam',
      'Kill Switch': 'killSwitch',
      'Wheel Lock': 'wheelLock',
      'VIN Etching': 'vinEtching',
      'Deadlocks': 'deadlocks',
      'Anti-Theft Marking': 'antiTheftMarking',
      'Thatcham Approved Alarm': 'thatchamApprovedAlarm',
      'OBD Port Lock': 'obdPortLock',
      'Disc Lock': 'discLock',
      'Chain and Padlock': 'chainAndPadlock',
      'U-Lock': 'uLock',
      'Ground Anchor': 'groundAnchor',
      'Bike Cover': 'bikeCover',
      'Datatag/Security Marking': 'securityMarking',
      'Brake Lever Lock': 'brakeLeverLock',
      'Slam Locks': 'slamLocks',
      'Hook Locks': 'hookLocks',
      'Load Area Alarm': 'loadAreaAlarm',
      'Security Cages': 'securityCages',
      'Window Grills': 'windowGrills',
      'Catalytic Converter Lock': 'catalyticConverterLock',
      'Air Cuff Lock': 'airCuffLock',
      'Kingpin Lock': 'kingpinLock',
      'Glad Hand Lock': 'gladHandLock',
      'Wheel Clamp': 'wheelClamp',
      'Load Security': 'loadSecurity',
      'Trailer Lock': 'trailerLock',
      'Fifth Wheel Lock': 'fifthWheelLock',
    };
    return `securityFeatureOptions.${featureMap[feature] || feature.toLowerCase()}`;
  };

  // Get available security features based on vehicle type
  const availableSecurityFeatures = React.useMemo(() => {
    const vehicleType = vehicleTypes.find(vt => vt.id === formData.vehicleTypeId);
    if (vehicleType && securityFeaturesByType[vehicleType.name]) {
      return securityFeaturesByType[vehicleType.name];
    }
    return [];
  }, [formData.vehicleTypeId, vehicleTypes]);

  useEffect(() => {
        if (open) {
      loadVehicleTypes();
      if (vehicle) {
        setFormData({
          ...vehicle,
          vehicleTypeId: vehicle.vehicleType?.id || '',
            purchaseMileage: vehicle.purchaseMileage ? convert(vehicle.purchaseMileage) : '',
          motExempt: vehicle.motExempt ?? vehicle.isMotExempt ?? false,
          roadTaxExempt: vehicle.roadTaxExempt ?? vehicle.isRoadTaxExempt ?? false,
          serviceIntervalMiles: vehicle.serviceIntervalMiles ? convert(vehicle.serviceIntervalMiles) : 4000,
          securityFeatures: typeof vehicle.securityFeatures === 'string' 
            ? vehicle.securityFeatures.split(',').map(s => s.trim()).filter(Boolean)
            : (Array.isArray(vehicle.securityFeatures) ? vehicle.securityFeatures : []),
        });
      } else {
        // Reset to defaults when no vehicle (add new)
        setFormData(initialForm);
      }
    }
  }, [open, vehicle]);

  // Load makes when vehicle type changes
  useEffect(() => {
    if (formData.vehicleTypeId) {
      loadMakes(formData.vehicleTypeId);
    } else {
      setMakes([]);
    }
  }, [formData.vehicleTypeId]);

  // Load models when make or year changes
  useEffect(() => {
    if (formData.make && formData.year) {
      const makeObj = makes.find(m => m.name === formData.make);
      if (makeObj) {
        loadModels(makeObj.id, formData.year);
      }
    } else {
      setModels([]);
    }
  }, [formData.make, formData.year, makes]);

  const loadVehicleTypes = async () => {
    const data = await fetchArrayData(api, '/vehicle-types');
    setVehicleTypes(data);
  };

  const loadMakes = async (vehicleTypeId) => {
    const data = await fetchArrayData(api, `/vehicle-makes?vehicleTypeId=${vehicleTypeId}`);
    setMakes(data);
  };

  const loadModels = async (makeId, year) => {
    const data = await fetchArrayData(api, `/vehicle-models?makeId=${makeId}&year=${year}`);
    setModels(data);
  };

  const handleChange = (e) => {
    setFormData({ ...formData, [e.target.name]: e.target.value });
  };

  const handleClose = (saved) => {
    setFormData(initialForm);
    onClose(saved);
  };

  const lookupRegistration = async (registration) => {
    if (!registration || registration.length < 3) {
      return;
    }

    setLookingUpReg(true);
    try {
      const response = await api.get(`/dvla/vehicle/${registration}`);
      const data = response.data;

      if (data && !data.error) {
        // Find or create make
        let makeId = null;
        if (data.make) {
          const existingMake = makes.find(m => m.name.toLowerCase() === data.make.toLowerCase());
          if (existingMake) {
            makeId = existingMake.id;
          } else {
            // Create new make (include current vehicleTypeId or pick a sensible default)
            try {
              const payload = { name: data.make };
              let selectedTypeId = formData.vehicleTypeId;
              if (!selectedTypeId && vehicleTypes && vehicleTypes.length > 0) {
                // Prefer a 'Car' type if present, otherwise use the first available type
                const carType = vehicleTypes.find(t => /car/i.test(t.name));
                selectedTypeId = (carType && carType.id) ? carType.id : vehicleTypes[0].id;
              }
              if (selectedTypeId) {
                payload.vehicleTypeId = selectedTypeId;
                // Ensure form reflects the selected type so subsequent flows use it
                setFormData(prev => ({ ...prev, vehicleTypeId: selectedTypeId }));
              }
              const newMake = await api.post('/vehicle-makes', payload);
              makeId = newMake.data.id;
              setMakes(prev => [...prev, newMake.data]);
            } catch (err) {
              console.error('Error creating make:', err);
            }
          }
        }

        // Update form with vehicle details (prefer DVLA fields when present)
        const rawColor = (data.primaryColour ?? data.colour) ?? formData.vehicleColor;
        const color = rawColor ? capitalizeFirst(rawColor) : rawColor;

        const updates = {
          vin: data.vin ?? formData.vin,
          make: data.make ? capitalizeFirst(data.make) : formData.make,
          model: data.model ? capitalizeFirst(data.model) : formData.model,
          year: data.yearOfManufacture ?? formData.year,
          vehicleColor: color ?? formData.vehicleColor,
          registrationNumber: data.registration ?? formData.registrationNumber,
          engineNumber: data.engineNumber ?? formData.engineNumber,
          v5DocumentNumber: data.v5DocumentNumber ?? formData.v5DocumentNumber,
        };

        // Set vehicleTypeId by matching DVLA vehicleType name to available vehicleTypes
        if (data.vehicleType && vehicleTypes && vehicleTypes.length > 0) {
          const match = vehicleTypes.find(t => t.name && data.vehicleType && t.name.toLowerCase() === data.vehicleType.toLowerCase());
          if (match) {
            updates.vehicleTypeId = match.id;
          }
        }

        // Road tax / MOT indicators
        if (typeof data.motExempt !== 'undefined') {
          updates.motExempt = !!data.motExempt;
        }
        if (typeof data.taxStatus !== 'undefined') {
          const ts = (data.taxStatus ?? '') .toString().toLowerCase();
          updates.roadTaxExempt = ts.includes('sorn') || ts.includes('untaxed') || ts.includes('exempt');
        }

        // Prefer DVLA-only data; do not invoke external APIs here
        setFormData(prev => ({ ...prev, ...updates }));

        // Ensure colour is in options (case-insensitive match)
        if (color) {
          const found = colorOptions.find(c => c.toLowerCase() === color.toLowerCase());
          if (!found) {
            setExtraColors(prev => Array.from(new Set([...prev, color])));
          }
        }

        // Load models if we have a make and year
        if (makeId && updates.year) {
          loadModels(makeId, updates.year);
        }
      }
    } catch (error) {
      const serverError = error?.response?.data?.error;
      const status = error?.response?.status;

      // DVLA busy - show a friendly message and allow manual entry
      if (status === 503 && serverError === 'dvla.lookup_busy') {
        try {
          alert(t('dvla.lookupBusy'));
        } catch (e) {
          alert('DVLA is busy; please try again later.');
        }
        return;
      }

      // Not found - allow manual entry without alarming the user
      if (status === 404) {
        console.info('DVLA returned 404; no record found for', registration);
        return;
      }

      if (serverError) {
        // If backend returned a translation key, show translated message
        try {
          alert(t(serverError));
        } catch (e) {
          alert(serverError);
        }
      } else {
        console.log('Could not lookup registration:', error.response?.data?.error || error.message);
        alert(t('vehicle.lookupFailed'));
      }
    } finally {
      setLookingUpReg(false);
    }
  };

    const lookupByVin = async (vin) => {
      // VIN-based lookup via DVLA is not supported by our backend proxy.
      // If a registration is present we can attempt a DVLA registration lookup,
      // otherwise skip auto-lookup and let the user fill remaining fields.
      if (!vin || vin.length < 6) return;
      if (formData.registrationNumber) {
        await lookupRegistration(formData.registrationNumber);
      } else {
        console.log('VIN lookup skipped: no registration present and DVLA VIN lookup unavailable');
      }
    };

  const handleRegistrationBlur = (e) => {
    const reg = e.target.value;
    if (reg && !vehicle) { // Only auto-lookup for new vehicles
      lookupRegistration(reg);
    }
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setLoading(true);
    try {
      // Convert securityFeatures array to comma-separated string for API
      // Convert distances back to km for storage
      const dataToSend = {
        ...formData,
        purchaseMileage: formData.purchaseMileage ? toKm(parseInt(formData.purchaseMileage)) : null,
        serviceIntervalMiles: formData.serviceIntervalMiles ? toKm(parseInt(formData.serviceIntervalMiles)) : null,
        securityFeatures: Array.isArray(formData.securityFeatures) 
          ? formData.securityFeatures.join(', ')
          : formData.securityFeatures,
      };
      // Do not send service/MOT/road-tax dates from the vehicle form — these
      // are computed from related records and should not be editable here.
      delete dataToSend.lastServiceDate;
      delete dataToSend.motExpiryDate;
      delete dataToSend.roadTaxExpiryDate;
      
      if (vehicle) {
        await api.put(`/vehicles/${vehicle.id}`, dataToSend);
      } else {
        await api.post('/vehicles', dataToSend);
      }
      handleClose(true);
    } catch (error) {
      console.error('Error saving vehicle:', error);
      alert(t('common.saveError', { type: 'vehicle' }));
    } finally {
      setLoading(false);
    }
  };

  return (
    <Dialog open={open} onClose={() => handleClose(false)} maxWidth="md" fullWidth>
      <DialogTitle>
        {vehicle ? t('vehicleDialog.editVehicle') : t('vehicle.addVehicle')}
      </DialogTitle>
      <form onSubmit={handleSubmit}>
        <DialogContent>
          <Grid container spacing={2}>
            <Grid item xs={12} sm={12}>
              <TextField
                fullWidth
                required
                name="name"
                label={t('vehicle.name')}
                value={formData.name}
                onChange={handleChange}
              />
            </Grid>
            <Grid item xs={12} sm={6}>
              <Autocomplete
                freeSolo
                options={vehicleTypes}
                getOptionLabel={(option) => typeof option === 'object' ? option.name : option}
                value={vehicleTypes.find(t => t.id === formData.vehicleTypeId) || null}
                onChange={(event, newValue) => {
                  if (newValue) {
                    setFormData({ 
                      ...formData, 
                      vehicleTypeId: typeof newValue === 'object' ? newValue.id : ''
                    });
                  } else {
                    setFormData({ ...formData, vehicleTypeId: '' });
                  }
                }}
                renderInput={(params) => (
                  <TextField
                    {...params}
                    label={t('vehicle.vehicleType')}
                    required
                    fullWidth
                  />
                )}
              />
            </Grid>
            <Grid item xs={12} sm={4}>
              <Autocomplete
                freeSolo
                options={makes.map(m => m.name)}
                value={formData.make}
                onChange={(event, newValue) => {
                  setFormData({ ...formData, make: newValue || '' });
                }}
                onInputChange={(event, newInputValue) => {
                  setFormData({ ...formData, make: newInputValue });
                }}
                renderInput={(params) => (
                  <TextField
                    {...params}
                    label={t('vehicle.make')}
                    fullWidth
                  />
                )}
              />
            </Grid>
            <Grid item xs={12} sm={4}>
              <TextField
                select
                fullWidth
                name="year"
                label={t('vehicle.year')}
                value={formData.year}
                onChange={handleChange}
                required
              >
                {yearOptions.map((year) => (
                  <MenuItem key={year} value={year}>
                    {year}
                  </MenuItem>
                ))}
              </TextField>
            </Grid>
            <Grid item xs={12} sm={4}>
              <Autocomplete
                freeSolo
                options={models.map(m => m.name)}
                value={formData.model}
                onChange={(event, newValue) => {
                  setFormData({ ...formData, model: newValue || '' });
                }}
                onInputChange={(event, newInputValue) => {
                  setFormData({ ...formData, model: newInputValue });
                }}
                renderInput={(params) => (
                  <TextField
                    {...params}
                    label={t('vehicle.model')}
                    fullWidth
                  />
                )}
              />
            </Grid>
            <Grid item xs={12} sm={6}>
              <TextField
                fullWidth
                name="vin"
                label={t('vehicle.vin')}
                value={formData.vin}
                onChange={handleChange}
                onBlur={(e) => { if (!vehicle) lookupByVin(e.target.value); }}
                InputProps={{
                  endAdornment: (
                    <>
                      {lookingUpReg ? <CircularProgress size={20} /> : null}
                      <IconButton size="small" onClick={() => lookupByVin(formData.vin)} aria-label="fetch-vin">
                        <SearchIcon fontSize="small" />
                      </IconButton>
                    </>
                  ),
                }}
              />
            </Grid>
            <Grid item xs={12} sm={6}>
              <TextField
                fullWidth
                name="registrationNumber"
                label={t('vehicle.registrationNumber')}
                value={formData.registrationNumber}
                onChange={handleChange}
                onBlur={handleRegistrationBlur}
                helperText={lookingUpReg ? t('vehicle.lookingUpRegistration') : t('vehicle.registrationHelper')}
                InputProps={{
                  endAdornment: (
                    <>
                      {lookingUpReg ? <CircularProgress size={20} /> : null}
                      <IconButton size="small" onClick={() => lookupRegistration(formData.registrationNumber)} aria-label="fetch-reg">
                        <SearchIcon fontSize="small" />
                      </IconButton>
                    </>
                  ),
                }}
              />
            </Grid>
            <Grid item xs={12} sm={6}>
              <TextField
                fullWidth
                name="engineNumber"
                label={t('vehicle.engineNumber')}
                value={formData.engineNumber}
                onChange={handleChange}
              />
            </Grid>
            <Grid item xs={12} sm={6}>
              <TextField
                fullWidth
                name="v5DocumentNumber"
                label={t('vehicle.v5DocumentNumber')}
                value={formData.v5DocumentNumber}
                onChange={handleChange}
              />
            </Grid>
            <Grid item xs={12} sm={6}>
              <TextField
                fullWidth
                required
                type="number"
                name="purchaseCost"
                label={t('vehicle.purchaseCost')}
                value={formData.purchaseCost}
                onChange={handleChange}
              />
            </Grid>
            <Grid item xs={12} sm={6}>
              <TextField
                fullWidth
                required
                type="date"
                name="purchaseDate"
                label={t('vehicle.purchaseDate')}
                value={formData.purchaseDate}
                onChange={handleChange}
                InputLabelProps={{ shrink: true }}
              />
            </Grid>
            <Grid item xs={12} sm={12}>
              <Grid container spacing={1} alignItems="center">
                <Grid item xs={12} sm={6}>
                  <TextField
                    fullWidth
                    type="number"
                    name="purchaseMileage"
                    label={`${t('vehicle.purchaseMileage')} (${getLabel()})`}
                    value={formData.purchaseMileage}
                    onChange={handleChange}
                  />
                </Grid>
                <Grid item xs={12} sm={3}>
                  <div style={{ width: '100%', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                    <FormControlLabel
                      control={
                        <Checkbox
                          checked={!!formData.motExempt}
                          onChange={(e) => setFormData({ ...formData, motExempt: e.target.checked })}
                          name="motExempt"
                        />
                      }
                      label={t('vehicle.motExempt')}
                    />
                  </div>
                </Grid>
                <Grid item xs={12} sm={3}>
                  <div style={{ width: '100%', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                    <FormControlLabel
                      control={
                        <Checkbox
                          checked={!!formData.roadTaxExempt}
                          onChange={(e) => setFormData({ ...formData, roadTaxExempt: e.target.checked })}
                          name="roadTaxExempt"
                        />
                      }
                      label={t('vehicle.roadTaxExempt')}
                    />
                  </div>
                </Grid>
              </Grid>
            </Grid>
            {/* currentMileage is computed from fuel records and is not editable here */}
            {/* Service / MOT / Road tax dates are derived from related records and are not editable here */}
            <Grid item xs={12}>
              <Autocomplete
                multiple
                fullWidth
                options={availableSecurityFeatures}
                value={formData.securityFeatures}
                onChange={(event, newValue) => {
                  setFormData({ ...formData, securityFeatures: newValue });
                }}
                disableCloseOnSelect
                getOptionLabel={(option) => t(getSecurityFeatureTranslationKey(option))}
                renderOption={(props, option, { selected }) => (
                  <li {...props}>
                    <input
                      type="checkbox"
                      checked={selected}
                      style={{ marginRight: 8 }}
                      readOnly
                    />
                    {t(getSecurityFeatureTranslationKey(option))}
                  </li>
                )}
                renderInput={(params) => (
                  <TextField
                    {...params}
                    label={t('vehicle.securityFeatures')}
                    placeholder={availableSecurityFeatures.length > 0 ? t('securityFeatures.selectFeatures') : t('securityFeatures.selectVehicleFirst')}
                    helperText={t('vehicle.securityFeaturesHelper')}
                  />
                )}
              />
            </Grid>
            <Grid item xs={12} sm={4}>
              <TextField
                select
                fullWidth
                name="vehicleColor"
                label={t('vehicle.vehicleColor')}
                value={formData.vehicleColor}
                onChange={handleChange}
                helperText={t('vehicle.vehicleColorHelper')}
              >
                {vehicleColors.map((color) => (
                  <MenuItem key={color} value={color}>
                    {t(getColorTranslationKey(color))}
                  </MenuItem>
                ))}
              </TextField>
            </Grid>
            <Grid item xs={12} sm={4}>
              <TextField
                fullWidth
                type="number"
                name="serviceIntervalMonths"
                label={t('vehicle.serviceIntervalMonths')}
                value={formData.serviceIntervalMonths}
                onChange={handleChange}
                inputProps={{ min: 1 }}
                helperText={t('vehicle.serviceIntervalMonthsHelper')}
              />
            </Grid>
            <Grid item xs={12} sm={4}>
              <TextField
                fullWidth
                type="number"
                name="serviceIntervalMiles"
                label={`${t('vehicle.serviceIntervalMiles')} (${getLabel()})`}
                value={formData.serviceIntervalMiles}
                onChange={handleChange}
                inputProps={{ min: 100 }}
                helperText={t('vehicle.serviceIntervalMilesHelper')}
              />
            </Grid>
            
            <Grid item xs={12} sm={4}>
              <TextField
                fullWidth
                select
                name="depreciationMethod"
                label={t('vehicle.depreciationMethod')}
                value={formData.depreciationMethod}
                onChange={handleChange}
              >
                <MenuItem value="straight_line">{t('depreciation.straightLine')}</MenuItem>
                <MenuItem value="declining_balance">{t('depreciation.decliningBalance')}</MenuItem>
                  <MenuItem value="doubleDeclining">{t('depreciation.doubleDeclining')}</MenuItem>
              </TextField>
            </Grid>
            <Grid item xs={12} sm={4}>
              <TextField
                fullWidth
                type="number"
                name="depreciationYears"
                label={t('vehicle.depreciationYears')}
                value={formData.depreciationYears}
                onChange={handleChange}
              />
            </Grid>
            <Grid item xs={12} sm={4}>
              <TextField
                fullWidth
                type="number"
                name="depreciationRate"
                label={t('vehicle.depreciationRate')}
                value={formData.depreciationRate}
                onChange={handleChange}
              />
            </Grid>
          </Grid>
        </DialogContent>
        <DialogActions>
          <Button onClick={() => handleClose(false)}>{t('common.cancel')}</Button>
          <Button type="submit" variant="contained" disabled={loading}>
            {loading ? <CircularProgress size={24} /> : t('common.save')}
          </Button>
        </DialogActions>
      </form>
    </Dialog>
  );
};

export default VehicleDialog;
