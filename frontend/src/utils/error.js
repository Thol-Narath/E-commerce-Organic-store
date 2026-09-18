import { normalizeError } from '../services/api';

/**
 * Convert any Axios/API error into a single user-friendly message.
 * Also exposes the field-error map and HTTP status for advanced handling.
 */
export function getErrorMessage(error, fallback = 'Something went wrong. Please try again.') {
  const normalized = normalizeError(error);
  if (normalized.message) return normalized.message;

  // If there's no top-level message, fall back to the first field error.
  const errors = normalized.errors;
  if (errors && typeof errors === 'object') {
    const first = Object.values(errors).flat().find(Boolean);
    if (first) return first;
  }

  return fallback;
}