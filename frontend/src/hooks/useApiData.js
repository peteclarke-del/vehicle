import { useState, useCallback } from 'react';
import { useAuth } from '../contexts/AuthContext';

/**
 * Custom hook for fetching data from API with built-in array validation
 * @param {string} endpoint - API endpoint to fetch from
 * @param {*} initialData - Initial data (default: empty array)
 * @returns {Object} { data, loading, error, fetchData, setData }
 */
export const useApiData = (endpoint, initialData = []) => {
  const [data, setData] = useState(initialData);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const { api } = useAuth();

  const fetchData = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const response = await api.get(endpoint);
      // Ensure response is an array if initial data was an array
      const responseData = Array.isArray(initialData) 
        ? (Array.isArray(response.data) ? response.data : [])
        : response.data;
      setData(responseData);
      return responseData;
    } catch (err) {
      console.error(`Error loading data from ${endpoint}:`, err);
      setError(err);
      setData(initialData);
      return initialData;
    } finally {
      setLoading(false);
    }
  }, [api, endpoint, initialData]);

  return { data, loading, error, fetchData, setData };
};

/**
 * Helper function for one-off API calls with array validation
 * @param {Object} api - Axios instance from useAuth
 * @param {string} endpoint - API endpoint
 * @returns {Promise<Array>} - Always returns an array
 */
export const fetchArrayData = async (api, endpoint) => {
  try {
    const response = await api.get(endpoint);
    return Array.isArray(response.data) ? response.data : [];
  } catch (error) {
    console.error(`Error fetching ${endpoint}:`, error);
    return [];
  }
};
