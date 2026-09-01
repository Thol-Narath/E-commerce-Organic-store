import { Badge } from 'react-bootstrap';

const STATUS_VARIANTS = {
  // Order fulfillment statuses.
  pending: 'warning',
  confirmed: 'success',
  processing: 'info',
  shipped: 'primary',
  delivered: 'success',
  cancelled: 'danger',
  // Payment statuses.
  unpaid: 'secondary',
  payment_pending: 'warning',
  payment_expired: 'warning',
  payment_cancelled: 'secondary',
  paid: 'success',
  failed: 'danger',
  refunded: 'secondary',
};

const STATUS_LABELS = {
  pending: 'Pending',
  confirmed: 'Confirmed',
  processing: 'Processing',
  shipped: 'Shipped',
  delivered: 'Delivered',
  cancelled: 'Cancelled',
  unpaid: 'Unpaid',
  payment_pending: 'Payment pending',
  payment_expired: 'Payment expired',
  payment_cancelled: 'Payment cancelled',
  paid: 'Paid',
  failed: 'Payment failed',
  refunded: 'Refunded',
};

export default function OrderStatusBadge({ status, type = 'order', className = '' }) {
  const key = type === 'payment' ? `payment_${status}` : status;
  const variant = STATUS_VARIANTS[status] || STATUS_VARIANTS[key] || 'secondary';
  const label = type === 'payment' && status !== 'unpaid'
    ? STATUS_LABELS[key] || status
    : STATUS_LABELS[status] || status;

  return (
    <Badge bg={variant} pill className={`text-capitalize ${className}`}>
      {label}
    </Badge>
  );
}