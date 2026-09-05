import { useCallback, useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { Alert, Card, Col, Row, Form } from 'react-bootstrap';
import { Chart as ChartJS, ArcElement, Tooltip, Legend, CategoryScale, LinearScale, BarElement, PointElement, LineElement, Filler } from 'chart.js';
import { Doughnut, Bar, Line } from 'react-chartjs-2';
import { adminOrderService } from '../../services/adminOrderService';
import { adminInventoryService } from '../../services/adminInventoryService';
import LoadingSpinner from '../../components/common/LoadingSpinner';
import usePageTitle from '../../hooks/usePageTitle';
import { getErrorMessage } from '../../utils/error';
import { WalletIcon, TruckIcon, CheckCircleIcon, XCircleIcon, BoxesIcon, AlertIcon } from '../../assets/icons';

ChartJS.register(ArcElement, Tooltip, Legend, CategoryScale, LinearScale, BarElement, PointElement, LineElement, Filler);

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

const STATUS_COLOR = {
  pending: '#f59e0b',
  confirmed: '#16a34a',
  processing: '#06b6d4',
  shipped: '#3b82f6',
  delivered: '#22c55e',
  cancelled: '#ef4444',
  refunded: '#94a3b8',
};

const PAYMENT_LABEL = {
  unpaid: 'Unpaid',
  paid: 'Paid',
  refunded: 'Refunded',
  failed: 'Failed',
};

const PAYMENT_COLOR = {
  unpaid: '#f59e0b',
  paid: '#22c55e',
  refunded: '#94a3b8',
  failed: '#ef4444',
};

export default function AdminDashboard() {
  usePageTitle('Admin Dashboard');
  const [stats, setStats] = useState(null);
  const [inventory, setInventory] = useState({ total_products: 0, in_stock: 0, low_stock: 0, out_of_stock: 0, total_units: 0 });
  const [revenueTrend, setRevenueTrend] = useState(null);
  const [trendDays, setTrendDays] = useState(30);
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

      try {
        const trend = await adminOrderService.getRevenueTrend(trendDays);
        setRevenueTrend(trend);
      } catch {
        setRevenueTrend(null);
      }
    } catch (err) {
      setError(getErrorMessage(err));
    } finally {
      setLoading(false);
    }
  }, [trendDays]);

  useEffect(() => { load(); }, [load]);

  const loadTrend = useCallback(async (days) => {
    setTrendDays(days);
    try {
      const trend = await adminOrderService.getRevenueTrend(days);
      setRevenueTrend(trend);
    } catch {
      setRevenueTrend(null);
    }
  }, []);

  if (loading) return <LoadingSpinner label="Loading dashboard..." />;
  if (error) return <Alert variant="danger">{error}</Alert>;

  const byStatus = stats.orders_by_status || {};
  const byPayment = stats.orders_by_payment_status || {};
  const totalOrders = Number(stats.total_orders) || 0;

  /* ---- Chart data: Orders by status (Doughnut) ---- */
  const statusData = {
    labels: STATUS_ORDER.map((s) => STATUS_LABEL[s]),
    datasets: [{
      data: STATUS_ORDER.map((s) => Number(byStatus[s]) || 0),
      backgroundColor: STATUS_ORDER.map((s) => STATUS_COLOR[s]),
      borderWidth: 2,
      borderColor: '#ffffff',
    }],
  };

  const statusOptions = {
    responsive: true,
    maintainAspectRatio: false,
    cutout: '60%',
    plugins: {
      legend: {
        position: 'right',
        labels: {
          boxWidth: 12,
          padding: 12,
          font: { size: 12 },
        },
      },
    },
  };

  /* ---- Chart data: Payment status (horizontal Bar) ---- */
  const paymentKeys = Object.keys(PAYMENT_LABEL);
  const paymentData = {
    labels: paymentKeys.map((k) => PAYMENT_LABEL[k]),
    datasets: [{
      data: paymentKeys.map((k) => Number(byPayment[k]) || 0),
      backgroundColor: paymentKeys.map((k) => PAYMENT_COLOR[k]),
      borderRadius: 4,
      barThickness: 24,
    }],
  };

  const paymentOptions = {
    indexAxis: 'y',
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: { display: false },
      tooltip: {
        callbacks: {
          label: (ctx) => {
            const pct = totalOrders > 0 ? Math.round((ctx.raw / totalOrders) * 100) : 0;
            return `${ctx.raw} (${pct}%)`;
          },
        },
      },
    },
    scales: {
      x: {
        beginAtZero: true,
        grid: { display: false },
        ticks: { font: { size: 11 } },
      },
      y: {
        grid: { display: false },
        ticks: { font: { size: 12 } },
      },
    },
  };

  /* ---- Chart data: Revenue over time (Line) ---- */
  const trendDataPoints = revenueTrend?.data || [];
  const revenueData = {
    labels: trendDataPoints.map((d) => {
      const dt = new Date(d.date);
      return `${dt.getMonth() + 1}/${dt.getDate()}`;
    }),
    datasets: [{
      label: 'Revenue',
      data: trendDataPoints.map((d) => Number(d.revenue) || 0),
      borderColor: '#16a34a',
      backgroundColor: 'rgba(22, 163, 74, 0.1)',
      fill: true,
      tension: 0.35,
      pointRadius: trendDataPoints.length > 30 ? 0 : 3,
      pointHoverRadius: 5,
    }],
  };

  const revenueOptions = {
    responsive: true,
    maintainAspectRatio: false,
    interaction: { mode: 'index', intersect: false },
    plugins: {
      legend: { display: false },
      tooltip: {
        callbacks: {
          label: (ctx) => `$${Number(ctx.raw).toFixed(2)}`,
        },
      },
    },
    scales: {
      x: {
        grid: { display: false },
        ticks: {
          font: { size: 10 },
          maxTicksLimit: 12,
          maxRotation: 0,
        },
      },
      y: {
        beginAtZero: true,
        grid: { color: '#f0f0f0' },
        ticks: {
          font: { size: 11 },
          callback: (v) => `$${v >= 1000 ? (v / 1000).toFixed(1) + 'k' : v}`,
        },
      },
    },
  };

  /* ---- Stat cards ---- */
  const statCards = [
    { label: 'Total revenue', value: `${stats.currency || 'USD'} ${stats.total_revenue ?? '0.00'}`, icon: WalletIcon, variant: 'success', to: '/admin/orders' },
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

      {/* Revenue over time */}
      <Card className="shadow-sm mb-4">
        <Card.Header className="bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
          <span className="fw-semibold">Revenue over time</span>
          <div className="d-flex align-items-center gap-2">
            <span className="text-muted small">
              {revenueTrend ? (
                <>${Number(revenueTrend.total_revenue).toLocaleString()} from {revenueTrend.total_orders} orders</>
              ) : 'Loading...'}
            </span>
            <Form.Select
              size="sm"
              style={{ width: 100 }}
              value={trendDays}
              onChange={(e) => loadTrend(Number(e.target.value))}
            >
              <option value={7}>Last 7 days</option>
              <option value={14}>Last 14 days</option>
              <option value={30}>Last 30 days</option>
              <option value={60}>Last 60 days</option>
              <option value={90}>Last 90 days</option>
            </Form.Select>
          </div>
        </Card.Header>
        <Card.Body>
          {trendDataPoints.length > 0 ? (
            <div style={{ height: 260 }}>
              <Line data={revenueData} options={revenueOptions} />
            </div>
          ) : (
            <div className="text-muted text-center py-4">
              No paid orders in the selected period.
            </div>
          )}
        </Card.Body>
      </Card>

      <Row className="g-3">
        {/* Orders by status — Doughnut */}
        <Col lg={7}>
          <Card className="shadow-sm h-100">
            <Card.Header className="bg-white fw-semibold">Orders by status</Card.Header>
            <Card.Body>
              <div className="d-flex align-items-center gap-4">
                <div style={{ width: 180, height: 180, flexShrink: 0 }}>
                  <Doughnut data={statusData} options={statusOptions} />
                </div>
                <div className="flex-grow-1">
                  {STATUS_ORDER.map((status) => {
                    const count = Number(byStatus[status]) || 0;
                    const percent = totalOrders > 0 ? Math.round((count / totalOrders) * 100) : 0;
                    return (
                      <div key={status} className="d-flex align-items-center justify-content-between small py-1 border-bottom">
                        <span className="d-flex align-items-center gap-2">
                          <span
                            className="d-inline-block rounded-circle"
                            style={{ width: 10, height: 10, backgroundColor: STATUS_COLOR[status] }}
                          />
                          {STATUS_LABEL[status]}
                        </span>
                        <span className="fw-semibold">
                          {count} <span className="text-muted fw-normal">({percent}%)</span>
                        </span>
                      </div>
                    );
                  })}
                </div>
              </div>
            </Card.Body>
          </Card>
        </Col>

        {/* Payment status — horizontal Bar */}
        <Col lg={5}>
          <Card className="shadow-sm h-100">
            <Card.Header className="bg-white fw-semibold">Payment status</Card.Header>
            <Card.Body>
              <div style={{ height: 200 }}>
                <Bar data={paymentData} options={paymentOptions} />
              </div>
              <div className="text-muted small mt-2">
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
              <span className="fw-semibold">Inventory health</span>
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
