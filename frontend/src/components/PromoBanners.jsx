import { Link } from 'react-router-dom';

const GROCERY_BAGS_IMAGE =
  'https://images.unsplash.com/photo-1542838132-92c53300491e?auto=format&fit=crop&w=600&q=80';
const GROCERY_BAGS_FALLBACK =
  'https://images.unsplash.com/photo-1579532537598-459ecdaf39cc?auto=format&fit=crop&w=600&q=80';

const FOOD_BOWL_IMAGE =
  'https://images.unsplash.com/photo-1512621776951-a57141f2eefd?auto=format&fit=crop&w=600&q=80';
const FOOD_BOWL_FALLBACK =
  'https://images.unsplash.com/photo-1493770348161-369560ae357d?auto=format&fit=crop&w=600&q=80';

/**
 * Two side-by-side rounded promotional banner cards (EasyMart-style).
 *
 * Left: warm peach background, "everyday fresh" heading, orange Shop Now,
 * photo of a person holding grocery bags on the right.
 * Right: green background, "healthy breakfast" heading, orange Shop Now,
 * photo of a prepared food dish on the right with a soft brush-stroke blob.
 *
 * Banners stack vertically on mobile.
 */
export default function PromoBanners() {
  return (
    <section className="section-promo py-5">
      <div className="container-lg">
        <div className="row g-4 promo-row">
          {/* Left: peach banner */}
          <div className="col-12 col-md-6">
            <div className="promo-card promo-card-peach">
              <div className="promo-card-body">
                <span className="promo-badge">Promo 1</span>
                <h3 className="promo-title">Everyday fresh &amp; clean with our products</h3>
                <Link to="/shop" className="btn btn-custom-orange promo-btn">
                  Shop Now
                </Link>
              </div>
              <div className="promo-card-media">
                <div className="promo-blob promo-blob-peach" aria-hidden="true" />
                <img
                  src={GROCERY_BAGS_IMAGE}
                  alt="Customer holding fresh grocery bags"
                  className="promo-img"
                  loading="lazy"
                  onError={(e) => {
                    e.target.onerror = null;
                    e.target.src = GROCERY_BAGS_FALLBACK;
                  }}
                />
              </div>
            </div>
          </div>

          {/* Right: green banner */}
          <div className="col-12 col-md-6">
            <div className="promo-card promo-card-green">
              <div className="promo-card-body">
                <span className="promo-badge">Promo 2</span>
                <h3 className="promo-title">Make your breakfast healthy and easy</h3>
                <Link to="/shop" className="btn btn-custom-orange promo-btn">
                  Shop Now
                </Link>
              </div>
              <div className="promo-card-media">
                <div className="promo-blob promo-blob-green" aria-hidden="true" />
                <img
                  src={FOOD_BOWL_IMAGE}
                  alt="Prepared healthy breakfast dish"
                  className="promo-img"
                  loading="lazy"
                  onError={(e) => {
                    e.target.onerror = null;
                    e.target.src = FOOD_BOWL_FALLBACK;
                  }}
                />
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>
  );
}
