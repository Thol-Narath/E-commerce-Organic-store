import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { Container, Row, Col } from 'react-bootstrap';
import { LeafIcon, MailIcon, MapPinIcon, PhoneIcon } from '../../assets/icons';
import { settingsService } from '../../services/settingsService';

const QUICK_LINKS = [
  { label: 'Home', to: '/' },
  { label: 'Shop', to: '/shop' },
  { label: 'Best Sales', to: '/best-sales' },
  { label: 'Promotions', to: '/promotions' },
  { label: 'Contact', to: '/contact' },
];

const CUSTOMER_SERVICE = [
  { label: 'My Account', to: '/account/profile' },
  { label: 'Orders', to: '/account/orders' },
  { label: 'Wishlist', to: '/account/wishlist' },
  { label: 'Shopping Cart', to: '/cart' },
  { label: 'Help Center', to: '/contact' },
];

function SocialFacebook({ size = 18 }) {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
      <path d="M13.5 21v-7h2.4l.4-3h-2.8V9.1c0-.9.3-1.5 1.6-1.5H16.3V4.9c-.3 0-1.3-.1-2.4-.1-2.4 0-4 1.4-4 4.1V11H7.5v3h2.4v7h3.6Z" />
    </svg>
  );
}

function SocialInstagram({ size = 18 }) {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" aria-hidden="true">
      <rect x="3" y="3" width="18" height="18" rx="5" />
      <circle cx="12" cy="12" r="4" />
      <circle cx="17.2" cy="6.8" r="1.1" fill="currentColor" stroke="none" />
    </svg>
  );
}

function SocialTwitter({ size = 18 }) {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
      <path d="M18.2 3h3l-6.8 7.7L22.4 21h-6.3l-4.9-6.3L5.6 21h-3l7.2-8.2L1.7 3h6.4l4.4 5.8L18.2 3Zm-1.1 16.2h1.7L7.1 4.7H5.3l11.8 14.5Z" />
    </svg>
  );
}

function SocialYoutube({ size = 18 }) {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
      <path d="M21.6 7.2a2.5 2.5 0 0 0-1.8-1.8C18.2 5 12 5 12 5s-6.2 0-7.8.4A2.5 2.5 0 0 0 2.4 7.2 26 26 0 0 0 2 12a26 26 0 0 0 .4 4.8 2.5 2.5 0 0 0 1.8 1.8c1.6.4 7.8.4 7.8.4s6.2 0 7.8-.4a2.5 2.5 0 0 0 1.8-1.8A26 26 0 0 0 22 12a26 26 0 0 0-.4-4.8ZM10 15.2V8.8L15.5 12 10 15.2Z" />
    </svg>
  );
}

const SOCIALS = [
  { Icon: SocialFacebook, label: 'Facebook' },
  { Icon: SocialInstagram, label: 'Instagram' },
  { Icon: SocialTwitter, label: 'Twitter' },
  { Icon: SocialYoutube, label: 'YouTube' },
];

export default function Footer() {
  const [storeName, setStoreName] = useState('Delicacy Organic');
  const [storeLogo, setStoreLogo] = useState('');
  const [logoHeight, setLogoHeight] = useState(42);
  const [contact, setContact] = useState({ address: '', phone: '', email: '' });

  useEffect(() => {
    let active = true;
    settingsService
      .publicSettings()
      .then((data) => {
        if (!active) return;
        if (data?.store?.name) setStoreName(data.store.name);
        if (data?.store?.logo) setStoreLogo(data.store.logo);
        if (data?.store?.logo_height) setLogoHeight(data.store.logo_height);
        setContact({
          address: data?.contact?.address || '',
          phone: data?.contact?.phone || '',
          email: data?.contact?.email || '',
        });
      })
      .catch(() => {});
    return () => {
      active = false;
    };
  }, []);

  return (
    <footer className="customer-footer mt-auto">
      <Container className="py-5">
        <Row className="g-4 g-lg-5">
          {/* Column 1 – Brand + description */}
          <Col lg={4} md={6}>
            <Link to="/" className="footer-brand-row">
              {storeLogo ? (
                <img
                  src={storeLogo}
                  alt={storeName}
                  className="footer-brand-logo"
                  style={{ height: Math.min(logoHeight, 56) }}
                />
              ) : (
                <>
                  <LeafIcon size={32} className="footer-brand-icon" />
                  <span className="footer-brand">{storeName}</span>
                </>
              )}
            </Link>
            <p className="footer-text mt-3" style={{ maxWidth: 320 }}>
              Welcome to {storeName} — your trusted source for 100&nbsp;% organic,
              farm-fresh produce. We partner with local growers to bring you wholesome
              fruits, vegetables and pantry staples that taste as good as they make
              you feel.
            </p>
          </Col>

          {/* Column 2 – Quick Links */}
          <Col lg={2} md={6} xs={6}>
            <h2 className="footer-heading">Quick Links</h2>
            <ul className="footer-links list-unstyled">
              {QUICK_LINKS.map((link) => (
                <li key={link.to}>
                  <Link to={link.to}>{link.label}</Link>
                </li>
              ))}
            </ul>
          </Col>

          {/* Column 3 – Customer Service */}
          <Col lg={2} md={6} xs={6}>
            <h2 className="footer-heading">Customer Service</h2>
            <ul className="footer-links list-unstyled">
              {CUSTOMER_SERVICE.map((link) => (
                <li key={`${link.label}-${link.to}`}>
                  <Link to={link.to}>{link.label}</Link>
                </li>
              ))}
            </ul>
          </Col>

          {/* Column 4 – Contact */}
          <Col lg={4} md={6}>
            <h2 className="footer-heading">Get in Touch</h2>
            <div className="footer-contact-list">
              {contact.address && (
                <span className="footer-contact-line">
                  <span className="footer-contact-icon">
                    <MapPinIcon size={15} />
                  </span>
                  {contact.address}
                </span>
              )}
              {contact.phone && (
                <a href={`tel:${contact.phone}`} className="footer-contact-line footer-contact-link">
                  <span className="footer-contact-icon">
                    <PhoneIcon size={15} />
                  </span>
                  {contact.phone}
                </a>
              )}
              {contact.email && (
                <a href={`mailto:${contact.email}`} className="footer-contact-line footer-contact-mail">
                  <span className="footer-contact-icon">
                    <MailIcon size={15} />
                  </span>
                  {contact.email}
                </a>
              )}
            </div>
            <div className="footer-socials">
              {SOCIALS.map(({ Icon, label }) => (
                <a key={label} href="#" className="footer-social" aria-label={label} onClick={(e) => e.preventDefault()}>
                  <Icon />
                </a>
              ))}
            </div>
          </Col>
        </Row>
      </Container>

      {/* ─── Payment Methods ─── */}
      <div className="footer-payments py-3">
        <Container>
          <div className="footer-payments-inner">
            <span className="d-flex align-items-center gap-1">
              <svg width="38" height="24" viewBox="0 0 38 24" fill="none" aria-hidden="true">
                <rect width="38" height="24" rx="3" fill="#1a1f71" />
                <text x="5" y="16" fill="#fff" fontSize="9" fontWeight="bold" fontFamily="Arial">VISA</text>
              </svg>
              Visa
            </span>
            <span className="d-flex align-items-center gap-1">
              <svg width="38" height="24" viewBox="0 0 38 24" fill="none" aria-hidden="true">
                <rect width="38" height="24" rx="3" fill="#252525" />
                <circle cx="15" cy="12" r="7" fill="#EB001B" opacity="0.85" />
                <circle cx="23" cy="12" r="7" fill="#F79E1B" opacity="0.85" />
              </svg>
              Mastercard
            </span>
            <span className="d-flex align-items-center gap-1">
              <svg width="38" height="24" viewBox="0 0 38 24" fill="none" aria-hidden="true">
                <rect width="38" height="24" rx="3" fill="#253B80" />
                <text x="5" y="15" fill="#fff" fontSize="7" fontWeight="bold" fontFamily="Arial">PayPal</text>
              </svg>
              PayPal
            </span>
            <span className="d-flex align-items-center gap-1">
              <svg width="38" height="24" viewBox="0 0 38 24" fill="none" aria-hidden="true">
                <rect width="38" height="24" rx="3" fill="#000" />
                <text x="4" y="15" fill="#fff" fontSize="6" fontWeight="600" fontFamily="Arial"> Pay</text>
              </svg>
              Apple Pay
            </span>
            <span className="d-flex align-items-center gap-1">
              <svg width="38" height="24" viewBox="0 0 38 24" fill="none" aria-hidden="true">
                <rect width="38" height="24" rx="3" fill="#fff" stroke="#ddd" />
                <text x="3" y="15" fill="#5F6368" fontSize="6" fontWeight="600" fontFamily="Arial">G Pay</text>
              </svg>
              Google Pay
            </span>
          </div>
        </Container>
      </div>

      {/* ─── Bottom Bar ─── */}
      <div className="footer-bottom py-3">
        <Container className="footer-bottom-inner">
          <span>
            &copy; {new Date().getFullYear()} {storeName}. All rights reserved.
          </span>
          <div className="footer-legal">
            <Link to="/privacy">Privacy Policy</Link>
            <Link to="/terms">Terms &amp; Conditions</Link>
          </div>
        </Container>
      </div>
    </footer>
  );
}