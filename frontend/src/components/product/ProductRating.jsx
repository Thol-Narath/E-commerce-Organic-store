import { useState } from 'react';
import { StarIcon } from '../../assets/icons';

const STARS = [1, 2, 3, 4, 5];

/**
 * Star rating display (with partial-star fill) that can also act as an
 * interactive 1-5 rating input. Used on product cards so customers can rate
 * a product directly from the home page.
 */
export default function ProductRating({
  rating = 0,
  count = 0,
  interactive = false,
  onRate,
  size = 13,
  showCount = true,
  className = '',
}) {
  const [hovered, setHovered] = useState(0);
  const display = hovered || rating;
  const percent = Math.max(0, Math.min(100, (display / 5) * 100));

  if (interactive) {
    return (
      <div className={`product-rating product-rating-interactive ${className}`}>
        <span className="product-rating-stars" role="radiogroup" aria-label="Rate this product">
          {STARS.map((i) => (
            <button
              key={i}
              type="button"
              role="radio"
              aria-checked={display === i}
              aria-label={`Rate ${i} star${i > 1 ? 's' : ''}`}
              className={`product-rating-star-btn ${i <= display ? 'is-active' : ''}`}
              onMouseEnter={() => setHovered(i)}
              onMouseLeave={() => setHovered(0)}
              onClick={() => onRate?.(i)}
            >
              <StarIcon size={size} fill={i <= display ? 'currentColor' : 'none'} />
            </button>
          ))}
        </span>
        {showCount && count > 0 && <span className="product-rating-count">{count} review{count !== 1 ? 's' : ''}</span>}
      </div>
    );
  }

  return (
    <div className={`product-rating ${className}`}>
      <span className="product-rating-stars" aria-label={`${rating.toFixed(1)} out of 5 stars`}>
        <span className="product-rating-stars-bg" aria-hidden="true">
          {STARS.map((i) => <StarIcon key={i} size={size} />)}
        </span>
        <span className="product-rating-stars-fill" aria-hidden="true" style={{ width: `${percent}%` }}>
          {STARS.map((i) => <StarIcon key={i} size={size} fill="currentColor" />)}
        </span>
      </span>
      {showCount && count > 0 && (
        <span className="product-rating-count">{count} review{count !== 1 ? 's' : ''}</span>
      )}
      {showCount && count === 0 && (
        <span className="product-rating-empty">No ratings yet</span>
      )}
    </div>
  );
}