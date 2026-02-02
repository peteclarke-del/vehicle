# Consumable Entity Unit Conversion Fix

## Problem

The Consumable entity had "Miles" hardcoded in property and method names, but the database stores values in **kilometers (KM)**. This caused:

1. **Method naming inconsistency**: `setReplacementIntervalMiles()` didn't exist, only `setReplacementInterval()`
2. **Unit confusion**: Property names suggested miles but database stored km
3. **No clear conversion documentation**: Unclear what units were being stored

## Solution

### Database Changes (Migration: Version20260202152225)

Renamed columns to be unit-agnostic:
- `replacement_interval_miles` → `replacement_interval` (stores KM)
- `next_replacement_mileage` → `next_replacement` (stores KM)

**Important**: Values remain in kilometers. Only column names changed.

### Entity Changes

1. **Property Renaming**:
   ```php
   // Old (misleading)
   private ?int $replacementIntervalMiles = null;
   private ?int $nextReplacementMileage = null;
   
   // New (unit-agnostic, stores KM)
   private ?int $replacementInterval = null;
   private ?int $nextReplacement = null;
   ```

2. **Primary Methods** (unit-agnostic):
   - `getReplacementInterval()`: Returns interval in KM
   - `setReplacementInterval(?int $interval)`: Stores interval in KM
   - `getNextReplacement()`: Returns next replacement distance in KM
   - `setNextReplacement(?int $distance)`: Stores next replacement in KM

3. **Backward Compatibility Aliases**:
   ```php
   // These methods still work for existing code
   getReplacementIntervalMiles()  // Returns KM (name is historical)
   setReplacementIntervalMiles()  // Stores KM (name is historical)
   getNextReplacementMileage()    // Returns KM (name is historical)
   setNextReplacementMileage()    // Stores KM (name is historical)
   ```

### Frontend Conversion

The frontend (ConsumableDialog.js) **already converts correctly**:

```javascript
// User enters miles → converted to KM for storage
replacementIntervalMiles: formData.replacementIntervalMiles 
  ? Math.round(toKm(parseFloat(formData.replacementIntervalMiles))) 
  : null
```

When loading data:
```javascript
// KM from database → converted to miles for display
replacementIntervalMiles: consumable.replacementIntervalMiles 
  ? Math.round(convert(consumable.replacementIntervalMiles)) 
  : ''
```

### Backend Storage

**ConsumableController.php** (line 386):
```php
// Frontend sends data already converted to KM
if (isset($data['replacementIntervalMiles'])) {
    // This now works - calls alias method
    $consumable->setReplacementIntervalMiles($data['replacementIntervalMiles']);
}
```

## Data Flow Summary

1. **User Input** (Frontend): Miles (US user preference)
2. **Frontend → Backend**: Converted to KM via `toKm()`
3. **Database Storage**: KM (always)
4. **Backend → Frontend**: KM (via `getReplacementIntervalMiles()`)
5. **Frontend Display**: Converted to miles via `convert()`

## Unit Standards

- **Database**: Always stores in **kilometers (KM)**
- **Frontend Display**: Shows in user's preference (miles for US users)
- **API Communication**: Uses KM (frontend does conversion)
- **Entity Methods**: Unit-agnostic names, but values are KM

## Migration Instructions

If you have existing data:
1. Run migration: `php bin/console doctrine:migrations:migrate`
2. No data conversion needed - only column names change
3. All existing code continues to work via alias methods

## Testing

Verified:
- ✅ `setReplacementInterval()` works
- ✅ `setReplacementIntervalMiles()` alias works
- ✅ Both store values in KM
- ✅ Frontend conversion (toKm/convert) works correctly
- ✅ Database migration successful
- ✅ Backward compatibility maintained

## Future Considerations

1. Consider renaming API field from `replacementIntervalMiles` to `replacementInterval` in future API version
2. Document that all distance values in API are in KM
3. Consider adding OpenAPI schema documentation for units
