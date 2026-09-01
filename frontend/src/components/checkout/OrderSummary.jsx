import { Card } from 'react-bootstrap';
import ImageWithFallback from '../common/ImageWithFallback';
import { formatPrice } from '../../utils/format';

/**
 * Checkout order summary: line items with unit prices, subtotal, shipping fee
 * (server-provided) and grand total. All figures originate from the backend.
 */
export default function OrderSummary({ items, subtotal, shippingFee, discount = '0.00', total }) {
  return (
    <Card className="shadow-sm">
      <Card.Body>
        <h2 className="h6 text-uppercase text-muted mb-3">Order Summary</h2>

        <div className="order-summary-items mb-3">
          {items.length === 0 && <p className="text-muted small mb-0">Your cart is empty.</p>}
          {items.map((item) => {
            const product = item.product || {};
            return (
              <div className="order-summary-item d-flex gap-3 py-2 border-bottom" key={item.id}>
                <div className="order-summary-item-image flex-shrink-0">
                  <ImageWithFallback
                    src={product.primary_image?.url || product.images?.[0]?.url}
                    alt={product.name || 'Product'}
                    className="w-100 h-100"
                  />
                </div>
                <div className="flex-grow-1 min-w-0">
                  <div className="fw-semibold text-truncate">{product.name || 'Product'}</div>
                  <div className="small text-muted">
                    {formatPrice(item.unit_price)} × {item.quantity}
                  </div>
                </div>
                <div className="fw-semibold flex-shrink-0">{formatPrice(item.line_total)}</div>
              </div>
            );
          })}
        </div>

        <dl className="order-summary-totals mb-0">
          <div className="d-flex justify-content-between mb-1">
            <dt className="text-muted fw-normal">Subtotal</dt>
            <dd className="mb-0">{formatPrice(subtotal)}</dd>
          </div>
          <div className="d-flex justify-content-between mb-1">
            <dt className="text-muted fw-normal">Shipping</dt>
            <dd className="mb-0">{formatPrice(shippingFee)}</dd>
          </div>
          {Number(discount) > 0 && (
            <div className="d-flex justify-content-between mb-1 text-danger">
              <dt className="fw-normal">Discount</dt>
              <dd className="mb-0">−{formatPrice(discount)}</dd>
            </div>
          )}
          <hr />
          <div className="d-flex justify-content-between fs-5">
            <dt className="fw-bold">Total</dt>
            <dd className="mb-0 fw-bold">{formatPrice(total)}</dd>
          </div>
        </dl>
      </Card.Body>
    </Card>
  );
}