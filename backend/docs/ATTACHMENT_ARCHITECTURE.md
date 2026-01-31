# Attachment Architecture

## Overview

The attachment system uses a flexible polymorphic pattern to support multiple types of attachments across different entities.

## Database Schema

```sql
CREATE TABLE attachments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    vehicle_id INT NULL,              -- FK to vehicles table (for cascade delete)
    user_id INT NOT NULL,             -- FK to users table
    filename VARCHAR(255) NOT NULL,    -- Stored filename (unique)
    original_name VARCHAR(255) NOT NULL, -- User's original filename
    mime_type VARCHAR(100) NOT NULL,
    file_size INT NOT NULL,
    uploaded_at DATETIME NOT NULL,
    entity_type VARCHAR(50) NULL,     -- Polymorphic: 'vehicle', 'part', 'service', etc.
    entity_id INT NULL,               -- Polymorphic: ID of the related entity
    description LONGTEXT NULL,
    storage_path VARCHAR(255) NULL,   -- Relative path from uploads/ directory
    category VARCHAR(50) NULL         -- Document category for organization
);
```

## Field Explanation

### vehicle_id vs entity_id

The attachment entity has **both** fields serving different purposes:

#### vehicle_id (Foreign Key Relationship)
- **Purpose**: Proper Doctrine ManyToOne relationship to Vehicle entity
- **Usage**: Enables cascade deletes when vehicle is removed
- **Set**: When attachment is directly related to a vehicle document
- **Example**: User manual, service manual, vehicle spec sheet

#### entity_id + entity_type (Polymorphic Relationship)
- **Purpose**: Generic linkage to ANY entity type
- **Usage**: Querying/filtering attachments by entity
- **Set**: For all attachments regardless of type
- **Example**: 
  - `entity_type='vehicle', entity_id=123` - Vehicle documents
  - `entity_type='part', entity_id=456` - Part receipt
  - `entity_type='service', entity_id=789` - Service record receipt

### When to Set Both

For **vehicle documents** (manuals, specs, etc.):
```php
$attachment->setVehicle($vehicle);        // Sets vehicle_id FK
$attachment->setEntityType('vehicle');    // Sets polymorphic type
$attachment->setEntityId($vehicle->getId()); // Sets polymorphic ID
```

For **receipt attachments** (parts, services, etc.):
```php
// These are linked via Part/Service entity's receiptAttachment field
// Only entity_type and entity_id are set
$attachment->setEntityType('part');
$attachment->setEntityId($part->getId());
```

### category Field

Optional field for organizing vehicle documents into tabs/sections:

- `user_manual` - Owner's manual, user guides
- `service_manual` - Repair manuals, technical docs
- `vehicle_spec` - Specification sheets, brochures
- `documentation` - General documents, receipts, misc

**Important**: Category is optional. If not set, attachments are still accessible by `entity_type` and `entity_id`.

## Querying Attachments

### Controller Pattern

```php
// AttachmentController::list()
$qb = $repository->createQueryBuilder('a')
    ->where('a.user = :user')
    ->setParameter('user', $user);

// Filter by entity
if ($entityType) {
    $qb->andWhere('a.entityType = :entityType')
        ->setParameter('entityType', $entityType);
}

if ($entityId) {
    $qb->andWhere('a.entityId = :entityId')
        ->setParameter('entityId', $entityId);
}

// Category filter is OPTIONAL - only apply if explicitly provided
if ($category !== null && $category !== '') {
    $qb->andWhere('a.category = :category')
        ->setParameter('category', $category);
}

$attachments = $qb->orderBy('a.uploadedAt', 'DESC')
    ->getQuery()
    ->getResult();
```

### Frontend API Call

```javascript
// Load all vehicle documents
const response = await api.get('/attachments', {
  params: {
    entityType: 'vehicle',
    entityId: vehicle.id,
    category: 'user_manual', // Optional filter
  },
});
```

## Import/Export

### Export (ZIP)

Attachments are exported in the manifest with all metadata:

```json
{
  "type": "attachment",
  "originalId": 123,
  "filename": "service_manual.pdf",
  "manifestName": "attachment_123_service_manual.pdf",
  "originalName": "Softail-2018-Service-Manual.pdf",
  "mimeType": "application/pdf",
  "fileSize": 5242880,
  "uploadedAt": "2026-01-30T12:34:56+00:00",
  "entityType": "vehicle",
  "entityId": 1,
  "description": "Official service manual",
  "storagePath": "attachments/vehicle-1/service_manual.pdf",
  "category": "service_manual"
}
```

### Import (ZIP)

When importing, the system:

1. **Creates new attachment entities** from manifest
2. **Sets all fields including category** (was missing before fix)
3. **Maps old IDs to new IDs** for reference integrity
4. **Remaps entity_id** after vehicles/parts/services are created with new IDs

```php
// Import creates attachment with OLD entityId first
$attachment->setEntityId($manifestData['entityId']); // OLD ID

// After vehicles are created, remap to NEW IDs
if ($entityType === 'vehicle' && isset($vehicleIdMap[$oldEntityId])) {
    $newVehicle = $vehicleIdMap[$oldEntityId];
    $attachment->setEntityId($newVehicle->getId());  // NEW ID
    $attachment->setVehicle($newVehicle);            // Set FK relationship
}
```

## Storage Structure

```
uploads/
  attachments/
    vehicle-1/           # Organized by vehicle
      manual_abc123.pdf
      spec_def456.pdf
    vehicle-2/
      manual_ghi789.pdf
    misc/                # Non-vehicle attachments
      receipt_jkl012.jpg
```

## Common Issues and Solutions

### Issue: Imported attachments not showing

**Symptoms**: Files exist in uploads/, database records look correct, but attachments don't display in UI

**Cause**: Missing `category` field in imported attachments (was exported but not imported)

**Solution**:
1. Updated import to set `category` from manifest
2. Updated query to make `category` filter optional
3. Assigned categories to existing NULL values based on filename patterns:

```sql
UPDATE attachments 
SET category = CASE
    WHEN LOWER(original_name) LIKE '%manual%' AND LOWER(original_name) LIKE '%service%' 
        THEN 'service_manual'
    WHEN LOWER(original_name) LIKE '%manual%' 
        THEN 'user_manual'
    WHEN LOWER(original_name) LIKE '%spec%' 
        THEN 'vehicle_spec'
    ELSE 'documentation'
END
WHERE entity_type = 'vehicle' AND category IS NULL;
```

### Issue: vehicle_id is NULL after import

**Symptoms**: Imported vehicle attachments have `entity_id` set correctly but `vehicle_id` is NULL

**Cause**: Doctrine doesn't track relationship changes on already-persisted entities by default

**Why it matters**: 
- Without `vehicle_id`, database-level CASCADE delete won't work
- ORM cascade still works (via `entity_type`/`entity_id`), but direct SQL deletes would leave orphans
- Belt-and-suspenders approach: both ORM and DB cascade = maximum data safety

**Solution**: Force Doctrine to recognize the relationship change during import:

```php
// In VehicleImportExportController::import() remapping phase
$attachment->setVehicle($newVehicle);
// Force Doctrine UnitOfWork to compute changeset for this entity
$entityManager->getUnitOfWork()->recomputeSingleEntityChangeSet(
    $entityManager->getClassMetadata(\App\Entity\Attachment::class),
    $attachment
);
$entityManager->flush();
```

**Fix existing NULL vehicle_id records**:

```sql
UPDATE attachments 
SET vehicle_id = entity_id 
WHERE entity_type = 'vehicle' 
  AND vehicle_id IS NULL 
  AND entity_id IS NOT NULL;
```

### Issue: Orphaned attachments after delete

**Symptoms**: Attachment files remain after deleting parent entity

**Solution**: Cascade delete is configured on relationships:

```php
// In Part, Consumable, ServiceRecord, etc.
#[ORM\ManyToOne(targetEntity: Attachment::class, cascade: ['remove'])]
private ?Attachment $receiptAttachment = null;

// In Attachment entity
#[ORM\ManyToOne(targetEntity: Vehicle::class)]
#[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
private ?Vehicle $vehicle = null;
```

## Best Practices

1. **Always set both vehicle_id AND entity_id for vehicle documents** - ensures proper cascade and filtering
2. **Set category during upload** - improves organization and filtering
3. **Use entity_type + entity_id for filtering** - more flexible than FK relationships
4. **Keep vehicle_id for cascade deletes** - proper cleanup when vehicle deleted
5. **Export/Import category** - maintain document organization across systems
6. **Use recomputeSingleEntityChangeSet for relationship changes** - ensures Doctrine tracks updates on already-persisted entities

## Future Improvements

- [ ] Auto-detect category from file content/name during upload
- [ ] Add attachment versioning (track document revisions)
- [ ] Implement attachment tags for more flexible organization
- [ ] Add full-text search across attachment metadata and OCR'd content
