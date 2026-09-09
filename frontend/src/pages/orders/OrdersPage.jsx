import { useCallback, useEffect, useRef, useState } from 'react';
import { Link } from 'react-router-dom';
import { Alert, Button, Card, Col, Pagination, Row } from 'react-bootstrap';
import OrderStatusBadge from '../../components/orders/OrderStatusBadge';
import EmptyState from '../../components/common/EmptyState';
import ImageWithFallback from '../../components/common/ImageWithFallback';
import Skeleton from '../../components/common/Skeleton';
import AccountLayout from '../../layouts/AccountLayout';
import Breadcrumbs from '../../components/common/Breadcrumbs';
import { orderService } from '../../services/orderService';
import usePageTitle from '../../hooks/usePageTitle';
import { formatPrice, formatDate } from '../../utils/format';
import { getErrorMessage } from '../../utils/error';
import { PackageIcon, ClockIcon } from '../../assets/icons';

const PER_PAGE = 10;
const POLL_INTERVAL = 8000;

function OrderCardSkeleton() {
  return (
    <Card className="account-card shadow-sm mb-3">
      <Card.Body>
        <div className="d-flex flex-column flex-md-row gap-3">
          <Skeleton variant="thumbnail" />
          <div className="flex-grow-1">
            <Skeleton variant="text" style={{ width: '40%' }} />
            <Skeleton variant="text" style={{ width: '70%' }} />
          </div>
        </div>
      </Card.Body>
    </Card>
  );
}

export default function OrdersPage() {
  usePageTitle('My Orders');
  const [orders, setOrders] = useState([]);
  const [page, setPage] = useState(1);
  const [pagination, setPagination] = useState({ current_page: 1, per_page: PER_PAGE, total: 0, last_page: 1 });
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [updatedAt, setUpdatedAt] = useState(null);
  const [justUpdated, setJustUpdated] = useState(false);
  const updatedAtRef = useRef(null);

  const load = useCallback(async (pageNumber, silent = false) => {
    if (!silent) {
      setLoading(true);
      setError('');
    }
    try {
      const { orders: list, pagination: meta } = await orderService.list(pageNumber, PER_PAGE);
      const prev = updatedAtRef.current;
      const next = `${meta?.total ?? 0}:${(list || []).map((o) => o.status).join(',')}`;
      if (prev && prev !== next) {
        setJustUpdated(true);
        window.setTimeout(() => setJustUpdated(false), 4000);
      }
      updatedAtRef.current = next;
      setOrders(list || []);
      setPagination(meta || {});
      setUpdatedAt(new Date());
    } catch (err) {
      if (!silent) setError(getErrorMessage(err));
    } finally {
      if (!silent) setLoading(false);
    }
  }, []);

  useEffect(() => {
    load(page);
  }, [load, page]);

  // Poll for live order status updates (only while this page is mounted and
  // the tab is visible). Stops automatically on unmount / tab switch.
  useEffect(() => {
    let timer;
    const tick = () => {
      if (document.visibilityState === 'visible') {
        load(page, true);
      }
    };
    timer = window.setInterval(tick, POLL_INTERVAL);
    return () => window.clearInterval(timer);
  }, [load, page]);

  const pages = pagination.last_page || 1;
  const pageItems = [];
  for (let p = 1; p <= pages; p += 1) {
    pageItems.push(
      <Pagination.Item key={p} active={p === page} onClick={() => setPage(p)}>
        {p}
      </Pagination.Item>
    );
  }

  return (
    <AccountLayout>
      <Breadcrumbs items={[{ label: 'Home', to: '/' }, { label: 'My Account', to: '/account/profile' }, { label: 'My Orders' }]} />

      <div className="account-page-header">
        <h1 className="h3 mb-1 d-flex align-items-center gap-2">
          <PackageIcon size={26} className="text-success" />
          My Orders
        </h1>
        <p className="text-muted mb-0 d-flex align-items-center gap-2">
          Review and track the orders you've placed.
          {justUpdated && <span className="account-just-updated"><ClockIcon size={14} /> Updated just now</span>}
        </p>
      </div>

      {error && <Alert variant="danger">{error}</Alert>}

      {loading ? (
        <>
          <OrderCardSkeleton />
          <OrderCardSkeleton />
          <OrderCardSkeleton />
        </>
      ) : orders.length === 0 ? (
        <EmptyState
          title="No orders yet"
          message="Once you place your first order, it will show up here."
          actionLabel="Start Shopping"
          actionTo="/shop"
        />
      ) : (
        <>
          <Row className="g-3">
            {orders.map((order) => {
              const items = order.items || [];
              const thumbnails = items.slice(0, 4);
              const totalItems = items.reduce((sum, it) => sum + (it.quantity || 0), 0);
              const img = (it) =>
                it.product?.primary_image?.url || it.product?.images?.[0]?.url || null;

              return (
                <Col xs={12} key={order.id}>
                  <Card className="account-card order-card shadow-sm">
                    <Card.Body className="d-flex flex-column flex-md-row gap-3 align-items-md-center">
                      {/* Thumbnails */}
                      <div className="order-thumbnails flex-shrink-0 d-flex">
                        {thumbnails.length > 0 ? (
                          thumbnails.map((it) => (
                            <div className="order-thumb" key={it.id}>
                              <ImageWithFallback src={img(it)} alt={it.product_name || ''} className="w-100 h-100" />
                            </div>
                          ))
                        ) : (
                          <div className="order-thumb order-thumb-empty">
                            <PackageIcon size={20} className="text-muted" />
                          </div>
                        )}
                        {items.length > 4 && (
                          <div className="order-thumb order-thumb-more">+{items.length - 4}</div>
                        )}
                      </div>

                      {/* Info */}
                      <div className="flex-grow-1 min-w-0">
                        <div className="d-flex flex-wrap align-items-center gap-2">
                          <Link
                            to={`/account/orders/${order.order_number}`}
                            className="fw-semibold text-decoration-none text-reset order-number-link"
                          >
                            {order.order_number}
                          </Link>
                        </div>
                        <div className="text-muted small mt-1 d-flex flex-wrap align-items-center gap-2">
                          <span>Placed on {formatDate(order.placed_at || order.created_at)}</span>
                          <span aria-hidden="true">·</span>
                          <span>{totalItems} item{totalItems === 1 ? '' : 's'}</span>
                          <span aria-hidden="true">·</span>
                          <span className="fw-semibold text-success">{formatPrice(order.total)}</span>
                        </div>
                        <div className="d-flex flex-wrap align-items-center gap-2 mt-2">
                          <OrderStatusBadge status={order.status} />
                          <OrderStatusBadge status={order.payment_status} type="payment" />
                        </div>
                      </div>

                      {/* Actions */}
                      <div className="flex-shrink-0 d-flex flex-column flex-sm-row gap-2">
                        {order.payment_status === 'unpaid' && !['cancelled', 'refunded'].includes(order.status) && (
                          <Button
                            as={Link}
                            to={`/payment/${order.order_number}`}
                            variant="success"
                            size="sm"
                          >
                            Pay Now
                          </Button>
                        )}
                        <Button
                          as={Link}
                          to={`/account/orders/${order.order_number}`}
                          variant="outline-success"
                          size="sm"
                        >
                          View Details
                        </Button>
                      </div>
                    </Card.Body>
                  </Card>
                </Col>
              );
            })}
          </Row>

          {pages > 1 && (
            <Pagination className="justify-content-center mt-4">{pageItems}</Pagination>
          )}
        </>
      )}
    </AccountLayout>
  );
}
