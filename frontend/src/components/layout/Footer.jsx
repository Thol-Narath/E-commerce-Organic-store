import { Link } from 'react-router-dom';
import { Col, Container, Row } from 'react-bootstrap';
import { useAuth } from '../../context/AuthContext';
import { LeafIcon, MailIcon, MapPinIcon, PhoneIcon } from '../../assets/icons';

/**
 * Customer storefront footer with brand blurb, quick links, account links and
 * clearly-marked placeholder contact details.
 */
export default function Footer() {
  const { user } = useAuth();

  return (
    <footer className="customer-footer mt-auto">
      <Container className="py-5">
        <Row className="g-4">
          <Col md={4}>
            <div className="d-flex align-items-center gap-2 mb-3">
              <LeafIcon size={30} className="footer-brand-icon" />
              <span className="footer-brand">OrganicStore</span>
            </div>
            <p className="footer-text">
              A demo organic grocery storefront. Fresh, natural and quality-driven
              products, presented through a modern, responsive store interface.
            </p>
          </Col>

          <Col xs={6} sm={4} md={3} lg={2} className="ms-md-auto">
            <h2 className="footer-heading">Shop</h2>
            <ul className="footer-links list-unstyled">
              <li><Link to="/shop">All products</Link></li>
              <li><Link to="/categories">Categories</Link></li>
              <li><Link to="/shop?sort=price_low">Best value</Link></li>
              <li><Link to="/about">About us</Link></li>
            </ul>
          </Col>

          <Col xs={6} sm={4} md={3} lg={2}>
            <h2 className="footer-heading">Account</h2>
            <ul className="footer-links list-unstyled">
              <li><Link to="/profile">My profile</Link></li>
              {user ? (
                <li><Link to="/profile">{user.name}</Link></li>
              ) : (
                <>
                  <li><Link to="/login">Sign in</Link></li>
                  <li><Link to="/register">Create account</Link></li>
                </>
              )}
            </ul>
          </Col>

          <Col xs={12} sm={6} md={3} lg={3}>
            <h2 className="footer-heading">Contact</h2>
            <address className="footer-text mb-2">
              <span className="footer-contact-line">
                <MapPinIcon size={15} /> 123 Demo Road, Greenville
              </span>
              <span className="footer-contact-line">
                <PhoneIcon size={15} /> +1 555 0100
              </span>
              <span className="footer-contact-line">
                <MailIcon size={15} /> hello@organicstore.demo
              </span>
            </address>
            <p className="footer-note small">Placeholder contact information — demo store.</p>
          </Col>
        </Row>
      </Container>

      <div className="footer-bottom py-3">
        <Container className="d-flex flex-column flex-sm-row justify-content-between align-items-center gap-2">
          <span>&copy; {new Date().getFullYear()} Organic Store. All rights reserved.</span>
          <span className="small">Demo storefront — Phase 5 of the Organic Store build.</span>
        </Container>
      </div>
    </footer>
  );
}