# Quick Start Guide

## Fastest Way to Get Started

1. **Clone and setup:**
```bash
git clone <repository-url>
cd vehicle
chmod +x setup.sh
./setup.sh
```

2. **Start frontend:**
```bash
cd frontend
npm start
```

3. **Open browser:**
- Go to http://localhost:3000
- Register a new account
- Start managing your vehicles!

## Create Your First Vehicle

1. Click "Add Vehicle" on the Vehicles page
2. Fill in the details:
   - Name: "My Car"
   - Type: Select "Car"
   - Make: "Toyota"
   - Model: "Camry"
   - Year: 2020
   - Purchase Cost: 25000
   - Purchase Date: Select date
3. Click Save

## Add Fuel Records

1. Go to Fuel Records page
2. Select your vehicle
3. Click "Add Fuel Record"
4. Enter:
   - Date
   - Mileage
   - Litres
   - Cost
   - (Optional) Fuel Type and Station
5. Save

## View Statistics

1. Go to Vehicles page
2. Click "View Details" on any vehicle
3. Navigate through tabs:
   - Overview: Vehicle information
   - Statistics: Costs and consumption
   - Depreciation: Value over time chart

## Customize Your Experience

### Change Theme
1. Go to Profile
2. Select "Dark" or "Light" theme
3. Changes apply immediately

### Change Language
1. Go to Profile
2. Select language (English, Spanish, French)
3. Interface updates automatically

## Common Tasks

### Update Vehicle Mileage
- Add a fuel record with current mileage
- System automatically updates vehicle's current mileage

### Track Maintenance
- Use Parts section to record:
  - Service parts
  - Repairs
  - Upgrades
  - Installation dates and costs

### Monitor Consumables
- Track tyre changes
- Record oil changes
- Monitor brake fluid
- Track any consumable with:
  - Specification
  - Last changed date
  - Mileage at change
  - Cost

## Troubleshooting

### Can't login?
- Make sure you registered first
- Check email and password
- Look for error messages

### No vehicles showing?
- Add a vehicle first using "Add Vehicle" button
- Check you're logged in

### Backend not responding?
```bash
docker-compose ps  # Check if containers are running
docker-compose logs php  # Check PHP logs
docker-compose logs mysql  # Check MySQL logs
```

### Frontend errors?
```bash
cd frontend
rm -rf node_modules package-lock.json
npm install
npm start
```

## Production Deployment

### Environment Setup
1. Update `.env` with production values
2. Set secure JWT_PASSPHRASE
3. Configure production database
4. Set APP_ENV=prod

### Build Frontend
```bash
cd frontend
npm run build
```

### Configure Nginx for Production
Serve frontend build from `frontend/build/` directory

### Security Checklist
- [ ] Change all default passwords
- [ ] Generate new JWT keys
- [ ] Configure HTTPS
- [ ] Set up firewall rules
- [ ] Configure CORS properly
- [ ] Enable rate limiting
- [ ] Set up backups
- [ ] Configure monitoring

## API Usage Examples

### Register User
```bash
curl -X POST http://localhost:8080/api/register \
  -H "Content-Type: application/json" \
  -d '{
    "email": "user@example.com",
    "password": "password123",
    "firstName": "John",
    "lastName": "Doe"
  }'
```

### Login
```bash
curl -X POST http://localhost:8080/api/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "user@example.com",
    "password": "password123"
  }'
```

### Create Vehicle (with token)
```bash
curl -X POST http://localhost:8080/api/vehicles \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{
    "name": "My Car",
    "vehicleTypeId": 1,
    "make": "Toyota",
    "model": "Camry",
    "year": 2020,
    "purchaseCost": "25000",
    "purchaseDate": "2020-01-15"
  }'
```

## Advanced Features

### SAML/SSO Setup
1. Configure SAML_IDP_* variables in `.env`
2. Update `hslavich_onelogin_saml.yaml`
3. Register your SP with your IdP
4. Test authentication flow

### Custom Depreciation
- Straight Line: Even depreciation over years
- Declining Balance: Percentage-based each year
- Double Declining: Accelerated depreciation

### Multi-Vehicle Management
- Track unlimited vehicles
- Compare costs across vehicles
- View aggregated statistics on dashboard

## Need Help?

- Check README.md for detailed documentation
- Open an issue on GitHub
- Check existing issues for solutions
- Review API documentation

## Next Steps

1. ✅ Set up the application
2. ✅ Create your first vehicle
3. ✅ Add some fuel records
4. ✅ Customize theme and language
5. 📊 Explore statistics and charts
6. 🔧 Track parts and maintenance
7. 📈 Monitor depreciation
8. 💰 Analyze costs

Happy vehicle tracking! 🚗
