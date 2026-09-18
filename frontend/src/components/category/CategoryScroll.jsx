import { useEffect, useRef } from 'react';
import { Link } from 'react-router-dom';

const SCROLL_STEP = 300;
const SCROLL_SPEED = 50;
const WRAP_DURATION = 900;
const RESUME_DELAY = 4000;

/**
 * Horizontal scrollable row of category photos (real food photography).
 *
 * Each item is a circular frame showing a real, cleanly-cropped food photo on
 * top (object-fit: cover) with a thin colored ring. The row scrolls
 * horizontally (with snap) on all screens and desktop-only arrow buttons
 * scroll it by a fixed amount.
 *
 * Auto-scroll: a requestAnimationFrame loop drifts the row continuously at
 * SCROLL_SPEED px/s; when it reaches the end it glides back to the start with
 * an ease-in-out wrap. The drift pauses while hovered/touched/clicked/scrolled,
 * and resumes shortly after the user stops interacting. Disabled for users who
 * prefer reduced motion.
 */
export default function CategoryScroll({ categories }) {
  const items = categories?.length ? categories.slice(0, 10) : [];
  const scrollRef = useRef(null);
  const pausedRef = useRef(false);
  const pauseTimerRef = useRef(null);
  const rafRef = useRef(null);

  useEffect(() => {
    const el = scrollRef.current;
    if (!el || items.length === 0) return undefined;

    if (window.matchMedia?.('(prefers-reduced-motion: reduce)').matches) return undefined;

    const pause = () => {
      pausedRef.current = true;
      window.clearTimeout(pauseTimerRef.current);
      pauseTimerRef.current = window.setTimeout(() => {
        pausedRef.current = false;
      }, RESUME_DELAY);
    };

    const easeInOutCubic = (t) => (t < 0.5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2);

    let last = null;
    const wrap = { active: false, from: 0, elapsed: 0 };

    const frame = (now) => {
      if (!pausedRef.current) {
        const dt = last ? (now - last) / 1000 : 0;
        const maxScroll = el.scrollWidth - el.clientWidth;
        if (maxScroll > 0) {
          if (wrap.active) {
            wrap.elapsed += dt * 1000;
            const t = Math.min(1, wrap.elapsed / WRAP_DURATION);
            el.scrollLeft = wrap.from * (1 - easeInOutCubic(t));
            if (t >= 1) wrap.active = false;
          } else {
            el.scrollLeft = Math.min(el.scrollLeft + SCROLL_SPEED * dt, maxScroll);
            if (el.scrollLeft >= maxScroll) {
              wrap.active = true;
              wrap.from = el.scrollLeft;
              wrap.elapsed = 0;
            }
          }
        }
        last = now;
      } else {
        last = null;
      }
      rafRef.current = requestAnimationFrame(frame);
    };

    rafRef.current = requestAnimationFrame(frame);
    const pauseEvents = ['mouseenter', 'pointerdown', 'focusin', 'touchstart', 'wheel'];
    pauseEvents.forEach((type) => el.addEventListener(type, pause, { passive: true }));

    return () => {
      cancelAnimationFrame(rafRef.current);
      window.clearTimeout(pauseTimerRef.current);
      pauseEvents.forEach((type) => el.removeEventListener(type, pause));
    };
  }, [items.length]);

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