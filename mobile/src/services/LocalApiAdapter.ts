import AsyncStorage from '@react-native-async-storage/async-storage';

const STORAGE_PREFIX = 'local_data_';

// The local user returned for /me and auth endpoints
const LOCAL_USER = {
  id: 1,
  email: 'local@standalone',
  firstName: 'Local',
  lastName: 'User',
  roles: ['ROLE_USER'],
};

// ============ Low-level storage helpers ============

async function getAllRecords(entity: string): Promise<any[]> {
  const raw = await AsyncStorage.getItem(STORAGE_PREFIX + entity);
  return raw ? JSON.parse(raw) : [];
}

async function saveAllRecords(entity: string, records: any[]): Promise<void> {
  await AsyncStorage.setItem(STORAGE_PREFIX + entity, JSON.stringify(records));
}

function getNextId(records: any[]): number {
  return records.reduce((max: number, r: any) => Math.max(max, r.id || 0), 0) + 1;
}

function createError(status: number, message: string): any {
  const err: any = new Error(message);
  err.response = {status, data: {error: message}};
  return err;
}

// ============ CRUD operations ============

async function listRecords(entity: string, params?: Record<string, any>): Promise<any[]> {
  let records = await getAllRecords(entity);

  // Apply vehicleId filter if present
  if (params?.vehicleId) {
    records = records.filter((r: any) => r.vehicleId === Number(params.vehicleId));
  }

  // Sort by most recent first
  records.sort((a: any, b: any) => (b.createdAt || '').localeCompare(a.createdAt || ''));
  return records;
}

async function getRecord(entity: string, id: number): Promise<any> {
  const records = await getAllRecords(entity);
  const record = records.find((r: any) => r.id === id);
  if (!record) {
    throw createError(404, 'Record not found');
  }
  return record;
}

async function createRecord(entity: string, data: any): Promise<any> {
  const records = await getAllRecords(entity);
  const id = getNextId(records);
  const now = new Date().toISOString();
  const record = {
    ...data,
    id,
    createdAt: now,
    updatedAt: now,
  };
  records.push(record);
  await saveAllRecords(entity, records);
  return record;
}

async function updateRecord(entity: string, id: number, data: any): Promise<any> {
  const records = await getAllRecords(entity);
  const index = records.findIndex((r: any) => r.id === id);
  if (index === -1) {
    throw createError(404, 'Record not found');
  }
  records[index] = {
    ...records[index],
    ...data,
    id, // never let data overwrite the id
    updatedAt: new Date().toISOString(),
  };
  await saveAllRecords(entity, records);
  return records[index];
}

async function deleteRecord(entity: string, id: number): Promise<void> {
  const records = await getAllRecords(entity);
  const filtered = records.filter((r: any) => r.id !== id);
  if (filtered.length === records.length) {
    throw createError(404, 'Record not found');
  }
  await saveAllRecords(entity, filtered);
}

// ============ URL parsing ============

interface ParsedRoute {
  segments: string[];
  entity: string;
  id?: number;
  sub?: string;
}

function parseUrl(url: string): ParsedRoute {
  const path = url.split('?')[0].replace(/^\//, '');
  const segments = path.split('/');
  const entity = segments[0];

  if (segments.length === 1) {
    return {segments, entity};
  }

  const second = segments[1];
  const secondIsNumeric = second !== '' && !isNaN(Number(second));

  if (!secondIsNumeric) {
    // e.g. /vehicles/totals, /user/preferences
    return {segments, entity, sub: second};
  }

  const id = Number(second);
  if (segments.length === 2) {
    return {segments, entity, id};
  }

  // e.g. /vehicles/5/costs, /mot-records/3/items
  return {segments, entity, id, sub: segments[2]};
}

// ============ Special local-only endpoints ============

async function getLocalPreferences(): Promise<any> {
  const raw = await AsyncStorage.getItem('local_preferences');
  return raw ? JSON.parse(raw) : {};
}

async function saveLocalPreferences(data: any): Promise<any> {
  const current = await getLocalPreferences();
  const merged = {...current, ...data};
  await AsyncStorage.setItem('local_preferences', JSON.stringify(merged));
  return merged;
}

async function computeVehicleTotals(): Promise<any> {
  const vehicles = await getAllRecords('vehicles');
  const fuelRecords = await getAllRecords('fuel-records');
  const serviceRecords = await getAllRecords('service-records');
  const motRecords = await getAllRecords('mot-records');

  const totalFuelCost = fuelRecords.reduce((sum: number, r: any) => sum + (parseFloat(r.totalCost || r.cost || '0') || 0), 0);
  const totalServiceCost = serviceRecords.reduce((sum: number, r: any) => sum + (parseFloat(r.totalCost || r.cost || '0') || 0), 0);
  const totalMotCost = motRecords.reduce((sum: number, r: any) => sum + (parseFloat(r.totalCost || r.testCost || '0') || 0), 0);

  return {
    totalVehicles: vehicles.length,
    totalFuelCost: totalFuelCost.toFixed(2),
    totalServiceCost: totalServiceCost.toFixed(2),
    totalMotCost: totalMotCost.toFixed(2),
    totalCost: (totalFuelCost + totalServiceCost + totalMotCost).toFixed(2),
  };
}

async function computeVehicleCosts(vehicleId: number): Promise<any> {
  const fuelRecords = (await getAllRecords('fuel-records')).filter((r: any) => r.vehicleId === vehicleId);
  const serviceRecords = (await getAllRecords('service-records')).filter((r: any) => r.vehicleId === vehicleId);
  const motRecords = (await getAllRecords('mot-records')).filter((r: any) => r.vehicleId === vehicleId);
  const parts = (await getAllRecords('parts')).filter((r: any) => r.vehicleId === vehicleId);
  const consumables = (await getAllRecords('consumables')).filter((r: any) => r.vehicleId === vehicleId);

  const fuelCost = fuelRecords.reduce((s: number, r: any) => s + (parseFloat(r.totalCost || r.cost || '0') || 0), 0);
  const serviceCost = serviceRecords.reduce((s: number, r: any) => s + (parseFloat(r.totalCost || r.cost || '0') || 0), 0);
  const motCost = motRecords.reduce((s: number, r: any) => s + (parseFloat(r.totalCost || r.testCost || '0') || 0), 0);
  const partsCost = parts.reduce((s: number, r: any) => s + (parseFloat(r.totalCost || r.cost || '0') || 0), 0);
  const consumablesCost = consumables.reduce((s: number, r: any) => s + (parseFloat(r.totalCost || r.cost || '0') || 0), 0);

  return {
    fuelCost: fuelCost.toFixed(2),
    serviceCost: serviceCost.toFixed(2),
    motCost: motCost.toFixed(2),
    partsCost: partsCost.toFixed(2),
    consumablesCost: consumablesCost.toFixed(2),
    totalCost: (fuelCost + serviceCost + motCost + partsCost + consumablesCost).toFixed(2),
  };
}

// ============ Main API adapter ============

export function createLocalApiAdapter(): any {
  return {
    get: async (url: string, config?: any) => {
      const {entity, id, sub} = parseUrl(url);
      const params = config?.params;

      // --- Special routes ---
      if (entity === 'me') {
        return {data: LOCAL_USER};
      }
      if (entity === 'user' && sub === 'preferences') {
        return {data: {data: await getLocalPreferences()}};
      }
      if (entity === 'dvsa') {
        throw createError(404, 'DVSA lookup is not available in standalone mode');
      }

      // --- Entity sub-routes ---
      if (entity === 'vehicles' && sub === 'totals') {
        return {data: await computeVehicleTotals()};
      }
      if (entity === 'vehicles' && id && sub === 'costs') {
        return {data: await computeVehicleCosts(id)};
      }
      if (entity === 'mot-records' && id && sub === 'items') {
        const motRecord = await getRecord('mot-records', id);
        const relatedParts = (await getAllRecords('parts')).filter((p: any) => p.motRecordId === id);
        const relatedConsumables = (await getAllRecords('consumables')).filter((c: any) => c.motRecordId === id);
        const relatedServices = (await getAllRecords('service-records')).filter((s: any) => s.motRecordId === id);
        return {
          data: {
            motRecord,
            parts: relatedParts,
            consumables: relatedConsumables,
            serviceRecords: relatedServices,
          },
        };
      }
      if (entity === 'attachments' && id) {
        return {data: await getRecord('attachments', id)};
      }

      // --- Standard CRUD: get by ID or list ---
      if (id) {
        return {data: await getRecord(entity, id)};
      }
      return {data: await listRecords(entity, params)};
    },

    post: async (url: string, data?: any, _config?: any) => {
      const {entity, sub} = parseUrl(url);

      if (entity === 'login') {
        return {data: {token: 'standalone-token', user: LOCAL_USER}};
      }
      if (entity === 'register') {
        return {data: {token: 'standalone-token', user: LOCAL_USER}};
      }
      if (entity === 'user' && sub === 'preferences') {
        return {data: await saveLocalPreferences(data)};
      }
      if (entity === 'attachments') {
        // Simplified attachment handling - store metadata only
        const record = await createRecord('attachments', {
          filename: 'local_attachment',
          originalFilename: 'photo.jpg',
          mimeType: 'image/jpeg',
          size: 0,
        });
        return {data: record, status: 201};
      }

      const record = await createRecord(entity, data);
      return {data: record, status: 201};
    },

    put: async (url: string, data?: any, _config?: any) => {
      const {entity, id} = parseUrl(url);
      if (!id) {
        throw createError(400, 'ID required for update');
      }
      const record = await updateRecord(entity, id, data);
      return {data: record};
    },

    delete: async (url: string, _config?: any) => {
      const {entity, id} = parseUrl(url);
      if (!id) {
        throw createError(400, 'ID required for delete');
      }
      await deleteRecord(entity, id);
      return {data: {message: 'Deleted successfully'}};
    },

    // Compatibility stubs - some code may reference interceptors
    interceptors: {
      request: {use: () => 0, eject: () => {}},
      response: {use: () => 0, eject: () => {}},
    },
  };
}

/**
 * Clear all locally-stored entity data (vehicles, records, etc.)
 * Does NOT clear server config or app preferences.
 */
export async function clearAllLocalData(): Promise<void> {
  const keys = await AsyncStorage.getAllKeys();
  const localKeys = keys.filter(k => k.startsWith(STORAGE_PREFIX) || k === 'local_preferences');
  if (localKeys.length > 0) {
    await AsyncStorage.multiRemove(localKeys);
  }
}
