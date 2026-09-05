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
 * Response interceptor: unwrap success envelope, and on error log the real
 * Axios error to the console so developers can see the actual status / body /
 * URL. The original Axios error is re-thrown so callers receive the full
 * response object (not a pre-normalized guess).
 */
api.interceptors.response.use(
  (response) => response,
  (error) => {
    // Log the real error during development.
    if (import.meta.env.DEV) {
      const resp = error.response;
      console.error('[API Error]', {
        url: error.config?.url,
        method: error.config?.method?.toUpperCase(),
        status: resp?.status,
        statusText: resp?.statusText,
        data: resp?.data,
        message: error.message,
      });
    }
    return Promise.reject(error);
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
 *
 * Call this in catch blocks — NOT in the interceptor — so each caller decides
 * how to present the error.
 */
export function normalizeError(error) {
  // If this is already a normalized error (has `status` but no `response`),
  // return it as-is to avoid double-normalization.
  if (error && typeof error === 'object' && 'status' in error && !error.response) {
    return error;
  }

  const response = error?.response;

  if (!response) {
    return {
      message: 'Network error. Please check your connection and try again.',
      errors: null,
      status: 0,
    };
  }

  const body = response.data || {};
  return {
    message: body.message || `Server error (${response.status}).`,
    errors: body.data && typeof body.data === 'object' ? body.data : null,
    status: response.status,
  };
}

export default api;
