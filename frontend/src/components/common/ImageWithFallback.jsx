import { useState } from 'react';
import { LeafIcon } from '../../assets/icons';

/**
 * Image that gracefully falls back to a branded leaf placeholder when the src
 * is missing or fails to load (e.g. broken storage URLs).
 */
export default function ImageWithFallback({ src, alt = '', className, placeholderClassName = '' }) {
  const [failed, setFailed] = useState(false);

  if (!src || failed) {
    return (
      <div className={`image-fallback d-flex align-items-center justify-content-center ${placeholderClassName}`} role="img" aria-label={alt || 'Image unavailable'}>
        <LeafIcon className="image-fallback-icon" />
      </div>
    );
  }

  return (
    <img
      src={src}
      alt={alt}
      loading="lazy"
      className={className}
      onError={() => setFailed(true)}
    />
  );
}