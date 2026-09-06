import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { Button, Col, Container, Form, Row } from 'react-bootstrap';
import { useAuth } from '../../context/AuthContext';
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
  const { user } = useAuth();
  const [email, setEmail] = useState('');
  const [subscribing, setSubscribing] = useState(false);
  const [subMessage, setSubMessage] = useState('');
  const [storeName, setStoreName] = useState('Delicacy Organic');

  useEffect(() => {
    let active = true;
    settingsService
      .publicSettings()
      .then((data) => {
        if (active && data?.store?.name) setStoreName(data.store.name);
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

          {/* Column 1 – Brand */}
          <Col lg={4} md={6}>
            <div className="d-flex align-items-center gap-2 mb-3">
              <LeafIcon size={32} className="footer-brand-icon" />
              <span className="footer-brand" style={{ fontSize: '1.35rem' }}>
                {storeName}
              </span>
            </div>
            <p className="footer-text" style={{ maxWidth: 320 }}>
              Welcome to {storeName} — your trusted source for 100&nbsp;% organic,
              farm-fresh produce. We partner with local growers to bring you wholesome
              fruits, vegetables and pantry staples that taste as good as they make
              you feel.
            </p>
            <div className="d-flex gap-3 mt-3">
              <span className="footer-contact-line" style={{ marginBottom: 0 }}>
                <MapPinIcon size={15} /> 123 Organic Lane, Greenville
              </span>
            </div>
            <span className="footer-contact-line" style={{ marginBottom: 0 }}>
              <PhoneIcon size={15} /> +1 555 0100
            </span>
            <span className="footer-contact-line" style={{ marginBottom: 0 }}>
              <MailIcon size={15} /> hello@delicacyorganic.com
            </span>
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
            <Form onSubmit={handleSubscribe} className="d-flex gap-2 mt-3">
              <Form.Control
                type="email"
                placeholder="Your email address"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                required
                className="flex-grow-1"
                style={{
                  background: 'rgba(255,255,255,0.08)',
                  border: '1px solid rgba(255,255,255,0.15)',
                  color: '#fff',
                }}
              />
              <Button
                type="submit"
                variant="success"
                disabled={subscribing}
                style={{ whiteSpace: 'nowrap' }}
              >
                {subscribing ? 'Sending…' : 'Subscribe'}
              </Button>
            </Form>
            {subMessage && (
              <small className="mt-2 d-block" style={{ color: '#a8d5a2' }}>
                {subMessage}
              </small>
            )}
          </Col>
        </Row>
      </Container>

      {/* ─── Payment Methods ─── */}
      <div className="footer-payments py-3" style={{ background: '#152115', borderTop: '1px solid rgba(255,255,255,0.07)' }}>
        <Container>
          <div className="d-flex flex-wrap justify-content-center align-items-center gap-4" style={{ color: '#93a298', fontSize: '0.85rem' }}>
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
        <Container className="d-flex flex-column flex-sm-row justify-content-between align-items-center gap-2">
          <span>
            &copy; {new Date().getFullYear()} {storeName}. All rights reserved.
          </span>
          <span className="small" style={{ color: '#7a8c7f' }}>
            Crafted with care for a healthier lifestyle.
          </span>
        </Container>
      </div>
    </footer>
  );
}
