import { useState } from 'react';
import ImageWithFallback from '../common/ImageWithFallback';

/**
 * Responsive product image gallery with thumbnail selection.
 * Falls back to a branded placeholder when no images exist.
 */
export default function ProductGallery({ images = [], name = 'Product' }) {
  const [active, setActive] = useState(0);

  const galleryImages = Array.isArray(images) && images.length ? images : null;

  if (!galleryImages) {
    return (
      <div className="product-gallery product-gallery-single rounded">
        <ImageWithFallback
          src={null}
          alt={name}
          className="w-100 h-100"
          placeholderClassName="h-100 w-100"
        />
      </div>
    );
  }

  const currentIndex = Math.min(active, galleryImages.length - 1);
  const current = galleryImages[currentIndex];

  return (
    <div className="product-gallery">
      <div className="product-gallery-main rounded">
        <ImageWithFallback src={current?.url} alt={current?.alt_text || name} className="w-100 h-100" />
      </div>

      {galleryImages.length > 1 && (
        <div className="product-gallery-thumbs d-flex gap-2 mt-3 flex-wrap" role="group" aria-label="Product images">
          {galleryImages.map((image, index) => (
            <button
              key={image.id || index}
              type="button"
              className={`product-gallery-thumb rounded ${index === currentIndex ? 'active' : ''}`}
              onClick={() => setActive(index)}
              aria-label={`View image ${index + 1} of ${galleryImages.length}`}
              aria-pressed={index === currentIndex}
            >
              <ImageWithFallback src={image?.url} alt={image?.alt_text || `${name} image ${index + 1}`} className="w-100 h-100" />
            </button>
          ))}
        </div>
      )}
    </div>
  );
}