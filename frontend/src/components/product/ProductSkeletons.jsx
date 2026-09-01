import { Col, Row } from 'react-bootstrap';

/**
 * Shimmer placeholders shown while a product query is loading.
 * Mirrors the ProductGrid column layout so the page does not jump.
 */
export default function ProductSkeletons({ count = 8 }) {
  return (
    <Row className="g-3 g-lg-4">
      {Array.from({ length: count }, (_, i) => (
        <Col key={i} xs={6} sm={6} md={4} lg={3}>
          <div className="product-skeleton card h-100">
            <div className="product-skeleton-image skeleton-shimmer" />
            <div className="p-3">
              <div className="skeleton-line skeleton-shimmer w-50 mb-2" />
              <div className="skeleton-line skeleton-shimmer w-75 mb-3" />
              <div className="skeleton-line skeleton-shimmer w-40 mb-3" />
              <div className="skeleton-btn skeleton-shimmer" />
            </div>
          </div>
        </Col>
      ))}
    </Row>
  );
}