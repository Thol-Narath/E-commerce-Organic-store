import { Button, Form, InputGroup } from 'react-bootstrap';
import { SearchIcon } from '../../assets/icons';

/**
 * Search box that applies a term on submit (enter or the search button),
 * keeping network requests predictable book-checking.
 */
export default function ProductSearch({ value, onChange, onSubmit, onClear, disabled }) {
  return (
    <Form onSubmit={onSubmit} role="search" aria-label="Search products">
      <Form.Label className="form-label-small">Search</Form.Label>
      <InputGroup>
        <InputGroup.Text>
          <SearchIcon size={16} />
        </InputGroup.Text>
        <Form.Control
          type="search"
          placeholder="Search products..."
          value={value}
          onChange={(e) => onChange(e.target.value)}
          disabled={disabled}
          aria-label="Search products"
        />
        <Button type="submit" variant="success" disabled={disabled}>
          Search
        </Button>
        {value && (
          <Button type="button" variant="outline-secondary" onClick={onClear} disabled={disabled} aria-label="Clear search">
            Clear
          </Button>
        )}
      </InputGroup>
    </Form>
  );
}