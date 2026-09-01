import { useCallback, useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { Alert, Badge, Button, Card, Col, Form, InputGroup, Pagination, Row, Spinner, Table } from 'react-bootstrap';
import { adminInventoryService } from '../../services/adminInventoryService';
import InventoryStatusBadge from '../../components/inventory/InventoryStatusBadge';
import StockActionModal from '../../components/inventory/StockActionModal';
import ErrorState from '../../components/common/ErrorState';
import LoadingSpinner from '../../components/common/LoadingSpinner';
import EmptyState from '../../components/common/EmptyState';
import usePageTitle from '../../hooks/usePageTitle';
import { useToast } from '../../context/ToastContext';
import { normalizeError } from '../../services/api';
import { formatPrice } from '../../utils/format';
import { PlusIcon, MinusIcon } from '../../assets/icons';

const TYPES = [
  { value: '', label: 'All movements' },
  { value: 'purchase', label: 'Purchase' },
  { value: 'sale', label: 'Sale' },
  { value: 'adjustment', label: 'Adjustment' },
  { value: 'return', label: 'Return / restock' },
  { value: 'initial', label: 'Initial' },
];

const TYPE_LABEL = {
  purchase: 'Purchase',
  sale: 'Sale',
  adjustment: 'Adjustment',
  return: 'Return / restock',
  initial: 'Initial',
};

const PER_PAGE = 15;

export default function AdminInventoryDetail() {
  const { id } = useParams();
  const { showToast } = useToast();
  usePageTitle('Inventory Detail');

  const [product, setProduct] = useState(null);
  const [loading, setLoading] = useState(true);
  const [loadError, setLoadError] = useState('');
  const [notFound, setNotFound] = useState(false);

  const [reorder, setReorder] = useState('');
  const [reorderSaving, setReorderSaving] = useState(false);
  const [reorderError, setReorderError] = useState('');

  const [transactions, setTransactions] = useState([]);
  const [pagination, setPagination] = useState({ current_page: 1, per_page: PER_PAGE, total: 0, last_page: 1 });
  const [page, setPage] = useState(1);
  const [type, setType] = useState('');
  const [ledgerLoading, setLedgerLoading] = useState(false);
  const [ledgerError, setLedgerError] = useState('');

  const [modal, setModal] = useState({ show: false, mode: 'add' });

  const loadProduct = useCallback(async () => {
    setLoading(true);
    setLoadError('');
    setNotFound(false);
    try {
      const data = await adminInventoryService.get(id);
      setProduct(data);
      setReorder(String(data.reorder_level ?? data.low_stock_threshold ?? ''));
    } catch (err) {
      setLoadError(normalizeError(err).message);
      if (err.status === 404) setNotFound(true);
    } finally {
      setLoading(false);
    }
  }, [id]);

  const loadLedger = useCallback(async () => {
    setLedgerLoading(true);
    setLedgerError('');
    try {
      const data = await adminInventoryService.getTransactions(id, {
        page,
        per_page: PER_PAGE,
        type: type || undefined,
      });
      setTransactions(data.items || []);
      setPagination(data.pagination || {});
    } catch (err) {
      setLedgerError(normalizeError(err).message);
    } finally {
      setLedgerLoading(false);
    }
  }, [id, page, type]);

  useEffect(() => {
    loadProduct();
  }, [loadProduct]);

  useEffect(() => {
    loadLedger();
  }, [loadLedger]);

  const saveReorder = async (e) => {
    e.preventDefault();
    const value = Number(reorder);
    if (!Number.isInteger(value) || value < 0) return;
    setReorderSaving(true);
    setReorderError('');
    try {
      const data = await adminInventoryService.updateReorderLevel(id, value);
      setProduct(data);
      showToast('Reorder level updated.');
      window.scrollTo({ top: 0, behavior: 'smooth' });
    } catch (err) {
      setReorderError(normalizeError(err).message);
    } finally {
      setReorderSaving(false);
    }
  };

  const handleAction = async (result) => {
    if (result?.product) setProduct(result.product);
    setModal((prev) => ({ ...prev, show: false }));
    await Promise.all([loadProduct(), loadLedger()]);
  };

  if (loading) {
    return <LoadingSpinner label="Loading inventory detail..." />;
  }

  if (notFound) {
    return (
      <EmptyState
        title="Product Not Found"
        message="This product does not exist or was removed from the catalog."
        actionLabel="Back to inventory"
        actionTo="/admin/inventory"
      />
    );
  }

  if (loadError) {
    return <ErrorState message={loadError} onRetry={loadProduct} />;
  }

  const deltaClass = (tx) =>
    Number(tx.quantity_change) > 0 ? 'text-success' : Number(tx.quantity_change) < 0 ? 'text-danger' : 'text-muted';
  const deltaPrefix = (tx) => (Number(tx.quantity_change) > 0 ? '+' : '');

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
    <div>
      <div className="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <h2 className="h4 mb-0">Inventory Detail</h2>
        <Link to="/admin/inventory" className="link-success small">
          ← Back to inventory
        </Link>
      </div>

      <Card className="shadow-sm mb-4">
        <Card.Body>
          <Row className="g-3 align-items-center">
            <Col sm="auto">
              {product.primary_image?.url ? (
                <img
                  src={product.primary_image.url}
                  alt={product.name}
                  width="80"
                  height="80"
                  className="rounded object-fit-cover"
                />
              ) : (
                <div className="table-thumb text-muted d-inline-flex align-items-center justify-content-center" style={{ width: 80, height: 80 }}>
                  –
                </div>
              )}
            </Col>
            <Col>
              <h3 className="h5 mb-1">{product.name}</h3>
              <div className="text-muted small mb-1">
                SKU: {product.sku} · {product.category?.name || 'Uncategorized'} · {formatPrice(product.price)}
              </div>
              <InventoryStatusBadge status={product.stock_status} />
            </Col>
            <Col xs={12} lg="auto" className="mt-3 mt-lg-0">
              <div className="d-flex flex-wrap gap-2">
                <Button variant="success" onClick={() => setModal({ show: true, mode: 'add' })}>
                  <PlusIcon size={16} /> Add stock
                </Button>
                <Button variant="warning" onClick={() => setModal({ show: true, mode: 'remove' })}>
                  <MinusIcon size={16} /> Remove
                </Button>
                <Button variant="outline-primary" onClick={() => setModal({ show: true, mode: 'adjust' })}>
                  Adjust
                </Button>
              </div>
            </Col>
          </Row>

          <div className="row row-cols-2 row-cols-md-4 mt-4 g-3">
            <div className="col">
              <div className="text-muted small">Available stock</div>
              <div className="fw-semibold fs-5">
                {product.stock_quantity} <span className="fs-6 text-muted fw-normal">{product.unit || 'units'}</span>
              </div>
            </div>
            <div className="col">
              <div className="text-muted small">Reorder level</div>
              <div className="fw-semibold fs-5">{product.reorder_level ?? product.low_stock_threshold ?? '—'}</div>
            </div>
            <div className="col">
              <div className="text-muted small">Status</div>
              <div><InventoryStatusBadge status={product.stock_status} /></div>
            </div>
            <div className="col">
              <div className="text-muted small">Last movement</div>
              <div className="fw-semibold">
                {product.last_inventory_transaction_at
                  ? new Date(product.last_inventory_transaction_at).toLocaleString()
                  : 'Never'}
              </div>
            </div>
          </div>

          <Form onSubmit={saveReorder} className="mt-4 pt-3 border-top">
            <Row className="g-2 align-items-end">
              <Col xs={12} md={3}>
                <Form.Group controlId="reorderLevelInput">
                  <Form.Label className="small text-muted mb-1">Reorder (low stock) level</Form.Label>
                  <InputGroup>
                    <Form.Control
                      type="number"
                      inputMode="numeric"
                      min={0}
                      value={reorder}
                      onChange={(e) => setReorder(e.target.value)}
                      required
                    />
                    <Button type="submit" variant="outline-success" disabled={reorderSaving}>
                      {reorderSaving ? <Spinner animation="border" size="sm" /> : 'Save'}
                    </Button>
                  </InputGroup>
                </Form.Group>
              </Col>
              <Col xs={12} md={9}>
                <div className="text-muted small mb-0 ms-md-2">
                  When stock falls to or below this level it is flagged as low stock and admins are notified.
                </div>
              </Col>
            </Row>
            {reorderError && (
              <Alert variant="danger" className="mt-3 mb-0">{reorderError}</Alert>
            )}
          </Form>
        </Card.Body>
      </Card>

      <div className="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
        <h3 className="h6 mb-0">Movement history</h3>
        <Form.Select
          value={type}
          onChange={(e) => {
            setType(e.target.value);
            setPage(1);
          }}
          className="w-auto"
          aria-label="Filter by transaction type"
        >
          {TYPES.map((t) => (
            <option key={t.value} value={t.value}>
              {t.label}
            </option>
          ))}
        </Form.Select>
      </div>

      {ledgerError && <Alert variant="danger">{ledgerError}</Alert>}

      {ledgerLoading ? (
        <LoadingSpinner label="Loading movements..." />
      ) : transactions.length === 0 ? (
        <EmptyState title="No movements recorded" message="Stock changes will appear here as they happen." />
      ) : (
        <>
          <div className="table-responsive admin-orders-table">
            <Table hover striped>
              <thead>
                <tr>
                  <th>Type</th>
                  <th className="text-end">Change</th>
                  <th>Before → After</th>
                  <th>By</th>
                  <th>Reference</th>
                  <th>Notes</th>
                  <th>When</th>
                </tr>
              </thead>
              <tbody>
                {transactions.map((tx) => (
                  <tr key={tx.id}>
                    <td><Badge pill bg="light" text="dark">{TYPE_LABEL[tx.type] || tx.type}</Badge></td>
                    <td className={`text-end fw-semibold ${deltaClass(tx)}`}>
                      {deltaPrefix(tx)}{tx.quantity_change}
                    </td>
                    <td>{tx.stock_before} → {tx.stock_after}</td>
                    <td>{tx.actor?.name || (<span className="text-muted">System</span>)}</td>
                    <td className="small">
                      {tx.reference_number ? <Badge bg="outline-secondary" text="dark">{tx.reference_number}</Badge> : <span className="text-muted">—</span>}
                    </td>
                    <td className="text-muted small">{tx.notes || '—'}</td>
                    <td className="text-muted small">{new Date(tx.created_at).toLocaleString()}</td>
                  </tr>
                ))}
              </tbody>
            </Table>
          </div>

          {pages > 1 && (
            <Pagination className="justify-content-center mt-4">{pageItems}</Pagination>
          )}
        </>
      )}

      <StockActionModal
        show={modal.show}
        mode={modal.mode}
        product={product}
        onHide={() => setModal((prev) => ({ ...prev, show: false }))}
        onSaved={handleAction}
      />
    </div>
  );
}