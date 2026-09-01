import { Link } from 'react-router-dom';
import { Card } from 'react-bootstrap';
import ImageWithFallback from '../common/ImageWithFallback';

/**
 * Category card linking to the category product page.
 * Shows the icon image when available and a letter avatar otherwise.
 */
export default function CategoryCard({ category }) {
  const count = Number(category.products_count) || 0;

  return (
    <Card as={Link} to={`/categories/${category.slug}`} className="h-100 text-center text-decoration-none category-card">
      <Card.Body className="d-flex flex-column align-items-center justify-content-center p-4">
        <div className="category-avatar mb-3">
          <ImageWithFallback
            src={category.icon_url}
            alt={category.name}
            className="w-100 h-100"
            placeholderClassName="category-avatar-fallback d-flex align-items-center justify-content-center"
          />
        </div>
        <Card.Title className="fs-6 mb-1">{category.name}</Card.Title>
        {category.description && (
          <Card.Text className="text-muted small mb-2">{category.description}</Card.Text>
        )}
        <span className="category-count small">
          {count > 0 ? `${count} product${count > 1 ? 's' : ''}` : 'Coming soon'}
        </span>
      </Card.Body>
    </Card>
  );
}