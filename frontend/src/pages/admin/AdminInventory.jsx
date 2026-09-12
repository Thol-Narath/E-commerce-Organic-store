import { useCallback, useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import {
  Alert, Button, Card, Col, Form, InputGroup, Row, Table,
} from 'react-bootstrap';
import { adminInventoryService } from '../../services/adminInventoryService';
import { adminCategoryService } from '../../services/adminCategoryService';
import InventoryStatusBadge from '../../components/inventory/InventoryStatusBadge';
import StockActionModal from '../../components/inventory/StockActionModal';
import LoadingSpinner from '../../components/common/LoadingSpinner';
import EmptyState from '../../components/common/EmptyState';
import StorePagination from '../../components/common/StorePagination';
import usePageTitle from '../../hooks/usePageTitle';
import { normalizeError } from '../../services/api';
import { AlertIcon, BoxesIcon, MinusIcon, PlusIcon, SearchIcon } from '../../assets/icons';

const STOCK_FILTERS = [
  { value: '', label: 'Any stock status' },
  { value: 'in_stock', label: 'In stock' },
  { value: 'low_stock', label: 'Low stock' },
  { value: 'out_of_stock', label: 'Out of stock' },
];

const SORT_OPTIONS = [
  { value: 'recently_updated', label: 'Recently updated' },
  { value: 'oldest_updated', label: 'Oldest updated' },
  { value: 'stock_high', label: 'Stock: high to low' },
  { value: 'stock_low', label: 'Stock: low to high' },
  { value: 'name_asc', label: 'Name: A to Z' },
  { value: 'name_desc', label: 'Name: Z to A' },
];

const PER_PAGE = 20;

function StatCard({ label, value, icon: Icon, variant, onClick }) {
  return (
    <Card className="admin-stat-card admin-stat-card-clickable h-100 shadow-sm" role="button" onClick={onClick}>
      <Card.Body className="d-flex align-items-center gap-3">
        <div className={`admin-stat-icon text-bg-${variant}`}>
          <Icon size={22} />
        </div>
        <div className="min-w-0">
          <div className="text-muted small text-truncate">{label}</div>
          <div className="fw-semibold fs-5">{value}</div>
        </div>
      </Card.Body>
    </Card>
  );
}

export default function AdminInventory() {
  usePageTitle('Inventory');

  const [stats, setStats] = useState({ total_products: 0, in_stock: 0, low_stock: 0, out_of_stock: 0, total_units: 0 });
  const [categories, setCategories] = useState([]);
  const [filters, setFilters] = useState({ search: '', category_id: '', stock: '', sort: 'recently_updated' });
  const [items, setItems] = useState([]);
  const [pagination, setPagination] = useState({ current_page: 1, per_page: PER_PAGE, total: 0, last_page: 1 });
  const [page, setPage] = useState(1);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [modal, setModal] = useState({ show: false, mode: 'add', product: null });

  const setFilter = (key, value) => {
    setFilters((prev) => ({ ...prev, [key]: value }));
    setPage(1);
  };

  const load = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      const { items: list, pagination: meta } = await adminInventoryService.list({
        page,
        per_page: PER_PAGE,
        search: filters.search || undefined,
        category_id: filters.category_id || undefined,
        stock: filters.stock || undefined,
        sort: filters.sort || undefined,
      });
      setItems(list || []);
      setPagination(meta || {});
    } catch (err) {
      setError(normalizeError(err).message);
    } finally {
      setLoading(false);
    }
  }, [page, filters]);

  const loadStats = useCallback(async () => {
    try {
      setStats(await adminInventoryService.getStatistics());
    } catch {
      // Statistics are non-critical; keep the last known values.
    }
  }, []);

  useEffect(() => {
    load();
    loadStats();
  }, [load, loadStats]);

  useEffect(() => {
    adminCategoryService.list().then(setCategories).catch(() => {});
  }, []);

  const openModal = (mode, product) => setModal({ show: true, mode, product });

  const saved = async () => {
    await Promise.all([load(), loadStats()]);
  };

  const pages = pagination.last_page || 1;

  return (
    <div>
      <div className="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <h2 className="h4 mb-0">Inventory</h2>
        <Link to="/admin" className="link-success small">
          ← Back to dashboard
        </Link>
      </div>

      <Row className="g-3 mb-4">
        <Col xs={6} lg={3}>
          <StatCard
            label="Total products"
            value={stats.total_products || 0}
            icon={BoxesIcon}
            variant="primary"
            onClick={() => { setFilter('stock', ''); setFilter('sort', 'recently_updated'); }}
          />
        </Col>
        <Col xs={6} lg={3}>
          <StatCard label="In stock" value={stats.in_stock || 0} icon={BoxesIcon} variant="success" onClick={() => setFilter('stock', 'in_stock')} />
        </Col>
        <Col xs={6} lg={3}>
          <StatCard label="Low stock" value={stats.low_stock || 0} icon={AlertIcon} variant="warning" onClick={() => setFilter('stock', 'low_stock')} />
        </Col>
        <Col xs={6} lg={3}>
          <StatCard label="Out of stock" value={stats.out_of_stock || 0} icon={AlertIcon} variant="danger" onClick={() => setFilter('stock', 'out_of_stock')} />
        </Col>
      </Row>

      <div className="text-muted small mb-3">
        <span className="fw-semibold">{stats.total_units || 0}</span> units on hand across all products.
      </div>

      <Row className="g-2 mb-3">
        <Col xs={12} md={5} lg={5}>
          <InputGroup>
            <InputGroup.Text className="bg-white">
              <SearchIcon size={16} className="text-muted" />
            </InputGroup.Text>
            <Form.Control
              type="search"
              placeholder="Name, SKU or product ID..."
              value={filters.search}
              onChange={(e) => setFilter('search', e.target.value)}
            />
          </InputGroup>
        </Col>
        <Col xs={6} md={4} lg={3}>
          <Form.Select value={filters.category_id} onChange={(e) => setFilter('category_id', e.target.value)}>
            <option value="">All categories</option>
            {categories.map((c) => (
              <option key={c.id} value={c.id}>
                {c.name}
              </option>
            ))}
          </Form.Select>
        </Col>
        <Col xs={6} md={3} lg={2}>
          <Form.Select value={filters.stock} onChange={(e) => setFilter('stock', e.target.value)}>
            {STOCK_FILTERS.map((s) => (
              <option key={s.value} value={s.value}>
                {s.label}
              </option>
            ))}
          </Form.Select>
        </Col>
        <Col xs={12} md={6} lg={2}>
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
        <LoadingSpinner label="Loading inventory..." />
      ) : items.length === 0 ? (
        <EmptyState title="No products found" message="Try adjusting your search or filters." />
      ) : (
        <>
          <div className="table-responsive d-none d-md-block">
            <Table hover striped className="admin-orders-table">
              <thead>
                <tr>
                  <th>Product</th>
                  <th>Category</th>
                  <th className="text-end">Stock</th>
                  <th>Status</th>
                  <th>Last movement</th>
                  <th className="text-end">Actions</th>
                </tr>
              </thead>
              <tbody>
                {items.map((p) => (
                  <tr key={p.id}>
                    <td>
                      <div className="d-flex align-items-center gap-2">
                        {p.primary_image?.url ? (
                          <img src={p.primary_image.url} alt={p.name} width="40" height="40" className="rounded object-fit-cover" />
                        ) : (
                          <div className="table-thumb text-muted">–</div>
                        )}
                        <div>
                          <div className="fw-semibold">{p.name}</div>
                          <div className="text-muted small">SKU: {p.sku}</div>
                        </div>
                      </div>
                    </td>
                    <td>{p.category?.name || '—'}</td>
                    <td className="text-end">
                      <span className="fw-semibold">{p.stock_quantity}</span>{' '}
                      <span className="text-muted small">{p.unit || 'units'}</span>
                    </td>
                    <td><InventoryStatusBadge status={p.stock_status} /></td>
                    <td className="text-muted small">
                      {p.last_inventory_transaction_at
                        ? new Date(p.last_inventory_transaction_at).toLocaleString()
                        : 'Never'}
                    </td>
                    <td className="text-end">
                      <div className="d-inline-flex gap-1 flex-wrap justify-content-end">
                        <Button size="sm" variant="outline-success" title="Add stock" onClick={() => openModal('add', p)}>
                          <PlusIcon size={15} />
                        </Button>
                        <Button size="sm" variant="outline-warning" title="Remove stock" onClick={() => openModal('remove', p)}>
                          <MinusIcon size={15} />
                        </Button>
                        <Button size="sm" variant="outline-primary" as={Link} to={`/admin/inventory/${p.id}`}>
                          Manage
                        </Button>
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </Table>
          </div>

          <div className="d-md-none d-flex flex-column gap-2">
            {items.map((p) => (
              <Card key={p.id} className="shadow-sm">
                <Card.Body>
                  <div className="d-flex justify-content-between align-items-start gap-2 mb-2">
                    <div className="min-w-0">
                      <div className="fw-semibold text-truncate">{p.name}</div>
                      <div className="text-muted small">SKU: {p.sku}</div>
                    </div>
                    <InventoryStatusBadge status={p.stock_status} />
                  </div>
                  <div className="small mb-3 text-muted">
                    <div>{p.category?.name || '—'}</div>
                    <div>
                      Stock: <strong>{p.stock_quantity}</strong> {p.unit || 'units'}
                    </div>
                  </div>
                  <div className="d-flex gap-2 flex-wrap">
                    <Button size="sm" variant="outline-success" onClick={() => openModal('add', p)}>
                      <PlusIcon size={15} /> Add
                    </Button>
                    <Button size="sm" variant="outline-warning" onClick={() => openModal('remove', p)}>
                      <MinusIcon size={15} /> Remove
                    </Button>
                    <Button size="sm" variant="outline-primary" as={Link} to={`/admin/inventory/${p.id}`}>
                      Manage
                    </Button>
                  </div>
                </Card.Body>
              </Card>
            ))}
          </div>

          {pages > 1 && (
            <StorePagination
              pagination={pagination}
              onPageChange={setPage}
              disabled={loading}
              ariaLabel="Inventory pagination"
            />
          )}
        </>
      )}

      <StockActionModal
        show={modal.show}
        mode={modal.mode}
        product={modal.product}
        onHide={() => setModal((prev) => ({ ...prev, show: false }))}
        onSaved={saved}
      />
    </div>
  );
}