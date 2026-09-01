import { useCallback, useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { Alert, Button, Card, Col, Container, Pagination, Row } from 'react-bootstrap';
import OrderStatusBadge from '../../components/orders/OrderStatusBadge';
import EmptyState from '../../components/common/EmptyState';
import LoadingSpinner from '../../components/common/LoadingSpinner';
import PageHeader from '../../components/common/PageHeader';
import { orderService } from '../../services/orderService';
import usePageTitle from '../../hooks/usePageTitle';
import { formatPrice, formatDate } from '../../utils/format';
import { getErrorMessage } from '../../utils/error';
import { TruckIcon } from '../../assets/icons';

const PER_PAGE = 10;

export default function OrdersPage() {
  usePageTitle('My Orders');
  const [orders, setOrders] = useState([]);
  const [page, setPage] = useState(1);
  const [pagination, setPagination] = useState({ current_page: 1, per_page: PER_PAGE, total: 0, last_page: 1 });
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  const load = useCallback(async (pageNumber) => {
    setLoading(true);
    setError('');
    try {
      const { orders: list, pagination: meta } = await orderService.list(pageNumber, PER_PAGE);
      setOrders(list || []);
      setPagination(meta || {});
    } catch (err) {
      setError(getErrorMessage(err));
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    load(page);
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
    <Container className="py-4">
      <PageHeader title="My Orders" subtitle="Review and track the orders you've placed." />

      {error && <Alert variant="danger">{error}</Alert>}

      {loading ? (
        <LoadingSpinner label="Loading your orders..." />
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
            {orders.map((order) => (
              <Col md={12} key={order.id}>
                <Card className="shadow-sm">
                  <Card.Body className="d-flex flex-column flex-md-row gap-3 align-items-md-center">
                    <div className="order-list-icon flex-shrink-0 d-none d-md-flex">
                      <TruckIcon size={28} className="text-success" />
                    </div>

                    <div className="flex-grow-1 min-w-0">
                      <div className="d-flex flex-wrap align-items-center gap-2">
                        <Link
                          to={`/orders/${order.order_number}`}
                          className="fw-semibold text-decoration-none text-reset"
                        >
                          {order.order_number}
                        </Link>
                        <OrderStatusBadge status={order.status} />
                        <OrderStatusBadge status={order.payment_status} type="payment" />
                      </div>
                      <div className="text-muted small mt-1">
                        Placed on {formatDate(order.placed_at || order.created_at)} ·{' '}
                        {formatPrice(order.total)}
                      </div>
                    </div>

                    <div className="flex-shrink-0 align-self-start align-self-md-center d-flex flex-column flex-md-row gap-2">
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
                        to={`/orders/${order.order_number}`}
                        variant="outline-success"
                        size="sm"
                      >
                        View Details
                      </Button>
                    </div>
                  </Card.Body>
                </Card>
              </Col>
            ))}
          </Row>

          {pages > 1 && (
            <Pagination className="justify-content-center mt-4">{pageItems}</Pagination>
          )}
        </>
      )}
    </Container>
  );
}