# Backup Manifest and Export Coverage Plan

## Scope
This document defines full backup coverage for entities in `backend/src/Entity` and the expected portability behavior during import.

Status definitions:
- `exported_imported`: Explicitly serialized in backup payload and restored on import.
- `reconstructed`: Not copied 1:1; rebuilt or resolved from environment/runtime identity.
- `omitted`: Intentionally excluded from backup for security or environment-specific reasons.

## Entity Coverage Matrix

| Entity | Status | Rationale |
|---|---|---|
| Attachment | exported_imported | Exported as metadata + files in attachments/. |
| Consumable | exported_imported | Included in vehicle payload. |
| ConsumableType | exported_imported | Exported in globalState; also resolved/created when needed. |
| FeatureFlag | exported_imported | Exported in globalState and restored by featureKey. |
| FuelRecord | exported_imported | Included in vehicle payload. |
| InsurancePolicy | exported_imported | Included in vehicle payload. |
| MotRecord | exported_imported | Included in vehicle payload. |
| Part | exported_imported | Included in vehicle payload. |
| PartCategory | exported_imported | Exported in globalState; also resolved/created when needed. |
| RefreshToken | omitted | Security-sensitive session data; must never be portable. |
| Report | exported_imported | User report metadata included in globalState. |
| RoadTax | exported_imported | Included in vehicle payload. |
| SecurityFeature | exported_imported | Exported in globalState and restored by type+name. |
| ServiceItem | exported_imported | Included via service record payload. |
| ServiceRecord | exported_imported | Included in vehicle payload. |
| Specification | exported_imported | Included in vehicle payload. |
| StockItem | exported_imported | Included in stockItems payload. |
| Todo | exported_imported | Included in vehicle payload. |
| User | reconstructed | Import binds data to authenticated target user. |
| UserFeatureOverride | exported_imported | Exported in globalState and restored per featureKey. |
| UserPreference | exported_imported | Exported in globalState and restored per preference name. |
| Vehicle | exported_imported | Primary backup entity. |
| VehicleAssignment | omitted | Cross-user access mapping is environment-specific. |
| VehicleImage | exported_imported | Metadata in payload + files in images/. |
| VehicleMake | exported_imported | Exported in globalState; also resolved/created when needed. |
| VehicleModel | exported_imported | Exported in globalState; also resolved/created when needed. |
| VehicleStatusHistory | exported_imported | Included in vehicle payload. |
| VehicleType | exported_imported | Exported in globalState; also resolved/created when needed. |

## Backup File Layout

ZIP exports now include:
- `backup.json`: canonical full payload (`vehicles`, `stockItems`, `globalState`, `manifest`).
- `vehicles.json`: compatibility payload for legacy importers.
- `stock.json`: optional stock items file.
- `global.json`: optional global/reference state file.
- `MANIFEST.json`: schema version, entity coverage, and counts.
- `attachments/`: binary attachment exports.
- `images/`: vehicle image exports.

## Import Behavior

1. Import prefers `backup.json` when present.
2. Import accepts legacy `vehicles.json` archives.
3. If `stock.json` and/or `global.json` exist, they are merged into the import payload.
4. `globalState` is imported first to ensure reference data exists before vehicle hydration.
5. Vehicle and stock data import proceeds as before, including media restoration.
