import { Col, Row } from 'react-bootstrap';
import HomeProductCard from './HomeProductCard';

/**
 * Responsive product grid. Renders a HomeProductCard per item so the shop
 * page uses the exact same card design as the home page. Breakpoints follow
 * the storefront recommendation (1/2 mobiles, 2–3 tablets, 4 desktop).
 */
export default function ProductGrid({ products }) {
  return (
    <Row className="g-3 g-lg-4">
      {products.map((product) => (
        <Col key={product.id} xs={6} sm={6} md={4} lg={3}>
          <HomeProductCard product={product} />
        </Col>
      ))}
    </Row>
  );
}