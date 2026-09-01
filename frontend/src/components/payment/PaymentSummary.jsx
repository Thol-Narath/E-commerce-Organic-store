import { Card } from 'react-bootstrap';
import OrderStatusBadge from '../orders/OrderStatusBadge';
import { formatPrice } from '../../utils/format';

/**
 * Right-hand panel on the payment page: always shows the order number and
 * amount due; once an attempt exists it also shows method + attempt status.
 */
export default function PaymentSummary({ orderNumber, amount, payment = null }) {
  return (
    <Card className="shadow-sm">
      <Card.Body>
        <h2 className="h6 text-uppercase text-muted mb-3">Payment Summary</h2>
        <dl className="mb-0">
          <div className="d-flex justify-content-between mb-2">
            <dt className="text-muted fw-normal">Order</dt>
            <dd className="mb-0 fw-semibold">
              <span className="text-truncate d-inline-block" style={{ maxWidth: 180 }}>
                {orderNumber}
              </span>
            </dd>
          </div>
          <div className="d-flex justify-content-between mb-2">
            <dt className="text-muted fw-normal">Amount due</dt>
            <dd className="mb-0 fw-bold">{amount ? formatPrice(amount) : '—'}</dd>
          </div>
          {payment?.payment_method_label && (
            <div className="d-flex justify-content-between mb-2">
              <dt className="text-muted fw-normal">Method</dt>
              <dd className="mb-0">{payment.payment_method_label}</dd>
            </div>
          )}
          {payment?.payment_status && (
            <div className="d-flex justify-content-between align-items-center">
              <dt className="text-muted fw-normal">Status</dt>
              <dd className="mb-0">
                <OrderStatusBadge status={payment.payment_status} type="payment" />
              </dd>
            </div>
          )}
        </dl>
      </Card.Body>
    </Card>
  );
}