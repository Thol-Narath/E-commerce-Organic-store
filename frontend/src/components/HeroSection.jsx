import { Link } from 'react-router-dom';

const DEFAULT_STATS = { avgRating: 4.8, totalReviews: 128 };

const VEGETABLE_BASKET_IMAGE =
  'https://images.unsplash.com/photo-1610348725531-843dff563e2c?auto=format&fit=crop&w=800&q=80';

const VEGETABLE_BASKET_FALLBACK =
  'https://upload.wikimedia.org/wikipedia/commons/2/2f/Culinary_fruits_front_view.png';

/**
 * Two-column hero banner styled to match the EasyMart-style reference.
 *
 * Left column: pill badge, 3-line mixed-color heading, single "Shop Now"
 * button, and a compact customer-rating row (overlapping avatars + stars +
 * review count) with graceful fallback when there are no reviews.
 *
 * Right column: a rounded rectangular photo of a wicker basket overflowing
 * with fresh vegetables, with a soft blurry light-green organic blob behind
 * it for visual interest.
 */
export default function HeroSection({ stats = DEFAULT_STATS }) {
  const avgRating = Number.isFinite(Number(stats?.avgRating)) ? Number(stats.avgRating) : DEFAULT_STATS.avgRating;
  const totalReviews = Number.isFinite(Number(stats?.totalReviews))
    ? Number(stats.totalReviews)
    : DEFAULT_STATS.totalReviews;

  const ratingLabel = totalReviews > 0 ? avgRating.toFixed(1) : 'New';

  return (
    <section className="hero hero-split py-4 py-md-5">
      <div className="container-lg hero-split-inner align-items-center">
        {/* Left content column */}
        <div className="hero-split-content">
          <span className="hero-chip">
            <span className="hero-chip-dot" aria-hidden="true" />
            Grocery Delivery Service
          </span>

          <h1 className="hero-title hero-split-title">
            <span className="hero-title-accent-green">Fastest</span>{' '}
            <span className="hero-title-accent-yellow">Delivery &amp;</span>
            <br />
            <span className="hero-title-accent-green">Easy Pickup.</span>
          </h1>

          <p className="hero-subtitle">
            Fresh organic produce and everyday staples, delivered right to your door.
          </p>

          {/* Single primary action */}
          <div className="hero-actions">
            <Link to="/shop" className="btn btn-custom-orange">
              Shop Now
            </Link>
          </div>

          {/* Dynamic real customer rating (graceful fallback) */}
          <div className="hero-rating">
            <div className="hero-avatars-initials">
              <span className="hero-avatar-initial hero-avatar-amber">R</span>
              <span className="hero-avatar-initial hero-avatar-green">S</span>
              <span className="hero-avatar-initial hero-avatar-blue">A</span>
            </div>
            <div className="hero-rating-text">
              <p className="hero-rating-title">Our Happy Customer</p>
              <p className="hero-rating-stars">
                ★ {ratingLabel}{' '}
                <span className="hero-rating-count">
                  {totalReviews > 0 ? `(${totalReviews} Reviews)` : '(Be the first to review)'}
                </span>
              </p>
            </div>
          </div>
        </div>

        {/* Right column: rounded basket photo + soft blob backdrop */}
        <div className="hero-split-image-col">
          <div className="hero-split-blob" aria-hidden="true" />
          <div className="hero-basket-frame">
            <img
              src={VEGETABLE_BASKET_IMAGE}
              alt="Fresh Organic Vegetables"
              className="hero-basket-photo"
              loading="eager"
              onError={(e) => {
                e.target.onerror = null;
                e.target.src = VEGETABLE_BASKET_FALLBACK;
                e.target.className = 'hero-basket-photo hero-basket-photo-cutout';
              }}
            />
          </div>
        </div>
      </div>
    </section>
  );
}
