import { useCallback, useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { Alert, Badge, Button, Form, Table } from 'react-bootstrap';
import { adminSupplierService } from '../../services/adminSupplierService';
import { normalizeError } from '../../services/api';
import StorePagination from '../../components/common/StorePagination';
import LoadingSpinner from '../../components/common/LoadingSpinner';
import EmptyState from '../../components/common/EmptyState';
import usePageTitle from '../../hooks/usePageTitle';
import { formatPrice, formatDate } from '../../utils/format';
import { PlusIcon } from '../../assets/icons';

const STATUS_BADGES = {
  draft: { label: 'Draft', bg: 'secondary' },
  ordered: { label: 'Ordered', bg: 'info' },
  received: { label: 'Received', bg: 'success' },
  cancelled: { label: 'Cancelled', bg: 'danger' },
};

const PER_PAGE = 15;

export default function AdminSupplierOrders() {
  usePageTitle('Purchase Orders');

  const [items, setItems] = useState([]);
  const [pagination, setPagination] = useState({ current_page: 1, per_page: PER_PAGE, total: 0, last_page: 1 });
  const [suppliers, setSuppliers] = useState([]);
  const [page, setPage] = useState(1);
  const [status, setStatus] = useState('');
  const [supplierFilter, setSupplierFilter] = useState('');
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  const load = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      const { items: list, pagination: meta } = await adminSupplierService.listOrders({
        page,
        per_page: PER_PAGE,
        status: status || undefined,
        supplier_id: supplierFilter || undefined,
      });
      setItems(list || []);
      setPagination(meta || {});
    } catch (err) {
      setError(normalizeError(err).message);
    } finally {
      setLoading(false);
    }
  }, [page, status, supplierFilter]);

  useEffect(() => { load(); }, [load]);

  useEffect(() => {
    adminSupplierService.options().then((d) => setSuppliers(d || [])).catch(() => {});
  }, []);

  return (
    <div>
      <div className="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <div>
          <h2 className="h4 mb-0">Purchase Orders</h2>
          <div className="text-muted small">
            Supplier orders for restocking inventory. Mark orders as received to add stock.
          </div>
        </div>
        <div className="d-flex gap-2">
          <Link to="/admin/suppliers" className="btn btn-outline-primary">Suppliers</Link>
          <Link to="/admin/suppliers/order" className="btn btn-success">
            <PlusIcon size={16} /> New Purchase Order
          </Link>
        </div>
      </div>

      {error && <Alert variant="danger">{error}</Alert>}

      <div className="d-flex flex-wrap gap-2 mb-3">
        <Form.Select
          style={{ maxWidth: 220 }}
          value={supplierFilter}
          onChange={(e) => { setSupplierFilter(e.target.value); setPage(1); }}
        >
          <option value="">All suppliers</option>
          {suppliers.map((s) => (
            <option key={s.id} value={s.id}>{s.name}</option>
          ))}
        </Form.Select>
        <Form.Select
          style={{ maxWidth: 180 }}
          value={status}
          onChange={(e) => { setStatus(e.target.value); setPage(1); }}
        >
          <option value="">All statuses</option>
          <option value="draft">Draft</option>
          <option value="ordered">Ordered</option>
          <option value="received">Received</option>
          <option value="cancelled">Cancelled</option>
        </Form.Select>
      </div>

      {loading ? (
        <LoadingSpinner label="Loading purchase orders..." />
      ) : items.length === 0 ? (
        <EmptyState
          title="No purchase orders found"
          message="Create a purchase order to restock products from your suppliers."
          action={<Link to="/admin/suppliers/order" className="btn btn-success btn-sm">New Purchase Order</Link>}
        />
      ) : (
        <>
          <div className="table-responsive">
            <Table hover striped className="align-middle">
              <thead>
                <tr>
                  <th>Order #</th>
                  <th>Supplier</th>
                  <th>Status</th>
                  <th className="text-end">Items</th>
                  <th className="text-end">Total</th>
                  <th>Expected delivery</th>
                  <th>Placed</th>
                  <th className="text-end">Actions</th>
                </tr>
              </thead>
              <tbody>
                {items.map((o) => {
                  const badge = STATUS_BADGES[o.status] || STATUS_BADGES.draft;
                  return (
                    <tr key={o.id}>
                      <td className="fw-semibold">{o.order_number}</td>
                      <td>{o.supplier?.name || '—'}</td>
                      <td><Badge bg={badge.bg}>{badge.label}</Badge></td>
                      <td className="text-end">{o.items?.length ?? 0}</td>
                      <td className="text-end fw-semibold">{formatPrice(o.total)}</td>
                      <td className="text-muted small">{o.expected_delivery_date ? formatDate(o.expected_delivery_date) : '—'}</td>
                      <td className="text-muted small">{o.ordered_at ? new Date(o.ordered_at).toLocaleDateString() : '—'}</td>
                      <td className="text-end">
                        <Button size="sm" variant="outline-primary" as={Link} to={`/admin/supplier-orders/${o.id}`}>
                          View
                        </Button>
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </Table>
          </div>

          {pagination.last_page > 1 && (
            <StorePagination
              pagination={pagination}
              onPageChange={setPage}
              disabled={loading}
              ariaLabel="Purchase orders pagination"
            />
          )}
        </>
      )}
    </div>
  );
}