import { useCallback, useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { Alert, Button, Col, Form, InputGroup, Row, Table } from 'react-bootstrap';
import { adminOrderService } from '../../services/adminOrderService';
import OrderStatusBadge from '../../components/orders/OrderStatusBadge';
import LoadingSpinner from '../../components/common/LoadingSpinner';
import EmptyState from '../../components/common/EmptyState';
import StorePagination from '../../components/common/StorePagination';
import usePageTitle from '../../hooks/usePageTitle';
import { formatPrice, formatDate } from '../../utils/format';
import { getErrorMessage } from '../../utils/error';
import { SearchIcon } from '../../assets/icons';

const STATUS_OPTIONS = ['pending', 'confirmed', 'processing', 'packed', 'shipped', 'out_for_delivery', 'delivered', 'cancelled', 'refunded'];
const PAYMENT_STATUS_OPTIONS = ['unpaid', 'paid', 'refunded', 'failed'];
const DATE_PERIODS = [
  { value: '', label: 'Any date' },
  { value: 'today', label: 'Today' },
  { value: 'yesterday', label: 'Yesterday' },
  { value: 'last_7_days', label: 'Last 7 days' },
  { value: 'last_30_days', label: 'Last 30 days' },
  { value: 'this_month', label: 'This month' },
];
const SORT_OPTIONS = [
  { value: 'newest', label: 'Newest first' },
  { value: 'oldest', label: 'Oldest first' },
  { value: 'total_high', label: 'Highest total' },
  { value: 'total_low', label: 'Lowest total' },
];
const PAYMENT_METHODS = [
  { value: '', label: 'Any payment method' },
  { value: 'aba_pay', label: 'ABA Pay' },
  { value: 'khqr', label: 'KHQR' },
  { value: 'card', label: 'Card' },
  { value: 'cod', label: 'Cash on Delivery' },
  { value: 'bank_transfer', label: 'Bank Transfer' },
  { value: 'online', label: 'Online' },
];

const PER_PAGE = 20;

const emptyFilters = {
  search: '',
  status: '',
  payment_status: '',
  payment_method: '',
  date_period: '',
  date_from: '',
  date_to: '',
  sort: 'newest',
};

export default function AdminOrders() {
  usePageTitle('Orders');
  const [filters, setFilters] = useState(emptyFilters);
  const [orders, setOrders] = useState([]);
  const [pagination, setPagination] = useState({ current_page: 1, per_page: PER_PAGE, total: 0, last_page: 1 });
  const [page, setPage] = useState(1);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  const setFilter = (key, value) => {
    setFilters((prev) => ({ ...prev, [key]: value }));
    setPage(1);
  };

  const load = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      const { orders: list, pagination: meta } = await adminOrderService.list({
        page,
        per_page: PER_PAGE,
        search: filters.search || undefined,
        status: filters.status || undefined,
        payment_status: filters.payment_status || undefined,
        payment_method: filters.payment_method || undefined,
        date_period: filters.date_period || undefined,
        date_from: filters.date_from || undefined,
        date_to: filters.date_to || undefined,
        sort: filters.sort || undefined,
      });
      setOrders(list || []);
      setPagination(meta || {});
    } catch (err) {
      setError(getErrorMessage(err));
    } finally {
      setLoading(false);
    }
  }, [page, filters]);

  useEffect(() => {
    load();
  }, [load]);

  const pages = pagination.last_page || 1;

  return (
    <div>
      <div className="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <h2 className="h4 mb-0">Orders</h2>
        <Link to="/admin" className="link-success small">
          ← Back to dashboard
        </Link>
      </div>

      <Row className="g-2 mb-3">
        <Col md={6} lg={5}>
          <InputGroup>
            <InputGroup.Text className="bg-white">
              <SearchIcon size={16} className="text-muted" />
            </InputGroup.Text>
            <Form.Control
              type="search"
              placeholder="Order number, customer name, email or phone..."
              value={filters.search}
              onChange={(e) => setFilter('search', e.target.value)}
            />
          </InputGroup>
        </Col>
        <Col xs={6} md={4} lg={2}>
          <Form.Select value={filters.status} onChange={(e) => setFilter('status', e.target.value)}>
            <option value="">All statuses</option>
            {STATUS_OPTIONS.map((s) => (
              <option key={s} value={s}>
                {s.charAt(0).toUpperCase() + s.slice(1)}
              </option>
            ))}
          </Form.Select>
        </Col>
        <Col xs={6} md={4} lg={2}>
          <Form.Select value={filters.payment_status} onChange={(e) => setFilter('payment_status', e.target.value)}>
            <option value="">Any payment</option>
            {PAYMENT_STATUS_OPTIONS.map((s) => (
              <option key={s} value={s}>
                {s.charAt(0).toUpperCase() + s.slice(1)}
              </option>
            ))}
          </Form.Select>
        </Col>
        <Col xs={6} md={4} lg={3}>
          <Form.Select value={filters.payment_method} onChange={(e) => setFilter('payment_method', e.target.value)}>
            {PAYMENT_METHODS.map((m) => (
              <option key={m.value} value={m.value}>
                {m.label}
              </option>
            ))}
          </Form.Select>
        </Col>
        <Col xs={6} md={4} lg={3}>
          <Form.Select value={filters.date_period} onChange={(e) => setFilter('date_period', e.target.value)}>
            {DATE_PERIODS.map((d) => (
              <option key={d.value} value={d.value}>
                {d.label}
              </option>
            ))}
          </Form.Select>
        </Col>
        <Col xs={6} md={4} lg={3}>
          <Form.Control
            type="date"
            value={filters.date_from}
            onChange={(e) => setFilter('date_from', e.target.value)}
            aria-label="From date"
          />
        </Col>
        <Col xs={6} md={4} lg={3}>
          <Form.Control
            type="date"
            value={filters.date_to}
            onChange={(e) => setFilter('date_to', e.target.value)}
            aria-label="To date"
          />
        </Col>
        <Col xs={6} md={4} lg={2}>
          <Form.Select value={filters.sort} onChange={(e) => setFilter('sort', e.target.value)}>
            {SORT_OPTIONS.map((s) => (
              <option key={s.value} value={s.value}>
                {s.label}
              </option>
            ))}
          </Form.Select>
        </Col>
      </Row>

      {error && <Alert variant="danger">{error}</Alert>}

      {loading ? (
        <LoadingSpinner label="Loading orders..." />
      ) : orders.length === 0 ? (
        <EmptyState title="No orders found" message="Try adjusting your search or filters." />
      ) : (
        <>
          <div className="table-responsive admin-orders-table">
            <Table hover striped>
              <thead>
                <tr>
                  <th>Order</th>
                  <th>Customer</th>
                  <th>Placed</th>
                  <th className="text-end">Total</th>
                  <th>Status</th>
                  <th>Payment</th>
                  <th className="text-end">Actions</th>
                </tr>
              </thead>
              <tbody>
                {orders.map((order) => (
                  <tr key={order.id}>
                    <td>
                      <span className="fw-semibold">{order.order_number}</span>
                    </td>
                    <td>
                      <div>{order.customer?.name || '—'}</div>
                      <div className="text-muted small">{order.customer?.email || ''}</div>
                    </td>
                    <td>{formatDate(order.placed_at || order.created_at)}</td>
                    <td className="text-end fw-semibold">{formatPrice(order.total)}</td>
                    <td>
                      <OrderStatusBadge status={order.status} />
                    </td>
                    <td>
                      <OrderStatusBadge status={order.payment_status} type="payment" />
                    </td>
                    <td className="text-end">
                      <Button size="sm" variant="outline-primary" as={Link} to={`/admin/orders/${order.id}`}>
                        View
                      </Button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </Table>
          </div>

          {pages > 1 && (
            <StorePagination
              pagination={pagination}
              onPageChange={setPage}
              disabled={loading}
              ariaLabel="Orders pagination"
            />
          )}
        </>
      )}
    </div>
  );
}