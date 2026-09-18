const EMBEDDABLE_HOST_RE = /(^|\.)google\.com$/;

export function isEmbeddableMapUrl(url) {
  if (!url || typeof url !== 'string') return false;
  let parsed;
  try {
    parsed = new URL(url.trim());
  } catch {
    return false;
  }
  if (parsed.protocol !== 'https:') return false;
  if (!EMBEDDABLE_HOST_RE.test(parsed.hostname.toLowerCase())) return false;
  return parsed.pathname.includes('/maps/embed') || parsed.searchParams.get('output') === 'embed';
}

export function buildMapSrc({ latitude, longitude, embedUrl }) {
  const custom = (embedUrl || '').trim();
  if (custom && isEmbeddableMapUrl(custom)) return custom;
  const lat = Number.parseFloat(String(latitude));
  const lng = Number.parseFloat(String(longitude));
  if (Number.isFinite(lat) && Number.isFinite(lng) && lat >= -90 && lat <= 90 && lng >= -180 && lng <= 180) {
    return `https://maps.google.com/maps?q=${encodeURIComponent(`${lat},${lng}`)}&t=m&z=15&ie=UTF8&iwloc=&output=embed`;
  }
  return null;
}

export function mapSourceHint({ latitude, longitude, embedUrl }) {
  const custom = (embedUrl || '').trim();
  if (custom && isEmbeddableMapUrl(custom)) return 'Using your custom Google Maps embed URL.';
  if (!custom) return 'Coordinates-based embed is used because no embed URL is set.';
  const lat = Number.parseFloat(String(latitude));
  const lng = Number.parseFloat(String(longitude));
  if (Number.isFinite(lat) && Number.isFinite(lng) && lat >= -90 && lat <= 90 && lng >= -180 && lng <= 180) {
    return 'This link cannot be embedded (only Google "Embed a map" URLs are allowed), so the map uses your coordinates instead.';
  }
  return 'This link cannot be embedded and no coordinates are set, so the map cannot be shown.';
}