import { Col, Row } from 'react-bootstrap';
import CategoryCard from './CategoryCard';

/**
 * Responsive grid of category cards.
 */
export default function CategoryGrid({ categories }) {
  return (
    <Row className="g-3 g-lg-4">
      {categories.map((category) => (
        <Col key={category.id} xs={6} md={4} lg={3}>
          <CategoryCard category={category} />
        </Col>
      ))}
    </Row>
  );
}