import { Form } from 'react-bootstrap';

export const SORT_OPTIONS = [
  { value: 'newest', label: 'Newest' },
  { value: 'oldest', label: 'Oldest' },
  { value: 'price_low', label: 'Price: Low to High' },
  { value: 'price_high', label: 'Price: High to Low' },
  { value: 'name_asc', label: 'Name: A to Z' },
  { value: 'name_desc', label: 'Name: Z to A' },
];

/**
 * Sort dropdown backed by the sort keys the Phase 4 catalog API supports.
 */
export default function ProductSort({ value, onChange, disabled }) {
  return (
    <Form.Group controlId="product-sort">
      <Form.Label className="form-label-small">Sort by</Form.Label>
      <Form.Select value={value || 'newest'} onChange={(e) => onChange(e.target.value)} disabled={disabled} aria-label="Sort products">
        {SORT_OPTIONS.map((option) => (
          <option key={option.value} value={option.value}>
            {option.label}
          </option>
        ))}
      </Form.Select>
    </Form.Group>
  );
}