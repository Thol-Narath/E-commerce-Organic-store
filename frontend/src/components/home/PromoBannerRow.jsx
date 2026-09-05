import { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { bannerService } from '../../services/bannerService';

const COLORS = ['#f97316', '#16a34a', '#ea580c'];

export default function PromoBannerRow() {
  const [banners, setBanners] = useState([]);

  useEffect(() => {
    let cancelled = false;
    bannerService.getBanners().then((data) => {
      if (!cancelled) setBanners(data || []);
    }).catch(() => {});
    return () => { cancelled = true; };
  }, []);

  const promos = banners.slice(0, 3);
  if (promos.length === 0) return null;

  return (
    <section className="section-promo-row py-5">
      <div className="container-lg">
        <div className="promo-row-grid">
          {promos.map((promo, i) => (
            <div
              key={promo.id}
              className="promo-card-home"
              style={{ background: promo.bg_color || COLORS[i % COLORS.length] }}
            >
              <div className="promo-card-home-body">
                {promo.discount_percent && (
                  <span className="promo-home-badge">{promo.discount_percent}% OFF</span>
                )}
                <h3 className="promo-home-title">{promo.title}</h3>
                {promo.subtitle && (
                  <p className="promo-home-subtitle">{promo.subtitle}</p>
                )}
                <Link to={promo.link_url || '/shop'} className="btn btn-white btn-sm promo-home-btn">
                  Shop Now
                </Link>
              </div>
            </div>
          ))}
        </div>
      </div>
    </section>
  );
}
