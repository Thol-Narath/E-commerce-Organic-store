import { useState, useEffect, useCallback, useRef } from 'react';
import { Link } from 'react-router-dom';
import { Container } from 'react-bootstrap';
import { ArrowRightIcon, LeafIcon } from '../../assets/icons';
import { bannerService } from '../../services/bannerService';

const FALLBACK_BANNERS = [
  {
    id: 0,
    title: 'Fresh Organic Vegetables',
    subtitle: 'Farm to table goodness delivered to your door',
    discount_badge: '25% OFF',
    bg_color: '#f97316',
    target_url: '/shop',
    cta_label: 'Shop Now',
    image_url: null,
  },
];

const SLIDE_INTERVAL = 5000;

export default function HeroBanner() {
  const [banners, setBanners] = useState(FALLBACK_BANNERS);
  const [current, setCurrent] = useState(0);
  const [isPaused, setIsPaused] = useState(false);
  const [isTransitioning, setIsTransitioning] = useState(false);
  const [direction, setDirection] = useState('next');
  const timerRef = useRef(null);

  useEffect(() => {
    let cancelled = false;
    bannerService.getBanners().then((data) => {
      if (!cancelled && data?.length) setBanners(data);
    }).catch(() => {});
    return () => { cancelled = true; };
  }, []);

  const goTo = useCallback((index, dir) => {
    if (isTransitioning) return;
    setDirection(dir || (index > current ? 'next' : 'prev'));
    setIsTransitioning(true);
    setTimeout(() => {
      setCurrent(index);
      setTimeout(() => setIsTransitioning(false), 50);
    }, 300);
  }, [current, isTransitioning]);

  const next = useCallback(() => {
    const nextIdx = current === banners.length - 1 ? 0 : current + 1;
    goTo(nextIdx, 'next');
  }, [current, banners.length, goTo]);

  const prev = useCallback(() => {
    const prevIdx = current === 0 ? banners.length - 1 : current - 1;
    goTo(prevIdx, 'prev');
  }, [current, banners.length, goTo]);

  useEffect(() => {
    if (isPaused || banners.length <= 1) {
      clearInterval(timerRef.current);
      return;
    }
    timerRef.current = setInterval(next, SLIDE_INTERVAL);
    return () => clearInterval(timerRef.current);
  }, [isPaused, next, banners.length]);

  const banner = banners[current] || banners[0];
  const badge = banner.discount_badge || (banner.discount_percent ? `${banner.discount_percent}% OFF` : null);
  const ctaUrl = banner.target_url || banner.link_url || '/shop';
  const ctaLabel = banner.cta_label || 'Shop Now';
  const hasImage = !!banner.image_url;

  const bgStyle = { background: banner.bg_color || '#f97316' };

  return (
    <section
      className="hero-banner"
      style={bgStyle}
      onMouseEnter={() => setIsPaused(true)}
      onMouseLeave={() => setIsPaused(false)}
    >
      <Container>
        <div className="hero-banner-inner align-items-center">
          <div
            className={`hero-banner-content hero-slide-content ${
              isTransitioning ? `hero-slide-${direction}-out` : `hero-slide-${direction}-in`
            }`}
            key={`content-${current}`}
          >
            {badge && (
              <span className="hero-discount-badge">
                {badge}
              </span>
            )}
            <h1 className="hero-banner-title">{banner.title}</h1>
            {banner.subtitle && (
              <p className="hero-banner-subtitle">{banner.subtitle}</p>
            )}
            <Link to={ctaUrl} className="btn btn-white hero-banner-cta">
              {ctaLabel} <ArrowRightIcon size={16} />
            </Link>
          </div>

          <div
            className={`hero-banner-image-col hero-slide-content ${
              isTransitioning ? `hero-slide-${direction}-out` : `hero-slide-${direction}-in`
            }`}
            key={`image-${current}`}
          >
            {hasImage ? (
              <img src={banner.image_url} alt={banner.title} className="hero-banner-photo" />
            ) : (
              <div className="hero-banner-fruit-placeholder" aria-hidden="true">
                <LeafIcon size={72} className="hero-fruit-emoji" />
              </div>
            )}
          </div>
        </div>

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
          {banners.length > 1 && (
            <div className="hero-progress-bar">
              <div
                className="hero-progress-fill"
                key={`progress-${current}-${isPaused}`}
                style={{ animationDuration: `${SLIDE_INTERVAL}ms` }}
              />
            </div>
          )}
        </div>

        <button type="button" className="hero-arrow hero-arrow-prev" onClick={prev} aria-label="Previous banner">
          &#8249;
        </button>
        <button type="button" className="hero-arrow hero-arrow-next" onClick={next} aria-label="Next banner">
          &#8250;
        </button>
      </Container>
    </section>
  );
}
