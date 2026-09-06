import { useCallback, useEffect, useRef, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import {
  Alert, Badge, Button, Card, Col, Container, Row, Spinner,
} from 'react-bootstrap';
import OrderStatusBadge from '../../components/orders/OrderStatusBadge';
import OrderTimeline from '../../components/orders/OrderTimeline';
import ImageWithFallback from '../../components/common/ImageWithFallback';
import LoadingSpinner from '../../components/common/LoadingSpinner';
import ConfirmDialog from '../../components/common/ConfirmDialog';
import AccountLayout from '../../layouts/AccountLayout';
import Breadcrumbs from '../../components/common/Breadcrumbs';
import { orderService } from '../../services/orderService';
import { useCart } from '../../context/CartContext';
import usePageTitle from '../../hooks/usePageTitle';
import { formatPrice, formatDate } from '../../utils/format';
import { getErrorMessage } from '../../utils/error';
import { useToast } from '../../context/ToastContext';
import {
  ClockIcon, MapPinIcon, TruckIcon, RefreshIcon,
} from '../../assets/icons';

const POLL_INTERVAL = 8000;

const CANCELLABLE = ['pending', 'confirmed', 'processing', 'packed'];

export default function OrderDetailPage() {
  usePageTitle('Order Details');
  const { orderNumber } = useParams();
  const { showToast } = useToast();
  const { addToCart } = useCart();
  const navigate = useNavigate();
  const loadedRef = useRef('');
  const timelineRef = useRef(null);

  const [order, setOrder] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [justUpdated, setJustUpdated] = useState(false);
  const [cancelBusy, setCancelBusy] = useState(false);
  const [showCancel, setShowCancel] = useState(false);
  const [reordering, setReordering] = useState(false);

  const scrollToTimeline = () => {
    timelineRef.current?.scrollIntoView({ behavior: 'smooth', block: 'start' });
  };

  const load = useCallback(async (silent = false) => {
    if (!silent) {
      setLoading(true);
      setError('');
    }
    try {
      const fetched = await orderService.get(orderNumber);
      if (loadedRef.current && loadedRef.current !== fetched.status) {
        setJustUpdated(true);
        window.setTimeout(() => setJustUpdated(false), 4000);
      }
      loadedRef.current = fetched.status;
      setOrder(fetched);
    } catch (err) {
      if (!silent) setError(getErrorMessage(err));
    } finally {
      if (!silent) setLoading(false);
    }
  }, [orderNumber]);

  useEffect(() => {
    load();
  }, [load]);

  // Poll for live status updates while this detail page is open & visible.
  useEffect(() => {
    let timer;
    const tick = () => {
      if (document.visibilityState === 'visible') {
        load(true);
      }
    };
    timer = window.setInterval(tick, POLL_INTERVAL);
    return () => window.clearInterval(timer);
  }, [load]);

  const confirmCancel = async () => {
    setCancelBusy(true);
    try {
      const updated = await orderService.cancel(orderNumber);
      setOrder(updated);
      setShowCancel(false);
      if (updated?.payment_status === 'paid') {
        showToast('Order cancelled. Refund processing required.');
      } else {
        showToast('Order cancelled.');
      }
    } catch (err) {
      showToast(getErrorMessage(err), 'danger');
    } finally {
      setCancelBusy(false);
    }
  };

  const handleReorder = async () => {
    if (!order?.items?.length) return;
    setReordering(true);
    try {
      for (const item of order.items) {
        if (!item.product_id) continue;
        await addToCart(item.product_id, item.quantity);
      }
      showToast('Products added back to your cart.');
      navigate('/cart');
    } catch (err) {
      showToast(getErrorMessage(err), 'danger');
    } finally {
      setReordering(false);
    }
  };

  if (loading) {
    return (
      <AccountLayout>
        <Container className="py-5">
          <LoadingSpinner label="Loading order details..." />
        </Container>
      </AccountLayout>
    );
  }

  if (error || !order) {
    return (
      <AccountLayout>
        <Container className="py-5">
          <Alert variant="danger">{error || 'Order not found.'}</Alert>
          <Button as={Link} to="/account/orders" variant="outline-success">
            Back to My Orders
          </Button>
        </Container>
      </AccountLayout>
    );
  }

  const address = order.shipping_address;
  const canCancel = CANCELLABLE.includes(order.status);

  return (
    <AccountLayout>
      <Breadcrumbs items={[
        { label: 'Home', to: '/' },
        { label: 'My Account', to: '/account/profile' },
        { label: 'My Orders', to: '/account/orders' },
        { label: order.order_number },
      ]} />

      <div className="account-page-header">
        <div className="d-flex flex-wrap align-items-center justify-content-between gap-2">
          <h1 className="h3 mb-0 d-flex align-items-center gap-2 flex-wrap">
            <TruckIcon size={26} className="text-success" />
            {order.order_number}
            <OrderStatusBadge status={order.status} />
            <OrderStatusBadge status={order.payment_status} type="payment" />
          </h1>
          <Button as={Link} to="/account/orders" variant="outline-success" size="sm">
            Back to My Orders
          </Button>
        </div>
        <p className="text-muted mb-0 mt-1 d-flex align-items-center gap-2">
          Order placed on {formatDate(order.placed_at || order.created_at)}.
          {justUpdated && <span className="account-just-updated"><ClockIcon size={14} /> Updated just now</span>}
        </p>
      </div>

      {/* Order tracking timeline */}
      <Card ref={timelineRef} className="account-card shadow-sm mb-4">
        <Card.Body>
          <h2 className="h6 text-uppercase text-muted mb-3">Order Tracking</h2>
          <OrderTimeline status={order.status} />
        </Card.Body>
      </Card>

      <Row className="g-4">
        <Col lg={8}>
          <Card className="account-card shadow-sm mb-4">
            <Card.Body>
              <h2 className="h6 text-uppercase text-muted mb-3">
                Items ({order.items.length})
              </h2>
              {order.items.map((item) => {
                const url = item.product ? `/products/${item.product.slug}` : null;
                const img = item.product?.primary_image?.url || item.product?.images?.[0]?.url;
                const title = item.product_name;
                const body = (
                  <div className="d-flex gap-3 align-items-center order-item-row py-2 border-bottom">
                    <div className="order-summary-item-image flex-shrink-0">
                      {img ? (
                        <ImageWithFallback src={img} alt={title} className="w-100 h-100" />
                      ) : (
                        <div className="w-100 h-100 d-flex align-items-center justify-content-center text-muted">—</div>
                      )}
                    </div>
                    <div className="flex-grow-1 min-w-0">
                      <div className="fw-semibold text-truncate">{title}</div>
                      <div className="small text-muted">
                        {formatPrice(item.unit_price)} × {item.quantity}
                      </div>
                    </div>
                    <div className="fw-semibold flex-shrink-0">{formatPrice(item.line_total)}</div>
                  </div>
                );
                return url ? (
                  <Link key={item.id} to={url} className="text-decoration-none text-reset d-block order-item-row">
                    {body}
                  </Link>
                ) : (
                  <div key={item.id}>{body}</div>
                );
              })}
            </Card.Body>
          </Card>

          {address && (
            <Card className="account-card shadow-sm mb-4">
              <Card.Body>
                <h2 className="h6 text-uppercase text-muted mb-3 d-flex align-items-center gap-2">
                  <MapPinIcon size={18} />
                  Shipping Address
                </h2>
                <div className="small lh-sm">
                  <div className="fw-semibold">{address.recipient_name || address.full_name || address.name}</div>
                  <div className="text-muted">Phone: {address.recipient_phone || address.phone || '—'}</div>
                  <div className="mt-1">{address.address_line1 || address.street_address}</div>
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

          {/* Status history */}
          {(order.status_histories || []).length > 0 && (
            <Card className="account-card shadow-sm">
              <Card.Body>
                <h2 className="h6 text-uppercase text-muted mb-3">Order Status History</h2>
                <div className="order-history-list">
                  {(order.status_histories || []).map((event) => (
                    <div key={event.id} className="order-history-item d-flex align-items-start gap-2 mb-2">
                      <div className="order-history-dot" />
                      <div className="flex-grow-1">
                        <div className="small">
                          <Badge bg="secondary" className="text-capitalize">{event.old_status}</Badge>
                          <span className="mx-1">→</span>
                          <Badge bg="success" className="text-capitalize">{event.new_status}</Badge>
                          <span className="text-muted small ms-2">{formatDate(event.created_at)}</span>
                        </div>
                        {event.note && <div className="text-muted small mt-1">“{event.note}”</div>}
                      </div>
                    </div>
                  ))}
                </div>
              </Card.Body>
            </Card>
          )}
        </Col>

        <Col lg={4}>
          <Card className="account-card shadow-sm">
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
            <Card className="account-card shadow-sm mt-3">
              <Card.Body>
                <h2 className="h6 text-uppercase text-muted mb-3">Payment</h2>
                <dl className="mb-0">
                  <div className="d-flex justify-content-between mb-1">
                    <dt className="text-muted fw-normal">Method</dt>
                    <dd className="mb-0">{order.payment.payment_method_label}</dd>
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
            <div className="mt-3">
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

          {/* Order actions */}
          <Card className="account-card shadow-sm mt-3">
            <Card.Body>
              <h2 className="h6 text-uppercase text-muted mb-3">Actions</h2>
              <div className="d-flex flex-column gap-2">
                <Button
                  variant="outline-success"
                  className="w-100"
                  onClick={scrollToTimeline}
                >
                  Track Order
                </Button>
                {order.status === 'delivered' && (
                  <Button
                    variant="outline-success"
                    className="w-100"
                    onClick={handleReorder}
                    disabled={reordering}
                  >
                    {reordering ? (
                      <>
                        <Spinner animation="border" size="sm" className="me-1" />
                        Reordering...
                      </>
                    ) : (
                      <>
                        <RefreshIcon size={16} className="me-2" />
                        Reorder
                      </>
                    )}
                  </Button>
                )}
                {canCancel && (
                  <Button
                    variant="outline-danger"
                    className="w-100"
                    onClick={() => setShowCancel(true)}
                  >
                    Cancel Order
                  </Button>
                )}
              </div>
              {canCancel && (
                <p className="text-muted small mt-2 mb-0">
                  Stock will be restored and pending payments cancelled.
                </p>
              )}
            </Card.Body>
          </Card>
        </Col>
      </Row>

      <ConfirmDialog
        show={showCancel}
        title="Cancel this order?"
        message="Are you sure you want to cancel this order? Reserved stock will be restored and pending payment attempts will be cancelled. This action cannot be undone."
        confirmLabel="Yes, cancel order"
        busy={cancelBusy}
        onCancel={() => setShowCancel(false)}
        onConfirm={confirmCancel}
      />
    </AccountLayout>
  );
}
