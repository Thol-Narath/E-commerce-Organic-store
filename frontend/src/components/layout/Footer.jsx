import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { Button, Col, Container, Form, Row } from 'react-bootstrap';
import { LeafIcon, MailIcon, MapPinIcon, PhoneIcon } from '../../assets/icons';
import { newsletterService } from '../../services/newsletterService';
import { settingsService } from '../../services/settingsService';

const CATEGORIES = [
  { name: 'Fresh Vegetables', slug: 'fresh-vegetables' },
  { name: 'Organic Fruits', slug: 'organic-fruits' },
  { name: 'Dairy & Eggs', slug: 'dairy-eggs' },
  { name: 'Bread & Bakery', slug: 'bread-bakery' },
  { name: 'Beverages', slug: 'beverages' },
];

const USEFUL_LINKS = [
  { label: 'About Us', to: '/about' },
  { label: 'Contact', to: '/contact' },
  { label: 'FAQ', to: '/faq' },
  { label: 'Privacy Policy', to: '/privacy' },
  { label: 'Terms of Service', to: '/terms' },
];

export default function Footer() {
  const [email, setEmail] = useState('');
  const [subscribing, setSubscribing] = useState(false);
  const [subMessage, setSubMessage] = useState('');
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

  const handleSubscribe = async (e) => {
    e.preventDefault();
    if (!email.trim()) return;
    setSubscribing(true);
    setSubMessage('');
    try {
      await newsletterService.subscribe(email);
      setSubMessage('Thank you for subscribing!');
      setEmail('');
    } catch {
      setSubMessage('Something went wrong. Please try again.');
    } finally {
      setSubscribing(false);
    }
  };

  return (
    <footer className="customer-footer mt-auto">
      {/* ─── Main Footer ─── */}
      <Container className="py-5">
        <Row className="g-4">

          {/* Column 1 – Brand + Contact */}
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
                <span className="footer-contact-line">
                  <span className="footer-contact-icon">
                    <PhoneIcon size={15} />
                  </span>
                  {contact.phone}
                </span>
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
          </Col>

          {/* Column 2 – Categories */}
          <Col lg={2} md={6} xs={6}>
            <h2 className="footer-heading">Categories</h2>
            <ul className="footer-links list-unstyled">
              {CATEGORIES.map((cat) => (
                <li key={cat.slug}>
                  <Link to={`/categories/${cat.slug}`}>{cat.name}</Link>
                </li>
              ))}
            </ul>
          </Col>

          {/* Column 3 – Useful Links */}
          <Col lg={2} md={6} xs={6}>
            <h2 className="footer-heading">Useful Links</h2>
            <ul className="footer-links list-unstyled">
              {USEFUL_LINKS.map((link) => (
                <li key={link.to}>
                  <Link to={link.to}>{link.label}</Link>
                </li>
              ))}
            </ul>
          </Col>

          {/* Column 4 – Newsletter */}
          <Col lg={4} md={6}>
            <h2 className="footer-heading">Newsletter</h2>
            <p className="footer-text" style={{ maxWidth: 300 }}>
              Subscribe to receive exclusive offers, new product announcements and
              organic living tips straight to your inbox.
            </p>
            <Form onSubmit={handleSubscribe} className="footer-newsletter-form d-flex gap-2 mt-3">
              <Form.Control
                type="email"
                placeholder="Your email address"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                required
                className="footer-newsletter-input flex-grow-1"
              />
              <Button type="submit" variant="success" disabled={subscribing}>
                {subscribing ? 'Sending…' : 'Subscribe'}
              </Button>
            </Form>
            {subMessage && (
              <small className="footer-newsletter-message d-block mt-2">
                {subMessage}
              </small>
            )}
          </Col>
        </Row>
      </Container>

      {/* ─── Payment Methods ─── */}
      <div className="footer-payments py-3">
        <Container>
          <div className="footer-payments-inner">
            <span className="d-flex align-items-center gap-1">
              <svg width="38" height="24" viewBox="0 0 38 24" fill="none">
                <rect width="38" height="24" rx="3" fill="#1a1f71" />
                <text x="5" y="16" fill="#fff" fontSize="9" fontWeight="bold" fontFamily="Arial">VISA</text>
              </svg>
              Visa
            </span>
            <span className="d-flex align-items-center gap-1">
              <svg width="38" height="24" viewBox="0 0 38 24" fill="none">
                <rect width="38" height="24" rx="3" fill="#252525" />
                <circle cx="15" cy="12" r="7" fill="#EB001B" opacity="0.85" />
                <circle cx="23" cy="12" r="7" fill="#F79E1B" opacity="0.85" />
              </svg>
              Mastercard
            </span>
            <span className="d-flex align-items-center gap-1">
              <svg width="38" height="24" viewBox="0 0 38 24" fill="none">
                <rect width="38" height="24" rx="3" fill="#253B80" />
                <text x="5" y="15" fill="#fff" fontSize="7" fontWeight="bold" fontFamily="Arial">PayPal</text>
              </svg>
              PayPal
            </span>
            <span className="d-flex align-items-center gap-1">
              <svg width="38" height="24" viewBox="0 0 38 24" fill="none">
                <rect width="38" height="24" rx="3" fill="#000" />
                <text x="4" y="15" fill="#fff" fontSize="6" fontWeight="600" fontFamily="Arial"> Pay</text>
              </svg>
              Apple Pay
            </span>
            <span className="d-flex align-items-center gap-1">
              <svg width="38" height="24" viewBox="0 0 38 24" fill="none">
                <rect width="38" height="24" rx="3" fill="#fff" stroke="#ddd" />
                <text x="3" y="15" fill="#5F6368" fontSize="6" fontWeight="600" fontFamily="Arial">G Pay</text>
              </svg>
              Google Pay
            </span>
          </div>
        </Container>
      </div>

      {/* ─── Copyright Bar ─── */}
      <div className="footer-bottom py-3">
        <Container className="footer-bottom-inner">
          <span>
            &copy; {new Date().getFullYear()} {storeName}. All rights reserved.
          </span>
          <span className="small footer-note">
            Crafted with care for a healthier lifestyle.
          </span>
        </Container>
      </div>
    </footer>
  );
}