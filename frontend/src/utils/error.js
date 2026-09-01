import { normalizeError } from '../services/api';

/**
 * Convert any Axios/API error into a single user-friendly message.
 * Also exposes the field-error map and HTTP status for advanced handling.
 */
export function getErrorMessage(error, fallback = 'Something went wrong. Please try again.') {
  const normalized = normalizeError(error);
  return normalized.message || fallback;
}