# Translation System Summary

## Overview
The vehicle management application uses i18next for internationalization, supporting 21 languages with a centralized common translation section.

## Supported Languages (21)
1. **en** - English (Master/Source of Truth)
2. **es** - Spanish (Español)
3. **fr** - French (Français)
4. **de** - German (Deutsch)
5. **it** - Italian (Italiano)
6. **pt** - Portuguese (Português)
7. **nl** - Dutch (Nederlands)
8. **pl** - Polish (Polski)
9. **ru** - Russian (Русский)
10. **zh** - Chinese (中文)
11. **ja** - Japanese (日本語)
12. **ko** - Korean (한국어)
13. **fi** - Finnish (Suomi)
14. **sv** - Swedish (Svenska)
15. **no** - Norwegian (Norsk)
16. **da** - Danish (Dansk)
17. **cs** - Czech (Čeština)
18. **tr** - Turkish (Türkçe)
19. **ar** - Arabic (العربية)
20. **hi** - Hindi (हिन्दी)
21. **pir** - Pirate (Pirate Speak)

## File Structure
```
frontend/public/locales/
├── manifest.json              # Lists all available languages
├── en/
│   └── translation.json       # Master English translations (775 lines, 47 sections)
├── es/
│   └── translation.json       # Spanish translations
├── fr/
│   └── translation.json       # French translations
... (and 18 more language directories)
```

## Translation Sections (47 total)
1. **common** (68 keys) - Shared translations used across multiple components
   - Actions: edit, delete, view, save, cancel, back, undo, close, confirm
   - Navigation: export, import, search, refresh, sortBy
   - Fields: name, description, date, notes, cost, mileage, year, type, title
   - Dates: startDate, endDate, purchaseDate, installationDate
   - Parts: manufacturer, supplier, brand, partNumber, warranty, category
   - UI: loading, download, registrationNumber

2. **Vehicle-related sections**:
   - vehicle, vehicleDialog, vehicles, vehicleDetails, vehicleSpecifications, vehicleCard, vehicleImages
   
3. **Maintenance sections**:
   - fuel, fuelRecords, fuelDialog
   - parts, partCategories
   - consumables
   - service, serviceRecords, serviceTypes
   - mot, motRecords
   - roadTax
   
4. **Insurance sections**:
   - insurance
   
5. **UI sections**:
   - nav, dashboard, reports, auth, preferences, app, session
   - attachments, attachment
   - notifications, errors
   - importExport, todo
   
6. **Specialized sections**:
   - vinDecoder, scraper, dvla
   - securityFeatures, securityFeatureOptions
   - stats, depreciation
   - profile, register, password
   - languages, colors

## Key Features

### Centralized Common Translations
The `common` section contains 68 frequently-used translations that were de-duplicated from individual sections:
- Generic actions (edit, delete, view)
- Common form fields (date, notes, mileage, year)
- Shared data attributes (manufacturer, supplier, partNumber)

### Translation Usage in Code
```javascript
import { useTranslation } from 'react-i18next';

function MyComponent() {
  const { t } = useTranslation();
  
  return (
    <button>{t('common.edit')}</button>
    <input placeholder={t('vehicle.vin')} />
  );
}
```

### Language Detection Priority
1. User's saved preference (from backend API)
2. localStorage (`i18nextLng`)
3. Browser navigator language
4. Fallback: English (en)

## Maintenance Guidelines

### Adding New Translations
1. Always update `en/translation.json` first (source of truth)
2. Run `python3 generate_translations.py` to sync all language files
3. Provide professional translations for non-English languages

### Adding New Languages
1. Add entry to `manifest.json`:
   ```json
   {
     "code": "xx",
     "name": "Language Name",
     "nativeName": "Native Name"
   }
   ```
2. Create directory: `frontend/public/locales/xx/`
3. Run `python3 generate_translations.py`

### Translation File Generation
The `generate_translations.py` script:
- Uses `en/translation.json` as the master template
- Preserves existing translations in other language files
- Provides English fallbacks for missing translations
- Maintains consistent structure across all language files

### Current Status
- **Master File**: `en/translation.json` (775 lines, fully consolidated)
- **Language Files**: 21 translation.json files (772-775 lines each)
- **Total Translation Keys**: ~575 unique keys across 47 sections
- **Common Section**: 68 shared translations
- **Translation Coverage**: 
  - Spanish (es): ~60% professionally translated
  - Pirate (pir): ~40% custom translations
  - Other languages: English fallback (professional translation recommended)

## Tools

### generate_translations.py
Location: `/home/pclarke/Projects/php/vehicle/generate_translations.py`

Purpose: Generates/updates all language translation files based on the English master file

Usage:
```bash
cd /home/pclarke/Projects/php/vehicle
python3 generate_translations.py
```

Features:
- Preserves existing translations
- Uses English as fallback for new entries
- Validates JSON structure
- Maintains consistent formatting

## Code References Updated (45+ files)
All components now use the consolidated common section:
- Dialog components: FuelRecordDialog, MotDialog, ServiceDialog, PartDialog, ConsumableDialog, VehicleDialog, PolicyDialog, RoadTaxDialog, PasswordChangeDialog
- List components: FuelRecords, MotRecords, ServiceRecords, Parts, Consumables, Insurance, Policies, Vehicles, RoadTax, Todo
- Page components: Dashboard, VehicleDetails, Insurance
- Utility components: VinDecoder, AttachmentUpload

## Best Practices
1. **Always use namespaced keys**: `t('common.edit')` not `t('edit')`
2. **Use common section for shared terms**: Reduces duplication and ensures consistency
3. **Context-specific translations in dedicated sections**: e.g., `vehicle.addVehicle` vs generic `common.add`
4. **Variable interpolation**: Use `{{variableName}}` for dynamic content
5. **Validate JSON**: Always check JSON syntax after manual edits
6. **English as source of truth**: Never modify other language files directly without updating English first

## Technical Details
- **Framework**: i18next v23.8.2
- **React Integration**: react-i18next v14.0.5
- **Backend Loading**: i18next-http-backend v2.4.3
- **Detection**: i18next-browser-languagedetector v7.2.0
- **Format**: JSON with nested namespaces
- **Encoding**: UTF-8 (required for international characters)
- **Fallback Strategy**: Language-only (strips region codes)

## Future Enhancements
1. Professional translation services for all 20 non-English languages
2. Translation management platform integration (e.g., Crowdin, Lokalise)
3. Automated translation validation tests
4. Translation coverage reports
5. Context-aware translations for pluralization rules
