import { useRef } from 'react';
import { Link } from 'react-router-dom';

const SCROLL_STEP = 300;

/**
 * Horizontal scrollable row of category photos (real food photography).
 *
 * Each item is a circular frame showing a real, cleanly-cropped food photo on
 * top (object-fit: cover) with a thin colored ring. The row scrolls
 * horizontally (with snap) on all screens and desktop-only arrow buttons
 * scroll it by a fixed amount.
 */
export default function CategoryScroll({ categories }) {
  const items = categories?.length ? categories.slice(0, 10) : [];
  const scrollRef = useRef(null);

  if (items.length === 0) return null;

  const scrollBy = (direction) => {
    const el = scrollRef.current;
    if (!el) return;
    el.scrollBy({ left: direction * SCROLL_STEP, behavior: 'smooth' });
  };

  return (
    <div className="category-scroll-wrap">
      <button
        type="button"
        className="category-scroll-arrow category-scroll-arrow-prev"
        aria-label="Scroll categories left"
        onClick={() => scrollBy(-1)}
      >
        <span aria-hidden="true">&#8249;</span>
      </button>

      <div className="category-scroll" role="list" ref={scrollRef}>
        {items.map((category) => {
          const iconSrc = category.icon_url || '/category-icons/category.svg';
          return (
            <Link
              key={category.id}
              to={`/categories/${category.slug}`}
              role="listitem"
              className="category-scroll-item text-decoration-none text-center"
            >
              <span className="category-scroll-icon">
                <img
                  src={iconSrc}
                  alt={category.name}
                  className="category-scroll-icon-img"
                  loading="lazy"
                />
              </span>
              <span className="category-scroll-name">{category.name}</span>
            </Link>
          );
        })}
      </div>

      <button
        type="button"
        className="category-scroll-arrow category-scroll-arrow-next"
        aria-label="Scroll categories right"
        onClick={() => scrollBy(1)}
      >
        <span aria-hidden="true">&#8250;</span>
      </button>
    </div>
  );
}