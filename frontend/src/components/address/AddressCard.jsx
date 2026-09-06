import { Badge, Button, Card } from 'react-bootstrap';
import { MapPinIcon } from '../../assets/icons';

/**
 * A single customer shipping address. In "selectable" mode (checkout) the
 * whole card is clickable and the selected card is highlighted; otherwise it
 * exposes Edit / Delete / Set default actions (address book).
 */
export default function AddressCard({
  address,
  selectable = false,
  selected = false,
  onSelect,
  onEdit,
  onDelete,
  onSetDefault,
  disabled = false,
}) {
  const handleSelect = () => {
    if (selectable && onSelect && !disabled) {
      onSelect(address);
    }
  };

  const cityLine = [address.city, address.state].filter(Boolean).join(', ');
  const postalLine = address.postal_code ? ` ${address.postal_code}` : '';
  const labelText = address.label
    ? address.label.charAt(0).toUpperCase() + address.label.slice(1)
    : '';

  return (
    <Card
      className={`address-card h-100 shadow-sm ${selectable ? 'address-card-selectable' : ''} ${
        selected ? 'address-card-selected' : ''
      }`}
      onClick={handleSelect}
    >
      <Card.Body className="d-flex flex-column gap-2">
        <div className="d-flex align-items-center justify-content-between gap-2">
          <div className="d-flex align-items-center gap-2 flex-wrap">
            {labelText && <span className="address-label">{labelText}</span>}
            {address.is_default && (
              <Badge bg="success" pill>
                Default
              </Badge>
            )}
            {selectable && selected && (
              <Badge bg="success" pill>
                Selected
              </Badge>
            )}
          </div>
          {selectable && selected && <MapPinIcon size={20} className="text-success flex-shrink-0" />}
        </div>

        <div className="d-flex align-items-start gap-2">
          <MapPinIcon size={20} className="text-success flex-shrink-0 mt-1" />
          <div className="small lh-sm">
            <div className="fw-semibold">{address.recipient_name}</div>
            <div className="text-muted">Phone: {address.recipient_phone || '—'}</div>
            <div className="mt-1">{address.address_line1}</div>
            {address.address_line2 && <div>{address.address_line2}</div>}
            <div>
              {cityLine}
              {postalLine}
            </div>
            <div>{address.country}</div>
          </div>
        </div>
      </Card.Body>

      {/* Inner button container: cards stay clickable while actions remain usable. */}
      <div
        className="card-footer bg-white border-top-0 d-flex flex-wrap gap-2 justify-content-end"
        onClick={(e) => e.stopPropagation()}
      >
        {!address.is_default && onSetDefault && (
          <Button variant="outline-success" size="sm" onClick={() => onSetDefault(address)} disabled={disabled}>
            Set default
          </Button>
        )}
        {onEdit && (
          <Button variant="outline-secondary" size="sm" onClick={() => onEdit(address)} disabled={disabled}>
            Edit
          </Button>
        )}
        {onDelete && (
          <Button variant="outline-danger" size="sm" onClick={() => onDelete(address)} disabled={disabled}>
            Delete
          </Button>
        )}
      </div>
    </Card>
  );
}