import { useCallback, useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { Alert, Card, Col, Row } from 'react-bootstrap';
import { adminOrderService } from '../../services/adminOrderService';
import { adminInventoryService } from '../../services/adminInventoryService';
import LoadingSpinner from '../../components/common/LoadingSpinner';
import usePageTitle from '../../hooks/usePageTitle';
import { getErrorMessage } from '../../utils/error';
import { WalletIcon, TruckIcon, CheckCircleIcon, XCircleIcon, BoxesIcon, AlertIcon } from '../../assets/icons';

const STATUS_ORDER = ['pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled', 'refunded'];

const STATUS_LABEL = {
  pending: 'Pending',
  confirmed: 'Confirmed',
  processing: 'Processing',
  shipped: 'Shipped',
  delivered: 'Delivered',
  cancelled: 'Cancelled',
  refunded: 'Refunded',
};

const STATUS_BAR_VARIANT = {
  pending: 'warning',
  confirmed: 'success',
  processing: 'info',
  shipped: 'primary',
  delivered: 'success',
  cancelled: 'danger',
  refunded: 'secondary',
};

const PAYMENT_LABEL = {
  unpaid: 'Unpaid',
  paid: 'Paid',
  refunded: 'Refunded',
  failed: 'Failed',
};

export default function AdminDashboard() {
  usePageTitle('Admin Dashboard');
  const [stats, setStats] = useState(null);
  const [inventory, setInventory] = useState({ total_products: 0, in_stock: 0, low_stock: 0, out_of_stock: 0, total_units: 0 });
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  const load = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      const [orderStats, inventoryStats] = await Promise.all([
        adminOrderService.getStatistics(),
        adminInventoryService.getStatistics(),
      ]);
      setStats(orderStats);
      setInventory(inventoryStats);
    } catch (err) {
      setError(getErrorMessage(err));
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    load();
  }, [load]);

  if (loading) {
    return <LoadingSpinner label="Loading dashboard..." />;
  }

  if (error) {
    return <Alert variant="danger">{error}</Alert>;
  }

  const byStatus = stats.orders_by_status || {};
  const byPayment = stats.orders_by_payment_status || {};
  const totalOrders = Number(stats.total_orders) || 0;

  const statCards = [
    {
      label: 'Total revenue',
      value: `${stats.currency || 'USD'} ${stats.total_revenue ?? '0.00'}`,
      icon: WalletIcon,
      variant: 'success',
      to: '/admin/orders',
    },
    { label: 'Total orders', value: stats.total_orders ?? 0, icon: TruckIcon, variant: 'primary', to: '/admin/orders' },
    { label: 'Paid orders', value: stats.paid_orders_count ?? 0, icon: CheckCircleIcon, variant: 'success', to: '/admin/orders' },
    { label: 'Cancelled orders', value: byStatus.cancelled ?? 0, icon: XCircleIcon, variant: 'danger', to: '/admin/orders' },
  ];

  return (
    <div>
      <div className="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
        <h2 className="h4 mb-0">Dashboard</h2>
        <Link to="/admin/orders" className="link-success">
          View all orders →
        </Link>
      </div>

      <Row className="g-3 mb-4">
        {statCards.map(({ label, value, icon: Icon, variant, to }) => (
          <Col xs={6} lg={3} key={label}>
            <Card as={Link} to={to} className="admin-stat-card h-100 text-reset text-decoration-none shadow-sm">
              <Card.Body className="d-flex align-items-center gap-3">
                <div className={`admin-stat-icon text-bg-${variant}`}>
                  <Icon size={22} />
                </div>
                <div className="min-w-0">
                  <div className="text-muted small text-truncate">{label}</div>
                  <div className="fw-semibold fs-5 text-truncate">{value}</div>
                </div>
              </Card.Body>
            </Card>
          </Col>
        ))}
      </Row>

      <Row className="g-3">
        <Col lg={7}>
          <Card className="shadow-sm h-100">
            <Card.Header className="bg-white">Orders by status</Card.Header>
            <Card.Body>
              {STATUS_ORDER.map((status) => {
                const count = Number(byStatus[status]) || 0;
                const percent = totalOrders > 0 ? Math.round((count / totalOrders) * 100) : 0;
                return (
                  <div key={status} className="mb-3">
                    <div className="d-flex justify-content-between small mb-1">
                      <span className="fw-semibold">{STATUS_LABEL[status]}</span>
                      <span className="text-muted">
                        {count} · {percent}%
                      </span>
                    </div>
                    <div className="progress admin-progress">
                      <div
                        className={`progress-bar bg-${STATUS_BAR_VARIANT[status]}`}
                        style={{ width: `${percent}%` }}
                        role="progressbar"
                      />
                    </div>
                  </div>
                );
              })}
            </Card.Body>
          </Card>
        </Col>

        <Col lg={5}>
          <Card className="shadow-sm h-100">
            <Card.Header className="bg-white">Payment status</Card.Header>
            <Card.Body>
              {Object.keys(PAYMENT_LABEL).map((key) => {
                const count = Number(byPayment[key]) || 0;
                const percent = totalOrders > 0 ? Math.round((count / totalOrders) * 100) : 0;
                return (
                  <div key={key} className="d-flex align-items-center justify-content-between border-bottom py-2">
                    <span className="text-capitalize">{PAYMENT_LABEL[key]}</span>
                    <strong>
                      {count} <span className="text-muted fw-normal">({percent}%)</span>
                    </strong>
                  </div>
                );
              })}
              <div className="text-muted small mt-3">
                Revenue counts only orders confirmed as paid by the payment gateway.
              </div>
            </Card.Body>
          </Card>
        </Col>
      </Row>

      <Row className="g-3 mt-3">
        <Col lg={7}>
          <Card className="shadow-sm h-100">
            <Card.Header className="bg-white d-flex justify-content-between align-items-center">
              <span>Inventory health</span>
              <Link to="/admin/inventory" className="link-success small">
                Manage inventory →
              </Link>
            </Card.Header>
            <Card.Body>
              <Row className="g-3">
                <Col xs={6} sm={3}>
                  <div className="d-flex align-items-center gap-2">
                    <div className="admin-stat-icon text-bg-primary"><BoxesIcon size={20} /></div>
                    <div>
                      <div className="text-muted small">Total products</div>
                      <div className="fw-semibold">{inventory.total_products ?? 0}</div>
                    </div>
                  </div>
                </Col>
                <Col xs={6} sm={3}>
                  <div className="d-flex align-items-center gap-2">
                    <div className="admin-stat-icon text-bg-success"><CheckCircleIcon size={20} /></div>
                    <div>
                      <div className="text-muted small">In stock</div>
                      <div className="fw-semibold">{inventory.in_stock ?? 0}</div>
                    </div>
                  </div>
                </Col>
                <Col xs={6} sm={3}>
                  <div className="d-flex align-items-center gap-2">
                    <div className="admin-stat-icon text-bg-warning"><AlertIcon size={20} /></div>
                    <div>
                      <div className="text-muted small">Low stock</div>
                      <div className="fw-semibold">{inventory.low_stock ?? 0}</div>
                    </div>
                  </div>
                </Col>
                <Col xs={6} sm={3}>
                  <div className="d-flex align-items-center gap-2">
                    <div className="admin-stat-icon text-bg-danger"><XCircleIcon size={20} /></div>
                    <div>
                      <div className="text-muted small">Out of stock</div>
                      <div className="fw-semibold">{inventory.out_of_stock ?? 0}</div>
                    </div>
                  </div>
                </Col>
              </Row>
              <div className="text-muted small mt-3">
                <span className="fw-semibold">{inventory.total_units ?? 0}</span> units on hand. Low- and out-of-stock
                products need attention.
              </div>
            </Card.Body>
          </Card>
        </Col>
      </Row>
    </div>
  );
}