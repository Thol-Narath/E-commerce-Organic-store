import { Button, Form } from 'react-bootstrap';
import { MinusIcon, PlusIcon } from '../../assets/icons';

/**
 * Compact quantity stepper. The +/- buttons step by 1; the number input
 * accepts direct entry. `max` is intentionally optional because the exact
 * stock level is not exposed to the client — the backend rejects over-stock
 * and the error is surfaced via a toast.
 */
export default function QuantityControl({
  value,
  min = 1,
  max,
  onChange,
  disabled,
  className = '',
}) {
  const atMin = value <= min;
  const atMax = max != null && value >= max;

  const clamp = (next) => {
    if (Number.isNaN(next)) return min;
    return Math.max(min, max != null ? Math.min(max, next) : next);
  };

  const handleInput = (raw) => {
    const next = Number(raw);
    if (!Number.isNaN(next)) onChange(clamp(Math.trunc(next)));
  };

  return (
    <div className={`quantity-control d-inline-flex align-items-center ${className}`}>
      <Button
        variant="light"
        onClick={() => onChange(clamp(value - 1))}
        disabled={disabled || atMin}
        aria-label="Decrease quantity"
        className="quantity-btn"
      >
        <MinusIcon size={16} />
      </Button>
      <Form.Control
        type="number"
        className="quantity-input text-center"
        value={value}
        min={min}
        max={max}
        onChange={(e) => handleInput(e.target.value)}
        disabled={disabled}
        aria-label="Quantity"
      />
      <Button
        variant="light"
        onClick={() => onChange(clamp(value + 1))}
        disabled={disabled || atMax}
        aria-label="Increase quantity"
        className="quantity-btn"
      >
        <PlusIcon size={16} />
      </Button>
    </div>
  );
}