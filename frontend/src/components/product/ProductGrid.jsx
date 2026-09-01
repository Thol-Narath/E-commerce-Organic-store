import { Col, Row } from 'react-bootstrap';
import ProductCard from './ProductCard';

/**
 * Responsive product grid. Renders a ProductCard per item using the breakpoints
 * recommended for the storefront (1/2 mobiles, 2–3 tablets, 4 desktop).
 */
export default function ProductGrid({ products }) {
  return (
    <Row className="g-3 g-lg-4">
      {products.map((product) => (
        <Col key={product.id} xs={6} sm={6} md={4} lg={3}>
          <ProductCard product={product} />
        </Col>
      ))}
    </Row>
  );
}