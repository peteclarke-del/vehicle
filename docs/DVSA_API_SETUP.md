# DVSA MOT History API Integration

This project includes integration with the UK DVSA (Driver and Vehicle Standards Agency) MOT History API to automatically fetch vehicle details and MOT history.

## Features

- **Auto-populate vehicle details** when entering a UK registration number
- **Fetch MOT history** for registered vehicles
- **Get latest MOT test results** including expiry dates and mileage

## Setup

### 1. Get Your API Key

1. Visit the [DVSA MOT History API Registration](https://documentation.history.mot.api.gov.uk/mot-history-api/register)
2. Register for an API key
3. Wait for approval (usually takes a few business days)

### 2. Configure the API Key

Once you receive your API key, add it to the backend environment configuration:

**Option A: Using .env file (recommended for development)**

Edit `/backend/.env`:

```bash
DVSA_API_KEY=your_api_key_here
```

**Option B: Using Docker environment variables**

Edit `docker-compose.yml` and add under the `vehicle_php` service:

```yaml
environment:
  - DVSA_API_KEY=your_api_key_here
```

### 3. Restart the Backend

After adding the API key, restart the PHP container:

```bash
docker-compose restart vehicle_php
```

## Usage

### Frontend - Vehicle Registration Lookup

1. Open the "Add Vehicle" dialog
2. Enter a UK registration number (e.g., "AB12CDE")
3. Tab out of the field or click elsewhere
4. The system will automatically populate:
   - Make
   - Model
   - Year
   - Color
   - MOT Expiry Date
   - Current Mileage (from latest MOT)

### API Endpoints

The following endpoints are available:

#### Get Vehicle Details
```
GET /api/dvsa/vehicle/{registration}
```

Returns basic vehicle information including make, model, year, color, etc.

**Example Response:**
```json
{
  "registration": "AB12CDE",
  "make": "FORD",
  "model": "FOCUS",
  "yearOfManufacture": 2015,
  "primaryColour": "Blue",
  "fuelType": "Petrol",
  "engineSize": 1600
}
```

#### Get MOT History
```
GET /api/dvsa/mot-history/{registration}
```

Returns complete MOT history for the vehicle.

#### Get Latest MOT Test
```
GET /api/dvsa/latest-mot/{registration}
```

Returns details of the most recent MOT test.

**Example Response:**
```json
{
  "completedDate": "2024-01-15",
  "testResult": "PASSED",
  "expiryDate": "2025-01-14",
  "odometerValue": 45000,
  "odometerUnit": "mi",
  "motTestNumber": "123456789",
  "rfrAndComments": []
}
```

#### Check API Status
```
GET /api/dvsa/check
```

Check if the DVSA API is configured and accessible.

## API Rate Limits

The DVSA API has rate limits:
- **Standard**: 500 requests per day
- Contact DVSA for increased limits if needed

## Notes

- The API only works for UK registered vehicles
- Vehicle must have had at least one MOT test
- Some older vehicles may not be in the database
- The service gracefully handles failures - if the API is unavailable, the form fields remain editable

## Troubleshooting

### "Vehicle not found" error
- Verify the registration number is correct
- Check the vehicle has had an MOT
- Ensure the API key is properly configured

### "DVSA API unavailable" error
- Check your API key is valid
- Verify you haven't exceeded rate limits
- Check the backend logs: `docker-compose logs vehicle_php`

### API not populating fields
- Check the browser console for errors
- Verify the registration format (no spaces, uppercase)
- Try the check endpoint: `http://localhost:8081/api/dvsa/check`

## Documentation

Full DVSA API documentation: https://documentation.history.mot.api.gov.uk/
