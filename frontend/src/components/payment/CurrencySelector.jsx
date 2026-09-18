import { Col, Row } from 'react-bootstrap';
import { formatAmount } from '../../utils/format';

/**
 * Lets the customer choose which currency the payment attempt is made in:
 * US Dollar ($) or Cambodian Riel (៛). The order total is always stored in
 * USD; any KHR amount shown here is a server-supplied estimate and the exact
 * charge is computed (and enforced) by the Laravel backend.
 */
export default function CurrencySelector({ currencies = [], orderTotal, value, onChange, disabled = false }) {
  if (currencies.length === 0) return null;

  const hasOrderTotal = orderTotal !== null && orderTotal !== undefined;
  const options = currencies
    .filter((c) => ['USD', 'KHR'].includes(c.code))
    .sort((a, b) => (a.code === 'USD' ? -1 : b.code === 'USD' ? 1 : 0));

  return (
    <div className="mb-3">
      <p className="mb-2 fw-semibold">Pay in currency</p>
      <Row className="g-3">
        {options.map((currency) => {
          const selected = value === currency.code;
          const amount = hasOrderTotal
            ? formatAmount(
                currency.code === 'KHR' ? Number(orderTotal) * Number(currency.rate || 1) : orderTotal,
                currency.code,
              )
            : null;

          return (
            <Col xs={6} key={currency.code}>
              <button
                type="button"
                className={`payment-method-card payment-currency-card ${selected ? 'selected' : ''}`}
                onClick={() => onChange?.(currency.code)}
                disabled={disabled}
                aria-pressed={selected}
              >
                <span className="payment-method-radio" aria-hidden="true">
                  <span className={selected ? 'inner' : ''} />
                </span>
                <span className={`payment-currency-badge ${selected ? 'selected' : ''}`} aria-hidden="true">
                  {currency.symbol || currency.code}
                </span>
                <span className="payment-method-label">{currency.code === 'KHR' ? 'Cambodian Riel' : 'US Dollar'}</span>
                {amount ? (
                  <span className="payment-method-hint">
                    <span className="fw-semibold text-success">{amount}</span>
                  </span>
                ) : null}
              </button>
            </Col>
          );
        })}
      </Row>
    </div>
  );
}