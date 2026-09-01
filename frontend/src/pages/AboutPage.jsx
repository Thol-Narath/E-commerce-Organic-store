import { Col, Container, Row } from 'react-bootstrap';
import PageHeader from '../components/common/PageHeader';
import usePageTitle from '../hooks/usePageTitle';
import { LeafIcon, ShieldIcon, StoreIcon, TruckIcon } from '../assets/icons';

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

export default function AboutPage() {
  usePageTitle('About');

  return (
    <Container className="py-4">
      <Row className="justify-content-center">
        <Col lg={8}>
          <PageHeader
            title="About Organic Store"
            subtitle="A modern storefront built around quality, freshness and trust."
          />

          <p className="lead">
            Organic Store is a demonstration e-commerce application showcasing a
            full organic grocery catalog. Our goal is simple: make it easy for
            customers to explore and understand the products they buy.
          </p>

          <Row className="g-4 mt-2">
            {PILLARS.map((pillar) => (
              <Col key={pillar.title} md={6}>
                <div className="feature-card h-100 p-4">
                  <div className="feature-icon mb-3">
                    <pillar.icon size={26} />
                  </div>
                  <h2 className="h6">{pillar.title}</h2>
                  <p className="text-muted small mb-0">{pillar.text}</p>
                </div>
              </Col>
            ))}
          </Row>

          <div className="border rounded-3 p-4 mt-4 bg-light">
            <h2 className="h5 mb-2">Our commitment</h2>
            <p className="mb-0">
              We keep our catalog honest: only active, available products are shown to
              customers, prices are always computed by the store, and historical order
              data is preserved. This is a demo build, so information on this page is
              illustrative rather than a claim of certification.
            </p>
          </div>
        </Col>
      </Row>
    </Container>
  );
}