import { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { ArrowRightIcon } from '../../assets/icons';
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
          {promos.map((promo, i) => {
            const bg = promo.bg_color || COLORS[i % COLORS.length];
            const link = promo.link_url || promo.target_url || '/shop';
            const label = promo.cta_label || promo.cta_text || 'Shop Now';
            const discount = promo.discount_percent || promo.discount_badge;

            return (
              <Link
                key={promo.id}
                to={link}
                className="promo-card-home"
                style={{ background: bg }}
                aria-label={promo.title}
              >
                {discount && (
                  <span className="promo-home-discount" aria-hidden="true">
                    {typeof discount === 'number' ? `${discount}%` : discount}
                  </span>
                )}
                <div className="promo-card-home-body">
                  {discount && (
                    <span className="promo-home-badge">
                      {typeof discount === 'number' ? `${discount}% OFF` : discount}
                    </span>
                  )}
                  <h3 className="promo-home-title">{promo.title}</h3>
                  {promo.subtitle && (
                    <p className="promo-home-subtitle">{promo.subtitle}</p>
                  )}
                  <span className="promo-home-btn">
                    {label} <ArrowRightIcon size={16} />
                  </span>
                </div>
              </Link>
            );
          })}
        </div>
      </div>
    </section>
  );
}