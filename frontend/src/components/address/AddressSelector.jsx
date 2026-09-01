import { useState } from 'react';
import { Col, Row, Spinner } from 'react-bootstrap';
import AddressCard from './AddressCard';
import AddressForm from './AddressForm';
import { PlusIcon } from '../../assets/icons';

/**
 * Checkout address picker. Shows the customer's saved addresses as selectable
 * cards and lets them add a new one without leaving the checkout page.
 * `onCreate(payload)` must return the created address (promise).
 */
export default function AddressSelector({
  addresses,
  selectedAddressId,
  onSelect,
  onCreate,
  loading = false,
  disabled = false,
}) {
  const [showForm, setShowForm] = useState(false);

  const handleCreate = async (payload) => {
    const created = await onCreate(payload);
    onSelect(created);
  };

  if (loading) {
    return (
      <div className="d-flex align-items-center gap-2 text-muted">
        <Spinner animation="border" size="sm" />
        Loading addresses...
      </div>
    );
  }

  return (
    <div>
      {addresses.length === 0 ? (
        <p className="text-muted mb-3">
          You don't have any saved addresses yet. Add one to continue with checkout.
        </p>
      ) : (
        <Row className="g-3 mb-3">
          {addresses.map((address) => (
            <Col md={6} key={address.id}>
              <AddressCard
                address={address}
                selectable
                selected={address.id === selectedAddressId}
                onSelect={onSelect}
                disabled={disabled}
              />
            </Col>
          ))}
        </Row>
      )}

      <div className="text-center text-md-start">
        <button
          type="button"
          className="btn btn-outline-success btn-sm d-inline-flex align-items-center gap-2"
          onClick={() => setShowForm(true)}
          disabled={disabled}
        >
          <PlusIcon size={16} />
          Add New Address
        </button>
      </div>

      <AddressForm show={showForm} onHide={() => setShowForm(false)} onSubmit={handleCreate} />
    </div>
  );
}