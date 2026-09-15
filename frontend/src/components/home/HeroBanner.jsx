import { useState, useEffect, useCallback, useRef } from 'react';
import { Link } from 'react-router-dom';
import { Container } from 'react-bootstrap';
import { ArrowRightIcon, ChevronLeftIcon, ChevronRightIcon, LeafIcon } from '../../assets/icons';
import { bannerService } from '../../services/bannerService';

const FALLBACK_BANNERS = [
  {
    id: 0,
    title: 'Fresh Organic Goodness, Delivered to Your Door',
    subtitle: 'Discover fresh vegetables, fruits, and everyday essentials sourced with quality in mind.',
    discount_badge: '100% Organic',
    bg_color: '#e7f6ea',
    target_url: '/shop',
    cta_label: 'Shop Fresh Products',
    image_url: null,
  },
  {
    id: 1,
    title: 'Farm-Fresh Produce Picked at Peak Ripeness',
    subtitle: 'Hand-selected by our growers every morning and delivered to your table the same week.',
    discount_badge: 'Weekly Deals',
    bg_color: '#f3faf5',
    target_url: '/promotions',
    cta_label: 'View Promotions',
    image_url: null,
  },
];

const SLIDE_INTERVAL = 5000;
const TRANSITION_MS = 640;

// "#f97316" -> "249, 115, 22" (returns null for invalid/empty input)
const hexToRgb = (hex) => {
  let value = String(hex || '').trim().replace('#', '');
  if (!value) return null;
  if (value.length === 3) value = value.split('').map((c) => `${c}${c}`).join('');
  if (!/^[0-9a-fA-F]{6}$/.test(value)) return null;
  const n = parseInt(value, 16);
  return `${(n >> 16) & 255}, ${(n >> 8) & 255}, ${n & 255}`;
};

function HeroSlide({ banner }) {
  const badge = banner.discount_badge || (banner.discount_percent ? `${banner.discount_percent}% OFF` : null);
  const ctaUrl = banner.target_url || banner.link_url || '/shop';
  const ctaLabel = banner.cta_label || 'Shop Fresh Products';
  const hasImage = !!banner.image_url;

  return (
    <>
      <div className="hero-banner-content hero-slide-content">
        {badge && (
          <span className="hero-discount-badge">
            <LeafIcon size={13} />
            {badge}
          </span>
        )}
        <h1 className="hero-banner-title">{banner.title}</h1>
        {banner.subtitle && (
          <p className="hero-banner-subtitle">{banner.subtitle}</p>
        )}
        <div className="hero-banner-cta-row">
          <Link to={ctaUrl} className="btn btn-hero-primary">
            {ctaLabel} <ArrowRightIcon size={16} />
          </Link>
          <Link to="/categories" className="btn btn-hero-secondary">
            Explore Categories
          </Link>
        </div>
      </div>

      <div className="hero-banner-image-col hero-slide-content">
        {hasImage ? (
          <img src={banner.image_url} alt={banner.title} className="hero-banner-photo" />
        ) : (
          <div className="hero-banner-fruit-art" aria-hidden="true">
            <span className="hero-art-ring hero-art-ring-1" />
            <span className="hero-art-ring hero-art-ring-2" />
            <span className="hero-art-leaf hero-art-leaf-1"><LeafIcon size={34} /></span>
            <span className="hero-art-leaf hero-art-leaf-2"><LeafIcon size={26} /></span>
            <LeafIcon size={96} className="hero-art-main-leaf" />
          </div>
        )}
      </div>
    </>
  );
}

export default function HeroBanner() {
  const [banners, setBanners] = useState(FALLBACK_BANNERS);
  const [current, setCurrent] = useState(0);
  const [transitionTo, setTransitionTo] = useState(null);
  const [direction, setDirection] = useState('next');
  const [isPaused, setIsPaused] = useState(false);
  const timerRef = useRef(null);

  useEffect(() => {
    let cancelled = false;
    bannerService.getBanners().then((data) => {
      if (!cancelled && data?.length) setBanners(data);
    }).catch(() => {});
    return () => { cancelled = true; };
  }, []);

  // Crossfade: render the outgoing slide (current) together with the
  // incoming one (transitionTo) until the animation finishes.
  const goTo = useCallback((index, dir) => {
    if (transitionTo != null) return;
    const target = (index + banners.length) % banners.length;
    if (target === current) return;
    setDirection(dir || (target > current ? 'next' : 'prev'));
    setTransitionTo(target);
    window.setTimeout(() => {
      setCurrent(target);
      setTransitionTo(null);
    }, TRANSITION_MS);
  }, [current, transitionTo, banners.length]);

  const next = useCallback(() => {
    goTo(current + 1, 'next');
  }, [current, goTo]);

  const prev = useCallback(() => {
    goTo(current - 1, 'prev');
  }, [current, goTo]);

  useEffect(() => {
    if (isPaused || banners.length <= 1) {
      clearInterval(timerRef.current);
      return;
    }
    timerRef.current = setInterval(next, SLIDE_INTERVAL);
    return () => clearInterval(timerRef.current);
  }, [isPaused, next, banners.length]);

  const currentBanner = banners[current] || banners[0];
  const outgoing = transitionTo != null ? banners[current] || banners[0] : null;
  const incoming = transitionTo != null ? banners[transitionTo] : null;
  const slideKey = transitionTo != null ? transitionTo : current;

  // Soft, admin-customizable gradient built from the banner's bg_color,
  // fading into white behind the product so the image stays the focus.
  const rgb = hexToRgb(currentBanner.bg_color);
  const bgStyle = rgb
    ? {
        background: `linear-gradient(110deg, rgba(${rgb}, 0.85) 0%, rgba(${rgb}, 0.3) 38%, rgba(255,255,255,0) 62%), #ffffff`,
      }
    : {
        background: 'linear-gradient(110deg, #F8FFF9 0%, #FFFFFF 55%, #F2FCF5 100%)',
      };

  return (
    <section
      className="hero-banner"
      style={bgStyle}
      onMouseEnter={() => setIsPaused(true)}
      onMouseLeave={() => setIsPaused(false)}
    >
      <Container>
        <div className="hero-banner-inner">
          {outgoing && (
            <div
              className={`hero-slide-layer hero-slide-layer-out hero-slide-${direction}`}
              key={`out-${current}`}
              aria-hidden="true"
            >
              <HeroSlide banner={outgoing} />
            </div>
          )}
          <div
            className={`hero-slide-layer hero-slide-layer-in hero-slide-${direction}`}
            key={`in-${slideKey}`}
          >
            <HeroSlide banner={incoming || currentBanner} />
          </div>
        </div>

        {banners.length > 1 && (
          <div className="hero-dots">
            {banners.map((b, i) => (
              <button
                key={b.id}
                type="button"
                className={`hero-dot ${i === current ? 'active' : ''}`}
                onClick={() => goTo(i, i > current ? 'next' : 'prev')}
                aria-label={`Go to slide ${i + 1}`}
              />
            ))}
            <div className="hero-progress-bar">
              <div
                className="hero-progress-fill"
                key={`progress-${current}-${isPaused}`}
                style={{ animationDuration: `${SLIDE_INTERVAL}ms` }}
              />
            </div>
          </div>
        )}

        {banners.length > 1 && (
          <>
            <button type="button" className="hero-arrow hero-arrow-prev" onClick={prev} aria-label="Previous banner">
              <ChevronLeftIcon size={20} />
            </button>
            <button type="button" className="hero-arrow hero-arrow-next" onClick={next} aria-label="Next banner">
              <ChevronRightIcon size={20} />
            </button>
          </>
        )}
      </Container>
    </section>
  );
}