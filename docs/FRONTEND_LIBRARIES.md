# Frontend Libraries Reference

This document provides comprehensive documentation for the shared libraries, hooks, utilities, and contexts used throughout the frontend application. These modules are designed to be reusable and should be used whenever their functionality is needed rather than implementing similar logic inline.

> **Note:** The mobile app (`mobile/`) uses similar patterns with React Native equivalents. See [mobile/README.md](../mobile/README.md) for mobile-specific documentation.

---

## Table of Contents

1. [Hooks](#hooks)
   - [useApiData](#useapidata)
   - [usePersistedSort](#usepersistedsort)
   - [useVehicleSelection](#usevehicleselection)
   - [useTablePagination](#usetablepagination)
   - [useDistance](#usedistance)
   - [useNotifications](#usenotifications)
   - [useDragDrop](#usedragdrop)
2. [Utilities](#utilities)
   - [SafeStorage](#safestorage)
   - [distanceUtils](#distanceutils)
   - [splitLabel](#splitlabel)
   - [formatCurrency](#formatcurrency)
   - [formatDate](#formatdate)
   - [logger](#logger)
   - [sortUtils](#sortutils)
   - [countryUtils](#countryutils)
3. [Contexts](#contexts)
   - [AuthContext](#authcontext)
   - [UserPreferencesContext](#userpreferencescontext)
   - [VehiclesContext](#vehiclescontext)
   - [ThemeContext](#themecontext)
4. [Components](#components)
   - [CenteredLoader](#centeredloader)
   - [VehicleSelector](#vehicleselector)

---

## Hooks

All hooks are located in `frontend/src/hooks/`.

### useApiData

**File:** `useApiData.js`

Provides standardised API data fetching with array validation. Ensures that API responses are always arrays to prevent runtime errors when mapping over data.

#### Exports

**`useApiData(fetchFn)`**

A hook that wraps an API fetch function and ensures the result is always an array.

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `fetchFn` | `function` | Yes | - | Async function that returns an Axios response |

**Returns:** `{ data: array, loading: boolean, error: Error|null, refresh: function }`

**Example:**
```javascript
import { useApiData } from '../hooks/useApiData';
import { api } from '../contexts/AuthContext';

function MyComponent() {
  const { data, loading, error, refresh } = useApiData(() => api.get('/fuel-records'));
  
  if (loading) return <Loader />;
  if (error) return <Error message={error.message} />;
  
  return data.map(record => <RecordRow key={record.id} record={record} />);
}
```

**`fetchArrayData(axiosPromise)`**

A standalone helper function for one-off API calls that ensures array responses.

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `axiosPromise` | `Promise` | Yes | Axios request promise |

**Returns:** `Promise<array>` - Resolves to an array (empty if response is not array-like)

**Example:**
```javascript
import { fetchArrayData } from '../hooks/useApiData';
import { api } from '../contexts/AuthContext';

const records = await fetchArrayData(api.get('/service-records'));
```

---

### usePersistedSort

**File:** `usePersistedSort.js`

Manages table sorting state with automatic persistence to localStorage. Sorting preferences are preserved across page refreshes and browser sessions.

#### Exports

**`usePersistedSort(storageKey, defaultField, defaultOrder)`**

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `storageKey` | `string` | Yes | - | Unique key for localStorage (e.g., `'fuelRecords_sort'`) |
| `defaultField` | `string` | No | `'date'` | Initial sort field if no saved preference |
| `defaultOrder` | `string` | No | `'desc'` | Initial sort direction (`'asc'` or `'desc'`) |

**Returns:** `{ orderBy: string, order: string, handleRequestSort: function }`

| Property | Type | Description |
|----------|------|-------------|
| `orderBy` | `string` | Current sort field |
| `order` | `string` | Current sort direction |
| `handleRequestSort` | `function(field: string)` | Toggle sort on a field |

**Example:**
```javascript
import { usePersistedSort } from '../hooks/usePersistedSort';

function FuelRecordsTable() {
  const { orderBy, order, handleRequestSort } = usePersistedSort('fuelRecords_sort', 'date', 'desc');
  
  return (
    <TableHead>
      <TableCell>
        <TableSortLabel
          active={orderBy === 'date'}
          direction={orderBy === 'date' ? order : 'asc'}
          onClick={() => handleRequestSort('date')}
        >
          Date
        </TableSortLabel>
      </TableCell>
    </TableHead>
  );
}
```

---

### useVehicleSelection

**File:** `useVehicleSelection.js`

Manages vehicle selection state with support for default vehicle preferences. Automatically selects the user's default vehicle when the component mounts.

#### Exports

**`useVehicleSelection(vehicles, options)`**

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `vehicles` | `array` | Yes | - | Array of vehicle objects with `id` property |
| `options` | `object` | No | `{}` | Configuration options |
| `options.includeViewAll` | `boolean` | No | `true` | Whether "View All" is a valid selection |

**Returns:** `{ selectedVehicleId: number|string, setSelectedVehicleId: function }`

| Property | Type | Description |
|----------|------|-------------|
| `selectedVehicleId` | `number\|string` | Currently selected vehicle ID or `'all'` |
| `setSelectedVehicleId` | `function` | Update the selected vehicle |

**Behaviour:**
- On mount, checks `UserPreferencesContext` for a `defaultVehicleId`
- If found and valid, selects that vehicle
- If `includeViewAll` is `true`, falls back to `'all'`
- Otherwise selects the first vehicle in the list

**Example:**
```javascript
import { useVehicleSelection } from '../hooks/useVehicleSelection';
import { useVehicles } from '../contexts/VehiclesContext';

function ServiceRecords() {
  const { vehicles } = useVehicles();
  const { selectedVehicleId, setSelectedVehicleId } = useVehicleSelection(vehicles, { includeViewAll: true });
  
  return (
    <VehicleSelector
      vehicles={vehicles}
      value={selectedVehicleId}
      onChange={setSelectedVehicleId}
      includeViewAll={true}
    />
  );
}
```

---

### useTablePagination

**File:** `useTablePagination.js`

Manages pagination state with integration to user preferences for default rows per page.

#### Exports

**`useTablePagination(defaultRowsPerPage)`**

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `defaultRowsPerPage` | `number` | No | `10` | Fallback rows per page |

**Returns:** `{ page: number, rowsPerPage: number, handleChangePage: function, handleChangeRowsPerPage: function }`

| Property | Type | Description |
|----------|------|-------------|
| `page` | `number` | Current page index (0-based) |
| `rowsPerPage` | `number` | Items per page |
| `handleChangePage` | `function(event, newPage)` | Page change handler |
| `handleChangeRowsPerPage` | `function(event)` | Rows per page change handler |

**Example:**
```javascript
import { useTablePagination } from '../hooks/useTablePagination';

function DataTable({ data }) {
  const { page, rowsPerPage, handleChangePage, handleChangeRowsPerPage } = useTablePagination(25);
  
  const paginatedData = data.slice(page * rowsPerPage, page * rowsPerPage + rowsPerPage);
  
  return (
    <>
      <Table>{/* ... */}</Table>
      <TablePagination
        rowsPerPageOptions={[10, 25, 50, 100]}
        count={data.length}
        rowsPerPage={rowsPerPage}
        page={page}
        onPageChange={handleChangePage}
        onRowsPerPageChange={handleChangeRowsPerPage}
      />
    </>
  );
}
```

---

### useDistance

**File:** `useDistance.js`

Provides distance conversion utilities based on user preferences (kilometres or miles).

#### Exports

**`useDistance()`**

Takes no parameters.

**Returns:** `{ convertDistance: function, formatDistance: function, unit: string, unitLabel: string }`

| Property | Type | Description |
|----------|------|-------------|
| `convertDistance` | `function(km: number)` | Converts km to user's preferred unit |
| `formatDistance` | `function(km: number, decimals?: number)` | Formats with unit suffix |
| `unit` | `string` | Current unit code (`'km'` or `'mi'`) |
| `unitLabel` | `string` | Display label (`'km'` or `'miles'`) |

**Example:**
```javascript
import { useDistance } from '../hooks/useDistance';

function MileageDisplay({ mileageKm }) {
  const { formatDistance } = useDistance();
  
  return <span>{formatDistance(mileageKm, 0)}</span>;
  // Output: "15,234 miles" or "24,520 km" depending on preference
}
```

---

### useNotifications

**File:** `useNotifications.js`

Manages real-time notifications via Server-Sent Events (SSE). Handles connection management, reconnection logic, and notification state.

#### Exports

**`useNotifications()`**

Takes no parameters.

**Returns:** `{ notifications: array, unreadCount: number, dismiss: function, snooze: function, loading: boolean }`

| Property | Type | Description |
|----------|------|-------------|
| `notifications` | `array` | Array of notification objects |
| `unreadCount` | `number` | Count of unread notifications |
| `dismiss` | `function(id: number)` | Mark notification as read |
| `snooze` | `function(id: number, days: number)` | Snooze notification |
| `loading` | `boolean` | Initial loading state |

**Notification Object Shape:**
```javascript
{
  id: number,
  type: string,        // 'mot_expiry', 'insurance_expiry', 'road_tax_expiry', etc.
  message: string,
  vehicleId: number,
  createdAt: string,   // ISO date
  read: boolean,
  snoozedUntil: string // ISO date or null
}
```

**Example:**
```javascript
import { useNotifications } from '../hooks/useNotifications';

function NotificationBell() {
  const { notifications, unreadCount, dismiss } = useNotifications();
  
  return (
    <Badge badgeContent={unreadCount} color="error">
      <NotificationsIcon />
    </Badge>
  );
}
```

---

### useDragDrop

**File:** `useDragDrop.js`

Handles file drag-and-drop operations with visual feedback states.

#### Exports

**`useDragDrop(onDrop)`**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `onDrop` | `function(files: FileList)` | Yes | Callback when files are dropped |

**Returns:** `{ isDragging: boolean, dragHandlers: object }`

| Property | Type | Description |
|----------|------|-------------|
| `isDragging` | `boolean` | Whether user is dragging over the drop zone |
| `dragHandlers` | `object` | Event handlers to spread on the drop zone element |

**dragHandlers Properties:**
- `onDragEnter`
- `onDragLeave`
- `onDragOver`
- `onDrop`

**Example:**
```javascript
import { useDragDrop } from '../hooks/useDragDrop';

function FileUploadZone({ onFilesSelected }) {
  const { isDragging, dragHandlers } = useDragDrop(onFilesSelected);
  
  return (
    <Box
      {...dragHandlers}
      sx={{
        border: isDragging ? '2px dashed blue' : '2px dashed grey',
        backgroundColor: isDragging ? 'action.hover' : 'transparent',
      }}
    >
      Drop files here
    </Box>
  );
}
```

---

## Utilities

All utilities are located in `frontend/src/utils/`.

### SafeStorage

**File:** `SafeStorage.js`

A safe wrapper around localStorage that handles unavailability (private browsing, storage quota exceeded) gracefully. Falls back to in-memory storage when localStorage is unavailable.

#### Exports

**`SafeStorage.get(key)`**

Retrieve a value from storage.

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `key` | `string` | Yes | Storage key |

**Returns:** `any` - Parsed JSON value or `null` if not found

**`SafeStorage.set(key, value)`**

Store a value.

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `key` | `string` | Yes | Storage key |
| `value` | `any` | Yes | Value to store (will be JSON stringified) |

**Returns:** `boolean` - `true` if successful

**`SafeStorage.remove(key)`**

Remove a value from storage.

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `key` | `string` | Yes | Storage key |

**Returns:** `boolean` - `true` if successful

**`SafeStorage.clear()`**

Clear all stored values.

**Returns:** `boolean` - `true` if successful

**`SafeStorage.isAvailable()`**

Check if localStorage is available.

**Returns:** `boolean`

**Example:**
```javascript
import SafeStorage from '../utils/SafeStorage';

// Store user preference
SafeStorage.set('theme', 'dark');

// Retrieve preference
const theme = SafeStorage.get('theme'); // 'dark'

// Remove preference
SafeStorage.remove('theme');
```

---

### distanceUtils

**File:** `distanceUtils.js`

Distance conversion utilities for handling kilometre/mile conversions throughout the application.

#### Exports

**`kmToMiles(km)`**

Convert kilometres to miles.

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `km` | `number` | Yes | Distance in kilometres |

**Returns:** `number` - Distance in miles

**`milesToKm(miles)`**

Convert miles to kilometres.

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `miles` | `number` | Yes | Distance in miles |

**Returns:** `number` - Distance in kilometres

**`convertDistance(km, unit)`**

Convert kilometres to specified unit.

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `km` | `number` | Yes | Distance in kilometres |
| `unit` | `string` | Yes | Target unit (`'km'` or `'mi'`) |

**Returns:** `number` - Converted distance

**`convertToKm(distance, unit)`**

Convert from specified unit to kilometres.

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `distance` | `number` | Yes | Distance value |
| `unit` | `string` | Yes | Source unit (`'km'` or `'mi'`) |

**Returns:** `number` - Distance in kilometres

**`formatDistance(km, unit, decimals)`**

Format distance with unit label.

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `km` | `number` | Yes | - | Distance in kilometres |
| `unit` | `string` | Yes | - | Display unit |
| `decimals` | `number` | No | `0` | Decimal places |

**Returns:** `string` - Formatted string (e.g., "15,234 miles")

**`getUnitLabel(unit)`**

Get human-readable unit label.

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `unit` | `string` | Yes | Unit code |

**Returns:** `string` - `'km'` or `'miles'`

---

### splitLabel

**File:** `splitLabel.js`

Utilities for splitting and formatting labels in table headers, particularly for vehicle registration numbers.

#### Exports

**`splitLabel(label)`**

Split a label string into multiple lines for table headers.

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `label` | `string` | Yes | Label to split |

**Returns:** `string[]` - Array of strings for each line

**`useSplitLabel(label)`**

React hook version that returns a memoised result.

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `label` | `string` | Yes | Label to split |

**Returns:** `string[]`

**`useRegistrationLabel(vehicle)`**

Extract and format a vehicle's display label (nickname or registration).

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `vehicle` | `object` | Yes | Vehicle object |

**Returns:** `string` - Vehicle nickname if set, otherwise registration number

**Example:**
```javascript
import { useRegistrationLabel } from '../utils/splitLabel';

function VehicleRow({ vehicle }) {
  const label = useRegistrationLabel(vehicle);
  return <TableCell>{label}</TableCell>;
}
```

---

### formatCurrency

**File:** `formatCurrency.js`

Currency formatting utilities using the Intl.NumberFormat API.

#### Exports

**`formatCurrency(amount, currency, locale)`**

Format a number as currency.

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `amount` | `number` | Yes | - | Amount to format |
| `currency` | `string` | No | `'GBP'` | ISO currency code |
| `locale` | `string` | No | `'en-GB'` | Locale for formatting |

**Returns:** `string` - Formatted currency string (e.g., "£1,234.56")

**Example:**
```javascript
import { formatCurrency } from '../utils/formatCurrency';

formatCurrency(1234.5);           // "£1,234.50"
formatCurrency(1234.5, 'USD', 'en-US'); // "$1,234.50"
formatCurrency(1234.5, 'EUR', 'de-DE'); // "1.234,50 €"
```

---

### formatDate

**File:** `formatDate.js`

Date formatting utilities for consistent date display across the application.

#### Exports

**`formatDate(dateString, options)`**

Format an ISO date string for display.

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `dateString` | `string` | Yes | - | ISO date string |
| `options` | `object` | No | `{}` | Intl.DateTimeFormat options |

**Returns:** `string` - Formatted date string

**Default Format:** `DD/MM/YYYY` (UK format)

**Example:**
```javascript
import { formatDate } from '../utils/formatDate';

formatDate('2024-03-15');  // "15/03/2024"
formatDate('2024-03-15', { weekday: 'long' });  // "Friday, 15 March 2024"
```

---

### logger

**File:** `logger.js`

Production-safe logging utility that suppresses non-error logs in production environments.

#### Exports

**`logger.log(...args)`**

Log to console (suppressed in production).

**`logger.info(...args)`**

Log informational message (suppressed in production).

**`logger.warn(...args)`**

Log warning (suppressed in production).

**`logger.error(...args)`**

Log error (always logged, including production).

**`logger.debug(...args)`**

Log debug information (suppressed in production).

**Example:**
```javascript
import logger from '../utils/logger';

logger.info('Component mounted');
logger.error('API call failed:', error);  // Always visible
```

---

### sortUtils

**File:** `sortUtils.js`

Generic sorting utilities for table data with support for various field types.

#### Exports

**`createSortComparator(order, orderBy, fieldConfigs)`**

Create a comparator function for use with `Array.sort()`.

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `order` | `string` | Yes | - | Sort direction (`'asc'` or `'desc'`) |
| `orderBy` | `string` | Yes | - | Field to sort by |
| `fieldConfigs` | `object` | No | `{}` | Field type configurations |

**fieldConfigs Object:**
```javascript
{
  fieldName: {
    type: 'string' | 'number' | 'date' | 'boolean',
    accessor: (item) => value,  // Optional custom value accessor
  }
}
```

**Returns:** `function(a, b)` - Comparator function

**`commonFieldConfigs`**

Pre-defined field configurations for common fields used across the application.

```javascript
{
  date: { type: 'date' },
  serviceDate: { type: 'date' },
  purchaseDate: { type: 'date' },
  cost: { type: 'number' },
  mileage: { type: 'number' },
  quantity: { type: 'number' },
  // ... more common fields
}
```

**Example:**
```javascript
import { createSortComparator, commonFieldConfigs } from '../utils/sortUtils';

function SortedTable({ data, orderBy, order }) {
  const sortedData = [...data].sort(
    createSortComparator(order, orderBy, commonFieldConfigs)
  );
  
  return <Table data={sortedData} />;
}
```

---

### countryUtils

**File:** `countryUtils.js`

Country and locale-related utilities.

#### Exports

**`getCountryFromLocale(locale)`**

Extract country code from a locale string.

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `locale` | `string` | Yes | Locale string (e.g., `'en-GB'`) |

**Returns:** `string` - Country code (e.g., `'GB'`)

**`getCurrencyForCountry(countryCode)`**

Get the default currency for a country.

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `countryCode` | `string` | Yes | ISO country code |

**Returns:** `string` - ISO currency code

---

## Contexts

All contexts are located in `frontend/src/contexts/`.

### AuthContext

**File:** `AuthContext.js`

Manages authentication state, login/logout operations, and provides a pre-configured Axios instance for API calls.

#### Exports

**`AuthProvider`**

Context provider component. Wrap your application with this component.

```jsx
<AuthProvider>
  <App />
</AuthProvider>
```

**`useAuth()`**

Hook to access authentication context.

**Returns:**
```javascript
{
  user: object | null,      // Current user object
  token: string | null,     // JWT token
  login: function,          // (email, password) => Promise
  logout: function,         // () => void
  register: function,       // (data) => Promise
  isAuthenticated: boolean, // Whether user is logged in
  loading: boolean,         // Initial auth check loading state
  api: AxiosInstance,       // Pre-configured Axios instance
}
```

**`api`** (named export)

Pre-configured Axios instance with:
- Base URL set to backend API
- JWT token automatically added to requests
- 401 response handling (auto logout)
- Request/response interceptors

**Example:**
```javascript
import { useAuth, api } from '../contexts/AuthContext';

function ProfilePage() {
  const { user, logout } = useAuth();
  
  const handleLogout = () => {
    logout();
  };
  
  // Use api for direct API calls
  const updateProfile = async (data) => {
    await api.put('/user/profile', data);
  };
}
```

---

### UserPreferencesContext

**File:** `UserPreferencesContext.js`

Manages user preferences such as default vehicle, theme, language, and rows per page.

#### Exports

**`UserPreferencesProvider`**

Context provider component.

**`useUserPreferences()`**

Hook to access user preferences.

**Returns:**
```javascript
{
  preferences: {
    defaultVehicleId: number | null,
    defaultRowsPerPage: number,
    theme: 'light' | 'dark',
    preferredLanguage: string,
    distanceUnit: 'km' | 'mi',
    currency: string,
  },
  updatePreference: function,  // (key, value) => Promise
  loading: boolean,
}
```

**Example:**
```javascript
import { useUserPreferences } from '../contexts/UserPreferencesContext';

function SettingsPage() {
  const { preferences, updatePreference } = useUserPreferences();
  
  const handleThemeChange = async (newTheme) => {
    await updatePreference('theme', newTheme);
  };
}
```

---

### VehiclesContext

**File:** `VehiclesContext.js`

Provides shared vehicle state across the application with caching to reduce API calls.

#### Exports

**`VehiclesProvider`**

Context provider component.

**`useVehicles()`**

Hook to access vehicles context.

**Returns:**
```javascript
{
  vehicles: array,          // Array of vehicle objects
  loading: boolean,
  error: Error | null,
  refreshVehicles: function, // Force refresh vehicle list
}
```

**Caching Behaviour:**
- Vehicles are cached for 30 seconds
- Calling `refreshVehicles()` bypasses the cache
- Cache is invalidated on vehicle create/update/delete operations

**Example:**
```javascript
import { useVehicles } from '../contexts/VehiclesContext';

function VehicleList() {
  const { vehicles, loading, refreshVehicles } = useVehicles();
  
  if (loading) return <Loader />;
  
  return (
    <>
      <Button onClick={refreshVehicles}>Refresh</Button>
      {vehicles.map(v => <VehicleCard key={v.id} vehicle={v} />)}
    </>
  );
}
```

---

### ThemeContext

**File:** `ThemeContext.js`

Manages light/dark theme state with MUI theme integration.

#### Exports

**`ThemeContextProvider`**

Context provider component. Must wrap MUI's ThemeProvider.

**`useThemeContext()`**

Hook to access theme context.

**Returns:**
```javascript
{
  mode: 'light' | 'dark',
  toggleTheme: function,  // () => void
  setMode: function,      // (mode: 'light' | 'dark') => void
}
```

**Example:**
```javascript
import { useThemeContext } from '../contexts/ThemeContext';

function ThemeToggle() {
  const { mode, toggleTheme } = useThemeContext();
  
  return (
    <IconButton onClick={toggleTheme}>
      {mode === 'light' ? <DarkModeIcon /> : <LightModeIcon />}
    </IconButton>
  );
}
```

---

## Components

Reusable UI components are located in `frontend/src/components/`.

### CenteredLoader

**File:** `CenteredLoader.js`

A simple centered loading spinner component.

#### Props

| Prop | Type | Required | Default | Description |
|------|------|----------|---------|-------------|
| `size` | `number` | No | `32` | Size of the spinner in pixels |
| `minHeight` | `string` | No | `'60vh'` | Minimum height of the container |

#### Example

```jsx
import CenteredLoader from '../components/CenteredLoader';

function DataPage({ loading, data }) {
  if (loading) {
    return <CenteredLoader size={48} minHeight="400px" />;
  }
  
  return <DataTable data={data} />;
}
```

---

### VehicleSelector

**File:** `VehicleSelector.js`

A dropdown component for selecting vehicles, commonly used for filtering data by vehicle.

#### Props

| Prop | Type | Required | Default | Description |
|------|------|----------|---------|-------------|
| `vehicles` | `array` | Yes | - | Array of vehicle objects |
| `value` | `number\|string` | Yes | - | Currently selected vehicle ID or `'all'` |
| `onChange` | `function` | Yes | - | Callback when selection changes |
| `showAddButton` | `boolean` | No | `false` | Show "Add Vehicle" button |
| `onAddVehicle` | `function` | No | - | Callback when "Add Vehicle" is clicked |
| `minWidth` | `number` | No | `200` | Minimum width in pixels |
| `includeViewAll` | `boolean` | No | `true` | Include "View All" option |
| `label` | `string` | No | `'Vehicle'` | Select label text |
| `disabled` | `boolean` | No | `false` | Disable the selector |

#### Example

```jsx
import VehicleSelector from '../components/VehicleSelector';
import { useVehicles } from '../contexts/VehiclesContext';
import { useVehicleSelection } from '../hooks/useVehicleSelection';

function RecordsPage() {
  const { vehicles } = useVehicles();
  const { selectedVehicleId, setSelectedVehicleId } = useVehicleSelection(vehicles);
  
  return (
    <VehicleSelector
      vehicles={vehicles}
      value={selectedVehicleId}
      onChange={setSelectedVehicleId}
      includeViewAll={true}
      label="Filter by Vehicle"
    />
  );
}
```

---

## Best Practices

### When to Use These Libraries

1. **Always use `usePersistedSort`** when implementing sortable tables to maintain user preferences.

2. **Always use `useVehicleSelection`** when implementing vehicle filtering to respect the user's default vehicle.

3. **Always use `SafeStorage`** instead of direct `localStorage` calls to handle edge cases gracefully.

4. **Always use `logger`** instead of `console.log` to keep production builds clean.

5. **Always use `api` from AuthContext** for API calls to ensure proper authentication headers.

6. **Always use `createSortComparator`** with `commonFieldConfigs` for consistent sorting behaviour.

### Common Patterns

**Typical page setup with filtering and sorting:**

```javascript
import { useVehicles } from '../contexts/VehiclesContext';
import { useVehicleSelection } from '../hooks/useVehicleSelection';
import { usePersistedSort } from '../hooks/usePersistedSort';
import { useApiData } from '../hooks/useApiData';
import { createSortComparator, commonFieldConfigs } from '../utils/sortUtils';
import { api } from '../contexts/AuthContext';

function RecordsPage() {
  const { vehicles } = useVehicles();
  const { selectedVehicleId, setSelectedVehicleId } = useVehicleSelection(vehicles);
  const { orderBy, order, handleRequestSort } = usePersistedSort('records_sort', 'date', 'desc');
  
  const { data, loading, refresh } = useApiData(() => 
    api.get('/records', { params: { vehicleId: selectedVehicleId !== 'all' ? selectedVehicleId : undefined }})
  );
  
  const sortedData = [...data].sort(createSortComparator(order, orderBy, commonFieldConfigs));
  
  // ... render
}
```
