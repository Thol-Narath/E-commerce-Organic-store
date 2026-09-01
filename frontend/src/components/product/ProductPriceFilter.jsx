import { Col, Form } from 'react-bootstrap';

/**
 * Minimum/maximum price inputs. Values are sanitized to non-negative numbers
 * before being sent to the API.
 */
export default function ProductPriceFilter({ minValue = '', maxValue = '', onMinChange, onMaxChange, disabled }) {
  const sanitize = (handler) => (e) => {
    const raw = e.target.value;
    if (raw === '' || (Number(raw) >= 0)) {
      handler(raw);
    }
  };

  return (
    <>
      <Form.Group controlId="product-price-min" className="flex-grow-1">
        <Form.Label className="form-label-small">Min price</Form.Label>
        <Form.Control
          type="number"
          min="0"
          step="0.01"
          placeholder="0"
          value={minValue}
          onChange={sanitize(onMinChange)}
          disabled={disabled}
          aria-label="Minimum price"
        />
      </Form.Group>
      <Form.Group controlId="product-price-max" className="flex-grow-1">
        <Form.Label className="form-label-small">Max price</Form.Label>
        <Form.Control
          type="number"
          min="0"
          step="0.01"
          placeholder="Any"
          value={maxValue}
          onChange={sanitize(onMaxChange)}
          disabled={disabled}
          aria-label="Maximum price"
        />
      </Form.Group>
    </>
  );
}