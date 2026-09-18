import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { Button, Col, Container, Row } from 'react-bootstrap';
import usePageTitle from '../hooks/usePageTitle';
import { settingsService } from '../services/settingsService';
import { statsService } from '../services/statsService';
import { buildMapSrc } from '../utils/maps';
import {
  ArrowRightIcon,
  BoxesIcon,
  CheckCircleIcon,
  ClockIcon,
  ExternalLinkIcon,
  HeartIcon,
  LeafIcon,
  MailIcon,
  MapPinIcon,
  PhoneIcon,
  ShieldIcon,
  StarIcon,
  StoreIcon,
  TruckIcon,
} from '../assets/icons';

const DEFAULT_STORY_IMAGE = '/storage/category-icons/fruits.jpg';
const DEFAULT_QUALITY_IMAGE = '/storage/category-icons/leafy-greens.jpg';

const HERO_SLIDE_INTERVAL = 4500;

const HERO_SLIDES = [
  { src: '/storage/category-icons/vegetables.jpg', alt: 'Fresh organic vegetables at Delicacy Organic' },
  { src: '/storage/category-icons/fruits.jpg', alt: 'Organic fruit at Delicacy Organic' },
  { src: '/storage/category-icons/leafy-greens.jpg', alt: 'Organic leafy greens at Delicacy Organic' },
  { src: '/storage/category-icons/citrus.jpg', alt: 'Fresh organic citrus at Delicacy Organic' },
];

const PILLARS = [
  {
    icon: LeafIcon,
    title: 'Organic by nature',
    text: 'Our catalog focuses on produce grown without synthetic pesticides or artificial additives, so shoppers can feel good about what they bring home.',
  },
  {
    icon: TruckIcon,
    title: 'Freshness first',
    text: 'Products are kept carefully stocked and handled. The store prioritizes freshness at every step of the journey.',
  },
  {
    icon: ShieldIcon,
    title: 'Quality you can trust',
    text: 'Every item is selected and checked so customers can shop with confidence, backed by transparent product information.',
  },
  {
    icon: StoreIcon,
    title: 'A modern shopping experience',
    text: 'A clean, responsive storefront makes browsing organic essentials fast and enjoyable on any device.',
  },
];

const TRUST_POINTS = [
  {
    title: 'Carefully selected products',
    text: 'Every item in the store is intentionally curated so you can shop with confidence.',
  },
  {
    title: 'Fresh products',
    text: 'Stock is managed and reviewed closely to keep quality high from delivery to checkout.',
  },
  {
    title: 'Transparent product information',
    text: 'Clear names, descriptions and pricing are calculated by the store every single time.',
  },
];

function SectionLabel({ children }) {
  return <span className="about-p-label">{children}</span>;
}

export default function AboutPage() {
  usePageTitle('About Us');

  useEffect(() => {
    const meta = document.querySelector('meta[name="description"]');
    const previous = meta?.getAttribute('content') || '';
    if (meta) {
      meta.setAttribute(
        'content',
        'Learn more about Delicacy Organic, our commitment to quality, freshness, and a better organic shopping experience.',
      );
    }
    return () => {
      if (meta) meta.setAttribute('content', previous);
    };
  }, []);

  const [settings, setSettings] = useState(null);
  const [stats, setStats] = useState(null);
  const [heroIndex, setHeroIndex] = useState(0);
  const [heroPaused, setHeroPaused] = useState(false);

  useEffect(() => {
    let cancelled = false;
    settingsService
      .publicSettings()
      .then((data) => {
        if (!cancelled) setSettings(data);
      })
      .catch(() => {});
    statsService
      .getStats()
      .then((data) => {
        if (!cancelled) setStats(data);
      })
      .catch(() => {});
    return () => {
      cancelled = true;
    };
  }, []);

  const location = settings?.location || {};
  const aboutImage = settings?.about?.image_url || null;
  const storeName = location.name || 'Delicacy Organic';
  const mapSrc = buildMapSrc({
    latitude: location.latitude,
    longitude: location.longitude,
    embedUrl: location.google_maps_embed_url,
  });

  const heroImages = aboutImage
    ? [{ src: aboutImage, alt: 'Fresh organic produce curated by Delicacy Organic' }, ...HERO_SLIDES.filter((s) => s.src !== aboutImage)]
    : HERO_SLIDES;

  useEffect(() => {
    if (heroPaused || heroImages.length <= 1) return;
    const id = window.setInterval(() => setHeroIndex((i) => (i + 1) % heroImages.length), HERO_SLIDE_INTERVAL);
    return () => window.clearInterval(id);
  }, [heroPaused, heroImages.length]);

  const highlights = [
    { icon: BoxesIcon, value: stats ? String(stats.active_products ?? 0) : 'Fresh', label: 'Products' },
    { icon: StoreIcon, value: stats ? String(stats.active_categories ?? 0) : 'Curated', label: 'Categories' },
    { icon: StarIcon, value: stats ? String(stats.total_reviews ?? 0) : 'Trusted', label: 'Reviews' },
    { icon: ClockIcon, value: '24/7', label: 'Support' },
  ];

  const contactItems = [
    { icon: MapPinIcon, title: 'Address', value: location.address || 'Store address coming soon.' },
    { icon: PhoneIcon, title: 'Phone', value: location.phone || 'Available soon.' },
    { icon: MailIcon, title: 'Email', value: location.email || 'Available soon.' },
    { icon: ClockIcon, title: 'Opening Hours', value: location.business_hours || 'Monday – Sunday, 8:00 AM – 8:00 PM' },
  ];

  return (
    <div className="about-page">
      {/* ---------- 1. Hero / Brand introduction ---------- */}
      <section className="about-hero">
        <Container>
          <Row className="align-items-center g-4 g-lg-5">
            <Col lg={6}>
              <SectionLabel>About {storeName}</SectionLabel>
              <h1 className="about-hero-title">
                Freshness You Can Trust, <span className="text-success">Every Day.</span>
              </h1>
              <p className="about-hero-text">
                We bring carefully selected organic products from trusted sources directly to
                your table — a modern way to shop for the food you and your family deserve.
              </p>
              <div className="d-flex flex-wrap gap-2 mt-4">
                <Button as={Link} to="/shop" variant="success" className="btn-organic-glow text-white">
                  Shop Products <ArrowRightIcon size={21} className="ms-1 text-white" />
                </Button>
                <Button as={Link} to="/contact" variant="outline-success" className="btn-outline-organic">
                  Contact Us
                </Button>
              </div>
              <div className="about-hero-chips mt-4">
                <span><LeafIcon size={15} /> Organic focus</span>
                <span><ShieldIcon size={15} /> Trusted quality</span>
                <span><HeartIcon size={15} /> Built for your table</span>
              </div>
            </Col>
            <Col lg={6}>
              <div
                className="about-media-frame about-hero-media"
                onMouseEnter={() => setHeroPaused(true)}
                onMouseLeave={() => setHeroPaused(false)}
              >
                {heroImages.map((slide, i) => (
                  <img
                    key={i}
                    src={slide.src}
                    alt={slide.alt}
                    loading={i === 0 ? 'eager' : 'lazy'}
                    className={`about-media-img about-hero-slide ${i === heroIndex ? 'is-active' : ''}`}
                  />
                ))}
                {heroImages.length > 1 && (
                  <div className="about-hero-dots" role="tablist" aria-label="Story highlight gallery">
                    {heroImages.map((slide, i) => (
                      <button
                        key={i}
                        type="button"
                        className={`about-hero-dot ${i === heroIndex ? 'is-active' : ''}`}
                        aria-label={`Show slide ${i + 1}`}
                        onClick={() => setHeroIndex(i)}
                      />
                    ))}
                  </div>
                )}
                <div className="about-hero-badge">
                  <span className="about-hero-badge-num">100%</span>
                  <span className="about-hero-badge-text">fresh focus</span>
                </div>
              </div>
            </Col>
          </Row>
        </Container>
      </section>

      {/* ---------- 2. Our story ---------- */}
      <section className="about-section about-section-soft">
        <Container>
          <Row className="align-items-center g-4 g-lg-5">
            <Col lg={6}>
              <div className="about-media-frame about-story-media">
                <img src={DEFAULT_STORY_IMAGE} alt="Seasonal organic fruit at Delicacy Organic" loading="lazy" className="about-media-img" />
                <div className="about-story-card">
                  <LeafIcon size={20} className="text-success" />
                  <p className="mb-0">Grown &amp; curated with care</p>
                </div>
              </div>
            </Col>
            <Col lg={6}>
              <SectionLabel>Our Story</SectionLabel>
              <h2 className="about-h2">A store built around fresh, honest food</h2>
              <p className="about-body">
                Our mission is simple: make it easier for people to discover fresh, quality
                products they can trust. We combine careful sourcing with a modern storefront
                so every order is straightforward from browse to doorstep.
              </p>
              <p className="about-body">
                From seasonal produce to everyday essentials, we keep the same promise —
                quality products, transparent information, and an easy shopping experience.
              </p>
              <ul className="about-check-list">
                <li><CheckCircleIcon size={17} /> Sourced and refreshed regularly</li>
                <li><CheckCircleIcon size={17} /> Honest product information on every page</li>
                <li><CheckCircleIcon size={17} /> An experience designed for every device</li>
              </ul>
            </Col>
          </Row>
        </Container>
      </section>

      {/* ---------- 3. Why choose us ---------- */}
      <section className="about-section">
        <Container>
          <div className="text-center mb-4">
            <SectionLabel>Why Choose Us</SectionLabel>
            <h2 className="about-h2">Everything you need for a better shop</h2>
          </div>
          <Row className="g-4">
            {PILLARS.map((pillar) => (
              <Col key={pillar.title} md={6} lg={3}>
                <div className="about-pillar-card h-100">
                  <div className="about-pillar-icon">
                    <pillar.icon size={26} />
                  </div>
                  <h3 className="about-pillar-title">{pillar.title}</h3>
                  <p className="about-pillar-text">{pillar.text}</p>
                </div>
              </Col>
            ))}
          </Row>
        </Container>
      </section>

      {/* ---------- 4. Quality / trust ---------- */}
      <section className="about-section about-section-soft">
        <Container>
          <Row className="align-items-center g-4 g-lg-5">
            <Col lg={6}>
              <div className="about-media-frame about-quality-media">
                <img src={DEFAULT_QUALITY_IMAGE} alt="Organic leafy greens checked for quality" loading="lazy" className="about-media-img" />
              </div>
            </Col>
            <Col lg={6}>
              <SectionLabel>Quality &amp; Trust</SectionLabel>
              <h2 className="about-h2">Quality is at the heart of everything we do.</h2>
              <p className="about-body">
                We keep our catalog honest: only active, available products are shown, prices are
                always calculated by the store, and orders are preserved for the long term.
              </p>
              <div className="about-trust-list">
                {TRUST_POINTS.map((point) => (
                  <div key={point.title} className="about-trust-item">
                    <CheckCircleIcon size={20} className="about-trust-check" />
                    <div>
                      <h3 className="about-trust-title">{point.title}</h3>
                      <p className="about-trust-text mb-0">{point.text}</p>
                    </div>
                  </div>
                ))}
              </div>
            </Col>
          </Row>
        </Container>
      </section>

      {/* ---------- 5. Brand highlights ---------- */}
      <section className="about-highlights">
        <Container>
          <Row className="g-4 text-center">
            {highlights.map((item) => (
              <Col xs={6} lg={3} key={item.label}>
                <div className="about-highlight-item">
                  <item.icon size={24} className="about-highlight-icon" />
                  <div className="about-highlight-value">{item.value}</div>
                  <div className="about-highlight-label">{item.label}</div>
                </div>
              </Col>
            ))}
          </Row>
        </Container>
      </section>

      {/* ---------- 6. Our commitment ---------- */}
      <section className="about-section about-section-soft">
        <Container>
          <div className="about-commitment">
            <Row className="align-items-center g-4 g-lg-5">
              <Col lg={6}>
                <SectionLabel>Our Commitment</SectionLabel>
                <h2 className="about-h2">Better products. Better shopping. Better everyday choices.</h2>
              </Col>
              <Col lg={6}>
                <p className="about-body mb-3">
                  Everything we do points back to one goal: making healthy, dependable food
                  easier to find and easier to love.
                </p>
                <div className="about-commitment-chips">
                  <span><CheckCircleIcon size={15} /> Honest catalog</span>
                  <span><CheckCircleIcon size={15} /> Fair, transparent pricing</span>
                  <span><CheckCircleIcon size={15} /> Trusted ordering</span>
                </div>
              </Col>
            </Row>
          </div>
        </Container>
      </section>

      {/* ---------- 7. Store location + Google Map ---------- */}
      <section className="about-section">
        <Container>
          <div className="text-center mb-4">
            <SectionLabel>Visit Our Store</SectionLabel>
            <h2 className="about-h2">Find us on the map</h2>
          </div>
          <Row className="g-4 align-items-stretch">
            <Col lg={7}>
              <div className="about-map-frame">
                {mapSrc ? (
                  <iframe
                    src={mapSrc}
                    title="Delicacy Organic store location"
                    style={{ border: 0, width: '100%', height: '100%' }}
                    loading="lazy"
                    referrerPolicy="no-referrer-when-downgrade"
                    allowFullScreen
                  />
                ) : (
                  <div className="about-map-fallback">
                    <MapPinIcon size={34} className="text-success mb-3" />
                    <h3 className="about-map-fallback-title">Store location is currently unavailable.</h3>
                    <p className="about-map-fallback-text mb-0">
                      {location.google_maps_url
                        ? 'Open our location in Google Maps to find the store.'
                        : 'Check back soon — the store location will be published shortly.'}
                    </p>
                  </div>
                )}
              </div>
            </Col>
            <Col lg={5}>
              <div className="about-store-card h-100">
                <h3 className="about-store-name">{storeName}</h3>
                <p className="about-store-address mb-3">{location.address || 'Store address coming soon.'}</p>
                <div className="about-store-links">
                  {location.phone && (
                    <a href={`tel:${location.phone.replace(/[^+\d]/g, '')}`} className="about-store-link">
                      <PhoneIcon size={16} /> {location.phone}
                    </a>
                  )}
                  {location.email && (
                    <a href={`mailto:${location.email}`} className="about-store-link">
                      <MailIcon size={16} /> {location.email}
                    </a>
                  )}
                </div>
                <div className="about-store-hours">
                  <ClockIcon size={16} />
                  <div>
                    <span className="about-store-hours-label">Business hours</span>
                    <span className="about-store-hours-value">
                      {location.business_hours || 'Monday – Sunday, 8:00 AM – 8:00 PM'}
                    </span>
                  </div>
                </div>
                {location.google_maps_url && (
                  <a
                    href={location.google_maps_url}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="about-store-map-link"
                  >
                    Open in Google Maps <ExternalLinkIcon size={16} />
                  </a>
                )}
              </div>
            </Col>
          </Row>
        </Container>
      </section>

      {/* ---------- 8. Contact information ---------- */}
      <section className="about-section about-section-soft">
        <Container>
          <div className="text-center mb-4">
            <SectionLabel>Contact Information</SectionLabel>
            <h2 className="about-h2">We are here to help</h2>
          </div>
          <Row className="g-4">
            {contactItems.map((item) => (
              <Col key={item.title} md={6} lg={3}>
                <div className="about-contact-card h-100">
                  <div className="about-contact-icon">
                    <item.icon size={22} />
                  </div>
                  <h3 className="about-contact-title">{item.title}</h3>
                  <p className="about-contact-value">{item.value}</p>
                </div>
              </Col>
            ))}
          </Row>
        </Container>
      </section>

      {/* ---------- 9. Call to action ---------- */}
      <section className="about-cta">
        <Container className="text-center">
          <h2 className="about-cta-title">Ready to discover something fresh?</h2>
          <p className="about-cta-text">
            Explore our carefully selected organic products and start shopping with confidence today.
          </p>
          <div className="d-flex flex-wrap justify-content-center gap-2">
            <Button as={Link} to="/shop" variant="success" className="btn-organic-glow">
              Shop Now <ArrowRightIcon size={16} className="ms-1" />
            </Button>
            <Button as={Link} to="/contact" variant="outline-light" size="lg">
              Contact Us
            </Button>
          </div>
        </Container>
      </section>
    </div>
  );
}