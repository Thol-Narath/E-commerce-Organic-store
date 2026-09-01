import { useEffect, useState } from 'react';
import { Alert, Button, Col, Form, Modal, Row } from 'react-bootstrap';
import { normalizeError } from '../../services/api';

const EMPTY = {
  label: '',
  recipient_name: '',
  recipient_phone: '',
  address_line1: '',
  address_line2: '',
  city: '',
  state: '',
  postal_code: '',
  country: '',
  is_default: false,
};

/**
 * Reusable create/edit address form rendered inside a modal. Fields mirror the
 * backend `addresses` schema. `onSubmit(values)` must be a promise-returning
 * callback that performs the API call; the modal shows loading, field and
 * server errors, then closes on success (toast handled by the caller).
 */
export default function AddressForm({
  show,
  onHide,
  initialValues = null,
  onSubmit,
  title = 'Add Address',
  submitLabel = 'Save Address',
}) {
  const [form, setForm] = useState(EMPTY);
  const [saving, setSaving] = useState(false);
  const [errors, setErrors] = useState(null);
  const [serverError, setServerError] = useState('');

  useEffect(() => {
    if (show) {
      setForm({ ...EMPTY, ...(initialValues || {}) });
      setErrors(null);
      setServerError('');
      setSaving(false);
    }
  }, [show, initialValues]);

  const handleChange = (e) => {
    const { name, value, type, checked } = e.target;
    setForm((prev) => ({ ...prev, [name]: type === 'checkbox' ? checked : value }));
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setSaving(true);
    setErrors(null);
    setServerError('');

    try {
      await onSubmit(form);
      onHide();
    } catch (err) {
      const apiError = normalizeError(err);
      setServerError(apiError.message);
      setErrors(apiError.errors);
    } finally {
      setSaving(false);
    }
  };

  return (
    <Modal show={show} onHide={onHide} centered size="lg">
      <Form onSubmit={handleSubmit} noValidate>
        <Modal.Header closeButton>
          <Modal.Title>{title}</Modal.Title>
        </Modal.Header>
        <Modal.Body>
          {serverError && <Alert variant="danger">{serverError}</Alert>}

          <Row className="g-3">
            <Col xs={12} md={6}>
              <Form.Group controlId="addressLabel">
                <Form.Label>Label (optional)</Form.Label>
                <Form.Control
                  type="text"
                  name="label"
                  value={form.label}
                  onChange={handleChange}
                  placeholder="e.g. Home, Work"
                  isInvalid={Boolean(errors?.label)}
                />
                <Form.Control.Feedback type="invalid">{errors?.label?.[0]}</Form.Control.Feedback>
              </Form.Group>
            </Col>
            <Col xs={12} md={6}>
              <Form.Group controlId="addressRecipient">
                <Form.Label>Recipient name</Form.Label>
                <Form.Control
                  type="text"
                  name="recipient_name"
                  value={form.recipient_name}
                  onChange={handleChange}
                  isInvalid={Boolean(errors?.recipient_name)}
                  required
                />
                <Form.Control.Feedback type="invalid">{errors?.recipient_name?.[0]}</Form.Control.Feedback>
              </Form.Group>
            </Col>

            <Col xs={12} md={6}>
              <Form.Group controlId="addressPhone">
                <Form.Label>Phone</Form.Label>
                <Form.Control
                  type="tel"
                  name="recipient_phone"
                  value={form.recipient_phone}
                  onChange={handleChange}
                  isInvalid={Boolean(errors?.recipient_phone)}
                  required
                />
                <Form.Control.Feedback type="invalid">{errors?.recipient_phone?.[0]}</Form.Control.Feedback>
              </Form.Group>
            </Col>
            <Col xs={12} md={6}>
              <Form.Group controlId="addressCountry">
                <Form.Label>Country</Form.Label>
                <Form.Control
                  type="text"
                  name="country"
                  value={form.country}
                  onChange={handleChange}
                  isInvalid={Boolean(errors?.country)}
                  required
                />
                <Form.Control.Feedback type="invalid">{errors?.country?.[0]}</Form.Control.Feedback>
              </Form.Group>
            </Col>

            <Col xs={12}>
              <Form.Group controlId="addressLine1">
                <Form.Label>Address line 1</Form.Label>
                <Form.Control
                  type="text"
                  name="address_line1"
                  value={form.address_line1}
                  onChange={handleChange}
                  isInvalid={Boolean(errors?.address_line1)}
                  required
                />
                <Form.Control.Feedback type="invalid">{errors?.address_line1?.[0]}</Form.Control.Feedback>
              </Form.Group>
            </Col>
            <Col xs={12}>
              <Form.Group controlId="addressLine2">
                <Form.Label>Address line 2 (optional)</Form.Label>
                <Form.Control
                  type="text"
                  name="address_line2"
                  value={form.address_line2 || ''}
                  onChange={handleChange}
                  isInvalid={Boolean(errors?.address_line2)}
                />
                <Form.Control.Feedback type="invalid">{errors?.address_line2?.[0]}</Form.Control.Feedback>
              </Form.Group>
            </Col>

            <Col xs={12} md={5}>
              <Form.Group controlId="addressCity">
                <Form.Label>City</Form.Label>
                <Form.Control
                  type="text"
                  name="city"
                  value={form.city}
                  onChange={handleChange}
                  isInvalid={Boolean(errors?.city)}
                  required
                />
                <Form.Control.Feedback type="invalid">{errors?.city?.[0]}</Form.Control.Feedback>
              </Form.Group>
            </Col>
            <Col xs={12} md={4}>
              <Form.Group controlId="addressState">
                <Form.Label>State / Province</Form.Label>
                <Form.Control
                  type="text"
                  name="state"
                  value={form.state}
                  onChange={handleChange}
                  isInvalid={Boolean(errors?.state)}
                  required
                />
                <Form.Control.Feedback type="invalid">{errors?.state?.[0]}</Form.Control.Feedback>
              </Form.Group>
            </Col>
            <Col xs={12} md={3}>
              <Form.Group controlId="addressPostal">
                <Form.Label>Postal code (optional)</Form.Label>
                <Form.Control
                  type="text"
                  name="postal_code"
                  value={form.postal_code || ''}
                  onChange={handleChange}
                  isInvalid={Boolean(errors?.postal_code)}
                />
                <Form.Control.Feedback type="invalid">{errors?.postal_code?.[0]}</Form.Control.Feedback>
              </Form.Group>
            </Col>

            <Col xs={12}>
              <Form.Check
                type="checkbox"
                id="addressDefault"
                label="Make this my default shipping address"
                name="is_default"
                checked={Boolean(form.is_default)}
                onChange={handleChange}
              />
            </Col>
          </Row>
        </Modal.Body>
        <Modal.Footer>
          <Button variant="outline-secondary" onClick={onHide} disabled={saving}>
            Cancel
          </Button>
          <Button type="submit" variant="success" disabled={saving}>
            {saving ? 'Saving...' : submitLabel}
          </Button>
        </Modal.Footer>
      </Form>
    </Modal>
  );
}