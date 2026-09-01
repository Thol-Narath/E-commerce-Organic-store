/**
 * Format a numeric value as a currency string.
 * Prices from the API are already decimal strings, so parse them defensively.
 */
export function formatPrice(value) {
  const number = Number(value);
  if (Number.isNaN(number)) return '—';
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
  }).format(number);
}

/**
 * Format an ISO date string into a readable date.
 */
export function formatDate(value) {
  if (!value) return '—';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return '—';
  return date.toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  });
}

/**
 * Round a discount percentage down to a whole number, or null when there is
 * no compare-at price or the product is not actually discounted.
 */
export function discountPercent(price, compareAtPrice) {
  const priceNum = Number(price);
  const compareNum = Number(compareAtPrice);
  if (!compareAtPrice || Number.isNaN(priceNum) || Number.isNaN(compareNum) || compareNum <= 0) {
    return null;
  }
  const percent = Math.round((1 - priceNum / compareNum) * 100);
  return percent > 0 ? percent : null;
}