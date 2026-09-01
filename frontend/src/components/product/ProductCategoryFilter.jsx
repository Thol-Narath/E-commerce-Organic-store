import { Form } from 'react-bootstrap';

/**
 * Category filter dropdown. Values are the numeric category ids, matching the
 * backend `category_id` filter used by the catalog API.
 */
export default function ProductCategoryFilter({ categories = [], value, onChange, disabled }) {
  return (
    <Form.Group controlId="product-category">
      <Form.Label className="form-label-small">Category</Form.Label>
      <Form.Select value={value || ''} onChange={(e) => onChange(e.target.value)} disabled={disabled} aria-label="Filter by category">
        <option value="">All categories</option>
        {categories.map((category) => (
          <option key={category.id} value={category.id}>
            {category.name}
          </option>
        ))}
      </Form.Select>
    </Form.Group>
  );
}