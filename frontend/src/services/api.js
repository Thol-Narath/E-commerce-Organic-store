import axios from 'axios';

const TOKEN_KEY = 'organic_store_token';

const apiBaseUrl = import.meta.env.VITE_API_BASE_URL || '/api/v1';

const api = axios.create({
  baseURL: apiBaseUrl,
  headers: {
    Accept: 'application/json',
  },
});

api.interceptors.request.use((config) => {
  const token = localStorage.getItem(TOKEN_KEY);
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

/**
 * Unwrap the { success, message, data } envelope and expose field errors.
 */
api.interceptors.response.use(
  (response) => {
    // Return the enveloped data so callers receive `response.data.data`.
    return response;
  },
  (error) => {
    return Promise.reject(normalizeError(error));
  }
);

export function getToken() {
  return localStorage.getItem(TOKEN_KEY);
}

export function setToken(token) {
  if (token) {
    localStorage.setItem(TOKEN_KEY, token);
  } else {
    localStorage.removeItem(TOKEN_KEY);
  }
}

/**
 * Convert an Axios error into a consistent shape: { message, errors, status }.
 */
export function normalizeError(error) {
  const response = error.response;

  if (!response) {
    return {
      message: 'Network error. Please try again.',
      errors: null,
      status: 0,
    };
  }

  const body = response.data || {};
  return {
    message: body.message || 'Something went wrong.',
    errors: body.data && typeof body.data === 'object' ? body.data : null,
    status: response.status,
  };
}

export default api;
