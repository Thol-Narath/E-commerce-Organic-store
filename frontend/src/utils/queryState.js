/**
 * Drop empty values from a query-parameter object so the URL stays clean.
 */
export function cleanParams(params) {
  const result = {};
  Object.entries(params).forEach(([key, value]) => {
    if (value !== undefined && value !== null && value !== '') {
      result[key] = value;
    }
  });
  return result;
}

/**
 * Build a fresh params object from the current URLSearchParams, updating one
 * key (or removing it when the value is empty).
 */
export function buildParams(searchParams, key, value) {
  const params = {};
  searchParams.forEach((paramValue, paramKey) => {
    params[paramKey] = paramValue;
  });

  if (value !== undefined && value !== null && value !== '') {
    params[key] = value;
  } else {
    delete params[key];
  }

  return cleanParams(params);
}

/**
 * Convert a URLSearchParams instance into a clean JS object.
 */
export function paramsToObject(searchParams) {
  const params = {};
  searchParams.forEach((value, key) => {
    params[key] = value;
  });
  return params;
}