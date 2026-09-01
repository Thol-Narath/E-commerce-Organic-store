import { useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { Alert, Button, Card, Col, Container, Row } from 'react-bootstrap';
import OrderStatusBadge from '../../components/orders/OrderStatusBadge';
import ImageWithFallback from '../../components/common/ImageWithFallback';
import LoadingSpinner from '../../components/common/LoadingSpinner';
import { orderService } from '../../services/orderService';
import usePageTitle from '../../hooks/usePageTitle';
import { formatPrice, formatDate } from '../../utils/format';
import { getErrorMessage } from '../../utils/error';
import { MapPinIcon, TruckIcon } from '../../assets/icons';

export default function OrderDetailPage() {
  usePageTitle('Order Details');
  const { orderNumber } = useParams();

  const [order, setOrder] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    let active = true;
    (async () => {
      setLoading(true);
      setError('');
      try {
        const fetched = await orderService.get(orderNumber);
        if (active) setOrder(fetched);
      } catch (err) {
        if (active) setError(getErrorMessage(err));
      } finally {
        if (active) setLoading(false);
      }
    })();
    return () => {
      active = false;
    };
  }, [orderNumber]);

  if (loading) {
    return (
      <Container className="py-5">
        <LoadingSpinner label="Loading order details..." />
      </Container>
    );
  }

  if (error) {
    return (
      <Container className="py-5">
        <Alert variant="danger">{error}</Alert>
        <Button as={Link} to="/orders" variant="outline-success">
          Back to My Orders
        </Button>
      </Container>
    );
  }

  const address = order?.shipping_address;

  return (
    <Container className="py-4">
      <div className="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
        <div className="d-flex align-items-center gap-2">
          <TruckIcon size={26} className="text-success" />
          <h1 className="h4 mb-0">{order.order_number}</h1>
          <OrderStatusBadge status={order.status} />
          <OrderStatusBadge status={order.payment_status} type="payment" />
        </div>
        <Button as={Link} to="/orders" variant="outline-success" size="sm">
          Back to My Orders
        </Button>
      </div>

      <Row className="g-4">
        <Col lg={8}>
          <Card className="shadow-sm mb-4">
            <Card.Body>
              <h2 className="h6 text-uppercase text-muted mb-3">Items ({order.items.length})</h2>
              {order.items.map((item) => {
                const url = item.product ? `/products/${item.product.slug}` : null;
                const img = item.product?.primary_image?.url || item.product?.images?.[0]?.url;
                const title = item.product_name;
                const body = (
                  <div className="d-flex gap-3 align-items-center order-item-row">
                    <div className="order-summary-item-image flex-shrink-0">
                      {img ? (
                        <ImageWithFallback src={img} alt={title} className="w-100 h-100" />
                      ) : (
                        <div className="w-100 h-100 d-flex align-items-center justify-content-center text-muted">
                          —
                        </div>
                      )}
                    </div>
                    <div className="flex-grow-1 min-w-0">
                      <div className="fw-semibold text-truncate">{title}</div>
                      <div className="small text-muted">
                        SKU: {item.product_sku || '—'} · {formatPrice(item.unit_price)} × {item.quantity}
                      </div>
                    </div>
                    <div className="fw-semibold flex-shrink-0">{formatPrice(item.line_total)}</div>
                  </div>
                );
                return url ? (
                  <Link key={item.id} to={url} className="text-decoration-none text-reset d-block py-2 border-bottom">
                    {body}
                  </Link>
                ) : (
                  <div key={item.id} className="py-2 border-bottom">
                    {body}
                  </div>
                );
              })}
            </Card.Body>
          </Card>

          {address && (
            <Card className="shadow-sm">
              <Card.Body>
                <h2 className="h6 text-uppercase text-muted mb-3 d-flex align-items-center gap-2">
                  <MapPinIcon size={18} />
                  Shipping Address
                </h2>
                <div className="small lh-sm">
                  <div className="fw-semibold">{address.recipient_name}</div>
                  <div className="text-muted">Phone: {address.recipient_phone || '—'}</div>
                  <div className="mt-1">{address.address_line1}</div>
                  {address.address_line2 && <div>{address.address_line2}</div>}
                  <div>{[address.city, address.state].filter(Boolean).join(', ')}</div>
                  <div>
                    {address.country}
                    {address.postal_code ? ` ${address.postal_code}` : ''}
                  </div>
                </div>
              </Card.Body>
            </Card>
          )}
        </Col>

        <Col lg={4}>
          <Card className="shadow-sm">
            <Card.Body>
              <h2 className="h6 text-uppercase text-muted mb-3">Order Summary</h2>
              <dl className="mb-0">
                <div className="d-flex justify-content-between mb-1">
                  <dt className="text-muted fw-normal">Subtotal</dt>
                  <dd className="mb-0">{formatPrice(order.subtotal)}</dd>
                </div>
                <div className="d-flex justify-content-between mb-1">
                  <dt className="text-muted fw-normal">Shipping</dt>
                  <dd className="mb-0">{formatPrice(order.shipping_fee)}</dd>
                </div>
                {Number(order.discount) > 0 && (
                  <div className="d-flex justify-content-between mb-1 text-danger">
                    <dt className="fw-normal">Discount</dt>
                    <dd className="mb-0">−{formatPrice(order.discount)}</dd>
                  </div>
                )}
                {Number(order.tax) > 0 && (
                  <div className="d-flex justify-content-between mb-1">
                    <dt className="text-muted fw-normal">Tax</dt>
                    <dd className="mb-0">{formatPrice(order.tax)}</dd>
                  </div>
                )}
                <hr />
                <div className="d-flex justify-content-between fs-5">
                  <dt className="fw-bold">Total</dt>
                  <dd className="mb-0 fw-bold">{formatPrice(order.total)}</dd>
                </div>
              </dl>
            </Card.Body>
          </Card>

          {order.payment ? (
            <Card className="shadow-sm mt-4">
              <Card.Body>
                <h2 className="h6 text-uppercase text-muted mb-3">Payment</h2>
                <dl className="mb-0">
                  <div className="d-flex justify-content-between mb-1">
                    <dt className="text-muted fw-normal">Method</dt>
                    <dd className="mb-0">{order.payment.payment_method_label}</dd>
                  </div>
                  <div className="d-flex justify-content-between mb-1">
                    <dt className="text-muted fw-normal">Payment number</dt>
                    <dd className="mb-0">{order.payment.payment_number}</dd>
                  </div>
                  <div className="d-flex justify-content-between mb-1">
                    <dt className="text-muted fw-normal">Amount</dt>
                    <dd className="mb-0 fw-semibold">{formatPrice(order.payment.amount)}</dd>
                  </div>
                  <div className="d-flex justify-content-between mb-1">
                    <dt className="text-muted fw-normal">Status</dt>
                    <dd className="mb-0">
                      <OrderStatusBadge status={order.payment.payment_status} type="payment" />
                    </dd>
                  </div>
                  {order.payment.paid_at && (
                    <div className="d-flex justify-content-between mb-1">
                      <dt className="text-muted fw-normal">Paid on</dt>
                      <dd className="mb-0">{formatDate(order.payment.paid_at)}</dd>
                    </div>
                  )}
                </dl>
              </Card.Body>
            </Card>
          ) : order.payment_status === 'unpaid' && !['cancelled', 'refunded'].includes(order.status) ? (
            <div className="mt-4">
              <Button
                as={Link}
                to={`/payment/${order.order_number}`}
                variant="success"
                size="lg"
                className="w-100"
              >
                Complete Payment
              </Button>
            </div>
          ) : null}

          <p className="text-muted small mt-3 mb-0">
            Order placed on {formatDate(order.placed_at || order.created_at)}.
          </p>
        </Col>
      </Row>
    </Container>
  );
}