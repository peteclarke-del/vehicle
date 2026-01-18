# Cost Tracking and Correlation Guide

## Overview

The Vehicle Management System now provides comprehensive cost tracking with the ability to correlate expenses to specific maintenance events. This allows for accurate reporting on where money is being spent and what work was performed.

## Cost Categories

### 1. Direct Purchase Costs
- **Fuel Records**: Track fuel purchases with date, cost, litres, mileage
- **Parts**: Individual part purchases (can be standalone or linked to service/MOT)
- **Consumables**: Oils, filters, tyres, etc. (can be standalone or linked to service/MOT)
- **Insurance**: Annual insurance policy costs

### 2. Maintenance Event Costs
- **MOT Records**: Test fees + repair costs
- **Service Records**: Labor costs + parts costs

## Cost Correlation System

### Linking Parts/Consumables to Events

Parts and consumables can be linked to specific maintenance events in two ways:

#### 1. Service Record Linkage
When parts or consumables are used during a service:
```
ServiceRecord
├── laborCost: £150.00
├── partsCost: £85.50 (auto-calculated from linked items)
└── Linked Items:
    ├── Part: Oil Filter (£12.50)
    ├── Part: Air Filter (£18.00)
    ├── Consumable: Engine Oil 5L (£35.00)
    └── Consumable: Coolant 2L (£20.00)
```

#### 2. MOT Record Linkage
When parts or consumables are used for MOT repairs:
```
MotRecord
├── testCost: £54.85
├── repairCost: £320.00 (auto-calculated from linked items)
└── Linked Items:
    ├── Part: Brake Pads (£85.00)
    ├── Part: Brake Discs (£150.00)
    └── Consumable: Brake Fluid (£15.00)
```

### Database Implementation

#### Parts Table
```sql
parts
├── id
├── vehicle_id
├── description, part_number, manufacturer
├── cost
├── installation_date, mileage_at_installation
├── service_record_id (nullable, links to service)
├── mot_record_id (nullable, links to MOT)
└── notes
```

#### Consumables Table
```sql
consumables
├── id
├── vehicle_id
├── consumable_type_id
├── specification, quantity, brand
├── cost
├── last_changed, mileage_at_change
├── service_record_id (nullable, links to service)
├── mot_record_id (nullable, links to MOT)
└── notes
```

## Use Cases

### Scenario 1: Regular Service
1. Create a **Service Record** (e.g., "Annual Service")
   - Service Date: 2026-01-15
   - Service Type: Full Service
   - Labor Cost: £150.00
   - Service Provider: "Main Street Garage"

2. Add **Consumables** used during service:
   - Engine Oil (5L) - £35.00 - linked to Service Record
   - Oil Filter - £12.50 - linked to Service Record
   - Air Filter - £18.00 - linked to Service Record
   - Coolant - £20.00 - linked to Service Record

3. **Result**: 
   - Total Service Cost: £235.50 (£150 labor + £85.50 parts)
   - All items clearly attributed to this service
   - Can generate service report with all items used

### Scenario 2: MOT with Repairs
1. Create a **MOT Record**
   - Test Date: 2026-02-10
   - Result: Pass (with repairs)
   - Test Cost: £54.85
   - Test Center: "Quick MOT Centre"

2. Add **Parts** used for MOT repairs:
   - Brake Pads (Front) - £85.00 - linked to MOT Record
   - Brake Discs (Front) - £150.00 - linked to MOT Record
   - Wiper Blades - £18.00 - linked to MOT Record

3. Add **Consumables** for repairs:
   - Brake Fluid - £15.00 - linked to MOT Record

4. **Result**:
   - Test Cost: £54.85
   - Repair Cost: £268.00 (auto-calculated)
   - Total MOT Cost: £322.85
   - Complete list of repairs and parts used
   - Can identify why MOT was expensive

### Scenario 3: Standalone Part Purchase
1. Buy a spare tire that's not immediately used
2. Create **Part** record without linking:
   - Description: Spare Tire
   - Cost: £120.00
   - Purchase Date: 2026-03-01
   - service_record_id: NULL
   - mot_record_id: NULL

3. **Result**:
   - Part tracked in inventory
   - Cost counted in total parts expenses
   - Not attributed to specific maintenance event
   - Can be linked later when used

### Scenario 4: Service with Pre-purchased Parts
1. Previously bought part (e.g., spark plugs purchased 2 weeks ago)
2. Service performed today
3. Update the **Part** record:
   - Set service_record_id to link to today's service
   - Update installation_date if needed

4. **Result**:
   - Part cost now attributed to this service
   - Service partsCost auto-updates
   - Full service history available

## Reporting Capabilities

### Total Vehicle Cost Breakdown
```
Vehicle: Honda Civic (2020)
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Purchase Cost:              £18,000.00
Depreciation:              -£3,600.00
Current Value:              £14,400.00

Running Costs:
├── Fuel:                    £2,450.00
├── Insurance:               £1,200.00
├── MOT (3 tests):             £164.55
│   ├── Test Fees:              £54.85 × 3
│   └── Repairs:                £268.00
├── Services (2):              £650.00
│   ├── Labor:                 £300.00
│   └── Parts/Consumables:     £350.00
├── Parts (standalone):        £245.00
└── Consumables (standalone):  £180.00
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Total Running Costs:         £4,889.55
Total Cost to Date:         £22,889.55
Cost per Mile:                  £0.38
```

### Service History Report
```
Service Record #15 - Annual Service
Date: 15 Jan 2026
Mileage: 45,230 miles
Provider: Main Street Garage

Labor Cost: £150.00

Parts & Consumables Used:
├── Engine Oil 5W-30 (5L) - £35.00
├── Oil Filter - £12.50
├── Air Filter - £18.00
├── Spark Plugs (x4) - £48.00
└── Coolant - £20.00
━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Parts Total: £133.50
Service Total: £283.50

Work Performed:
- Oil and filter change
- Air filter replacement
- Spark plug replacement
- Coolant top-up
- Visual inspection of brakes, suspension, exhaust
```

### MOT History Report
```
MOT Record #8
Test Date: 10 Feb 2026
Mileage: 46,100 miles
Test Center: Quick MOT Centre
Result: PASS (with repairs)

Test Fee: £54.85

Advisories:
- Front brake discs worn but above minimum
- Slight oil leak from rocker cover (monitor)

Failures:
- Front brake pads below minimum thickness
- Wiper blades ineffective

Repairs Performed:
├── Brake Pads (Front) - £85.00
├── Brake Discs (Front) - £150.00
├── Wiper Blades (Pair) - £18.00
└── Brake Fluid Replacement - £15.00
━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Repair Total: £268.00
MOT Total: £322.85
```

## API Integration

### Creating Linked Items

#### When creating a service record:
```javascript
// 1. Create the service record
POST /api/service-records
{
  "vehicleId": 1,
  "serviceDate": "2026-01-15",
  "serviceType": "Full Service",
  "laborCost": 150.00,
  "mileage": 45230,
  "serviceProvider": "Main Street Garage",
  "workPerformed": "Full service including oil change..."
}
// Returns: { "id": 15, ... }

// 2. Add parts/consumables linked to this service
POST /api/parts
{
  "vehicleId": 1,
  "description": "Oil Filter",
  "cost": 12.50,
  "purchaseDate": "2026-01-15",
  "installationDate": "2026-01-15",
  "serviceRecordId": 15  // Link to service
}

POST /api/consumables
{
  "vehicleId": 1,
  "consumableTypeId": 2,  // Engine Oil
  "specification": "5W-30 Fully Synthetic",
  "quantity": 5.0,
  "cost": 35.00,
  "lastChanged": "2026-01-15",
  "serviceRecordId": 15  // Link to service
}
```

#### When creating an MOT record:
```javascript
// 1. Create the MOT record
POST /api/mot-records
{
  "vehicleId": 1,
  "testDate": "2026-02-10",
  "result": "Pass",
  "testCost": 54.85,
  "mileage": 46100,
  "testCenter": "Quick MOT Centre",
  "failures": "Front brake pads below minimum...",
  "repairDetails": "Replaced brake pads and discs..."
}
// Returns: { "id": 8, ... }

// 2. Add parts used for MOT repairs
POST /api/parts
{
  "vehicleId": 1,
  "description": "Brake Pads (Front)",
  "cost": 85.00,
  "purchaseDate": "2026-02-10",
  "installationDate": "2026-02-10",
  "motRecordId": 8  // Link to MOT
}
```

### Querying Costs

#### Get all costs for a vehicle:
```
GET /api/vehicles/1/stats
```
Returns comprehensive cost breakdown including:
- Fuel costs
- Parts costs (linked and standalone)
- Consumables costs (linked and standalone)
- Insurance costs
- MOT costs (tests + repairs)
- Service costs (labor + parts)

#### Get service with items:
```
GET /api/service-records/15/items
```
Returns service record with all linked parts and consumables.

## Benefits

1. **Accurate Cost Attribution**: Know exactly what was spent on each maintenance event
2. **Complete Service History**: Full documentation of work performed and parts used
3. **Budget Planning**: Identify expensive maintenance patterns
4. **Warranty Tracking**: Link warranty parts to installation events
5. **Resale Documentation**: Complete maintenance records increase resale value
6. **Tax Records**: Detailed expense tracking for business vehicles
7. **Preventive Maintenance**: Track when specific items were last replaced

## Implementation Notes

- Foreign keys use `ON DELETE SET NULL` to preserve historical cost data
- Parts/consumables can exist without service/MOT links (standalone purchases)
- Linking is optional but recommended for accurate reporting
- Auto-calculation of service partsCost and MOT repairCost
- All cost fields use DECIMAL(10,2) for precision
- Timestamps track when records were created for audit trail
