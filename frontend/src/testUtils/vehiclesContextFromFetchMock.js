// Test helper: a stand-in for useVehicles() that loads through the (mocked)
// fetchArrayData('/vehicles'), so page tests can keep stubbing vehicles the
// same way they stub every other endpoint.
//
// Usage in a test file that already mocks ../hooks/useApiData:
//   jest.mock('../contexts/VehiclesContext', () => ({
//     useVehicles: () => require('../testUtils/vehiclesContextFromFetchMock').default(),
//   }));
import { useCallback, useState } from 'react';
import { fetchArrayData } from '../hooks/useApiData';

export default function useVehiclesFromFetchMock() {
  const [vehicles, setVehicles] = useState([]);
  const refreshVehicles = useCallback(async () => {
    const data = await fetchArrayData(null, '/vehicles');
    const list = Array.isArray(data) ? data : [];
    setVehicles(list);
    return list;
  }, []);
  return { vehicles, loading: false, refreshVehicles, fetchVehicles: refreshVehicles };
}
