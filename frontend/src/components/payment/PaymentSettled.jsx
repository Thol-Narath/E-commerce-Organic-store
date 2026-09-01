import { Button, Card } from 'react-bootstrap';
import { Link } from 'react-router-dom';
import OrderStatusBadge from '../orders/OrderStatusBadge';
import { formatPrice, formatDate } from '../../utils/format';
import { CheckCircleIcon } from '../../assets/icons';

/**
 * Receipt shown once the backend confirms a payment (webhook or
 * check-transaction). Only reached after the server marks the order paid.
 */
export default function PaymentSettled({ orderNumber, payment }) {
  return (
    <Card className="shadow-sm text-center">
      <Card.Body className="p-4 p-md-5">
        <CheckCircleIcon size={56} className="text-success mb-3" />
        <h1 className="h3 mb-2">Payment confirmed!</h1>
        <p className="text-muted mb-4">
          Your payment for order {orderNumber} has been received. We’ll start preparing your items.
        </p>

        {payment && (
          <dl className="payment-receipt mx-auto mb-4 text-start">
            <div className="d-flex justify-content-between">
              <dt className="text-muted fw-normal">Order</dt>
              <dd className="mb-0 fw-semibold">{orderNumber}</dd>
            </div>
            <div className="d-flex justify-content-between">
              <dt className="text-muted fw-normal">Amount paid</dt>
              <dd className="mb-0 fw-bold">{formatPrice(payment.amount)}</dd>
            </div>
            <div className="d-flex justify-content-between">
              <dt className="text-muted fw-normal">Method</dt>
              <dd className="mb-0">{payment.payment_method_label}</dd>
            </div>
            <div className="d-flex justify-content-between">
              <dt className="text-muted fw-normal">Payment number</dt>
              <dd className="mb-0">{payment.payment_number}</dd>
            </div>
            <div className="d-flex justify-content-between">
              <dt className="text-muted fw-normal">Paid on</dt>
              <dd className="mb-0">{formatDate(payment.paid_at)}</dd>
            </div>
            <div className="d-flex justify-content-between">
              <dt className="text-muted fw-normal">Status</dt>
              <dd className="mb-0">
                <OrderStatusBadge status={payment.payment_status} type="payment" />
              </dd>
            </div>
          </dl>
        )}

        <div className="d-flex flex-column flex-sm-row gap-2 justify-content-center">
          <Button as={Link} to={`/orders/${orderNumber}`} variant="success">
            Track Order
          </Button>
          <Button as={Link} to="/shop" variant="outline-success">
            Continue Shopping
          </Button>
        </div>
      </Card.Body>
    </Card>
  );
}