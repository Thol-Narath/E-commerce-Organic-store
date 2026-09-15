import { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { ArrowRightIcon, LeafIcon } from '../../assets/icons';
import { bannerService } from '../../services/bannerService';
import SectionHeader from '../common/SectionHeader';

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
      <div className="container-lg px-lg-4">
        <SectionHeader title="Special Offers" subtitle="Fresh picks, better prices" />
        <div className="promo-row-grid">
          {promos.map((promo) => {
            const link = promo.link_url || promo.target_url || '/shop';
            const label = promo.cta_label || promo.cta_text || 'Shop Now';
            const discount = promo.discount_percent || promo.discount_badge;

            return (
              <Link
                key={promo.id}
                to={link}
                className={`promo-card-home${promo.image_url ? ' promo-card-home--img' : ''}`}
                aria-label={promo.title}
              >
                {discount && (
                  <span className="promo-home-badge">
                    <LeafIcon size={13} />
                    {typeof discount === 'number' ? `${discount}% OFF` : discount}
                  </span>
                )}
                {promo.image_url && (
                  <img
                    src={promo.image_url}
                    alt={promo.title}
                    className="promo-home-img"
                    loading="lazy"
                  />
                )}
                <div className="promo-card-home-body">
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