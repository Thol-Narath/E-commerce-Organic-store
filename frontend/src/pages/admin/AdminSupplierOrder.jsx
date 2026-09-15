import { useCallback, useEffect, useMemo, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import {
  Alert, Button, Card, Col, Form, InputGroup, Row, Spinner, Tab, Tabs,
} from 'react-bootstrap';
import { adminSupplierService } from '../../services/adminSupplierService';
import { adminInventoryService } from '../../services/adminInventoryService';
import { normalizeError } from '../../services/api';
import InventoryStatusBadge from '../../components/inventory/InventoryStatusBadge';
import LoadingSpinner from '../../components/common/LoadingSpinner';
import EmptyState from '../../components/common/EmptyState';
import usePageTitle from '../../hooks/usePageTitle';
import { formatPrice } from '../../utils/format';
import { MinusIcon, PlusIcon, SearchIcon, TrashIcon } from '../../assets/icons';

/**
 * Build a supplier purchase order to restock any product in the store
 * (especially items that are low or out of stock). The admin picks a supplier,
 * searches the product catalog, adds line items with quantity + unit cost, and
 * creates a draft purchase order. Products are not permanently linked to a
 * supplier — any product can be ordered from any supplier.
 */
export default function AdminSupplierOrder() {
  usePageTitle('Order Stock from Supplier');

  const { supplierId } = useParams();
  const navigate = useNavigate();

  const [suppliers, setSuppliers] = useState([]);
  const [selectedSupplierId, setSelectedSupplierId] = useState(supplierId || '');
  const [products, setProducts] = useState([]);
  const [productsLoading, setProductsLoading] = useState(false);
  const [productSearch, setProductSearch] = useState('');
  const [lines, setLines] = useState([]);
  const [shippingFee, setShippingFee] = useState(0);
  const [expectedDelivery, setExpectedDelivery] = useState('');
  const [notes, setNotes] = useState('');
  const [tab, setTab] = useState('catalog');
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    adminSupplierService.options()
      .then((d) => {
        setSuppliers(d || []);
        if (supplierId) setSelectedSupplierId(String(supplierId));
      })
      .catch((e) => setError(normalizeError(e).message))
      .finally(() => setLoading(false));
  }, [supplierId]);

  // Any product can be ordered from any supplier, so we browse the full
  // inventory catalog (with live stock levels) rather than supplier-linked
  // products.
  const loadProducts = useCallback(async (search) => {
    setProductsLoading(true);
    setError('');
    try {
      const data = await adminInventoryService.list({
        per_page: 200,
        search: search || undefined,
      });
      setProducts(data?.items ?? []);
    } catch (e) {
      setError(normalizeError(e).message);
    } finally {
      setProductsLoading(false);
    }
  }, []);

  useEffect(() => {
    loadProducts('');
  }, [loadProducts]);

  const applySearch = (e) => {
    e.preventDefault();
    loadProducts(productSearch.trim());
  };

  const selectedSupplier = useMemo(
    () => suppliers.find((s) => String(s.id) === String(selectedSupplierId)),
    [suppliers, selectedSupplierId],
  );

  const addLine = (product) => {
    setLines((prev) => {
      const existing = prev.find((l) => l.product_id === product.id);
      if (existing) {
        return prev.map((l) => (l.product_id === product.id ? { ...l, quantity: l.quantity + 1 } : l));
      }
      return [...prev, {
        product_id: product.id,
        name: product.name,
        sku: product.sku,
        stock_quantity: product.stock_quantity,
        unit: product.unit,
        quantity: 1,
        unit_cost: Number(product.cost_price) > 0 ? Number(product.cost_price) : 0,
      }];
    });
  };

  const updateLine = (productId, patch) => {
    setLines((prev) => prev.map((l) => (l.product_id === productId ? { ...l, ...patch } : l)));
  };

  const removeLine = (productId) => {
    setLines((prev) => prev.filter((l) => l.product_id !== productId));
  };

  const subtotal = useMemo(
    () => lines.reduce((sum, l) => sum + (Number(l.unit_cost) || 0) * (Number(l.quantity) || 0), 0),
    [lines],
  );
  const total = subtotal + (Number(shippingFee) || 0);

  const handleSubmit = async (e) => {
    e.preventDefault();
    if (!selectedSupplierId) {
      setError('Please choose a supplier first.');
      return;
    }
    if (lines.length === 0) {
      setError('Add at least one product to the order.');
      return;
    }

    setSaving(true);
    setError('');
    try {
      const order = await adminSupplierService.createOrder({
        supplier_id: Number(selectedSupplierId),
        items: lines.map((l) => ({
          product_id: l.product_id,
          quantity: Number(l.quantity),
          unit_cost: Number(l.unit_cost),
        })),
        shipping_fee: Number(shippingFee) || 0,
        expected_delivery_date: expectedDelivery || undefined,
        notes: notes || undefined,
      });
      navigate(`/admin/supplier-orders/${order.id}`);
    } catch (err) {
      setError(normalizeError(err).message);
    } finally {
      setSaving(false);
    }
  };

  const lowStockProducts = useMemo(
    () => products.filter((p) => p.stock_status !== 'in_stock'),
    [products],
  );

  const renderProductTable = (list) => (
    <div className="table-responsive">
      <table className="table table-hover align-middle mb-0">
        <thead>
          <tr>
            <th>Product</th>
            <th className="text-end">Stock</th>
            <th>Status</th>
            <th className="text-end"></th>
          </tr>
        </thead>
        <tbody>
          {list.map((p) => (
            <tr key={p.id} className={p.stock_status === 'out_of_stock' ? 'table-danger' : p.stock_status === 'low_stock' ? 'table-warning' : ''}>
              <td>
                <div className="d-flex align-items-center gap-2">
                  {p.primary_image?.url ? (
                    <img src={p.primary_image.url} alt={p.name} width="36" height="36" className="rounded object-fit-cover" />
                  ) : (
                    <div className="table-thumb text-muted">–</div>
                  )}
                  <div>
                    <div className="fw-medium">{p.name}</div>
                    <div className="text-muted small">SKU: {p.sku}</div>
                  </div>
                </div>
              </td>
              <td className="text-end fw-semibold">{p.stock_quantity} {p.unit || 'units'}</td>
              <td><InventoryStatusBadge status={p.stock_status} /></td>
              <td className="text-end">
                <Button size="sm" variant="outline-success" onClick={() => addLine(p)}>
                  <PlusIcon size={15} /> Add
                </Button>
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );

  return (
    <div>
      <div className="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <div>
          <h2 className="h4 mb-0">Order Stock from Supplier</h2>
          <div className="text-muted small">
            Create a purchase order to restock products when inventory runs low.
          </div>
        </div>
        <Link to="/admin/suppliers" className="link-success small">
          ← Back to suppliers
        </Link>
      </div>

      {error && <Alert variant="danger">{error}</Alert>}

      {loading ? (
        <LoadingSpinner label="Loading suppliers..." />
      ) : (
        <Row className="g-4">
          <Col lg={8}>
            <Card className="shadow-sm">
              <Card.Header className="bg-white fw-semibold">
                1 · Choose supplier
              </Card.Header>
              <Card.Body>
                <Form.Select
                  value={selectedSupplierId}
                  onChange={(e) => setSelectedSupplierId(e.target.value)}
                  disabled={suppliers.length === 0}
                >
                  <option value="">Select a supplier...</option>
                  {suppliers.map((s) => (
                    <option key={s.id} value={s.id}>{s.name}</option>
                  ))}
                </Form.Select>
                {selectedSupplier && (
                  <div className="mt-2 text-muted small">
                    {selectedSupplier.phone && <span className="me-3">{selectedSupplier.phone}</span>}
                    {selectedSupplier.email && <span>{selectedSupplier.email}</span>}
                  </div>
                )}
                {suppliers.length === 0 && (
                  <Alert variant="info" className="mt-3 mb-0">
                    No suppliers yet.{' '}
                    <Link to="/admin/suppliers">Add a supplier</Link> first.
                  </Alert>
                )}
              </Card.Body>
            </Card>

            <Card className="shadow-sm mt-4">
              <Card.Header className="bg-white fw-semibold">
                2 · Add products
              </Card.Header>
              <Card.Body>
                <Form onSubmit={applySearch} className="mb-3">
                  <InputGroup>
                    <InputGroup.Text className="bg-white">
                      <SearchIcon size={16} className="text-muted" />
                    </InputGroup.Text>
                    <Form.Control
                      type="search"
                      placeholder="Search products by name, SKU or ID..."
                      value={productSearch}
                      onChange={(e) => setProductSearch(e.target.value)}
                    />
                    <Button type="submit" variant="outline-success">Search</Button>
                  </InputGroup>
                </Form>

                {productsLoading ? (
                  <LoadingSpinner label="Loading products..." />
                ) : (
                  <Tabs activeKey={tab} onSelect={setTab} className="mb-3">
                    <Tab eventKey="catalog" title="All products">
                      {products.length === 0 ? (
                        <EmptyState
                          title="No products found"
                          message="Try a different search term."
                        />
                      ) : (
                        renderProductTable(products)
                      )}
                    </Tab>
                    <Tab eventKey="low" title={`Low / out of stock (${lowStockProducts.length})`}>
                      {lowStockProducts.length === 0 ? (
                        <Alert variant="success" className="mb-0">
                          No low or out-of-stock products right now.
                        </Alert>
                      ) : (
                        renderProductTable(lowStockProducts)
                      )}
                    </Tab>
                  </Tabs>
                )}
              </Card.Body>
            </Card>
          </Col>

          <Col lg={4}>
            <Card className="shadow-sm">
              <Card.Header className="bg-white fw-semibold d-flex justify-content-between align-items-center">
                <span>3 · Order details</span>
                {lines.length > 0 && (
                  <span className="badge text-bg-success">{lines.length} item{lines.length > 1 ? 's' : ''}</span>
                )}
              </Card.Header>
              <Card.Body>
                {lines.length === 0 ? (
                  <p className="text-muted small mb-0">
                    No products added yet. Add products from the catalog to build the order.
                  </p>
                ) : (
                  <>
                    <div className="d-flex flex-column gap-2 mb-3" style={{ maxHeight: 320, overflowY: 'auto' }}>
                      {lines.map((l) => (
                        <div key={l.product_id} className="border rounded p-2">
                          <div className="d-flex justify-content-between align-items-start gap-2">
                            <div className="min-w-0">
                              <div className="small fw-semibold text-truncate">{l.name}</div>
                              <div className="text-muted small">Stock: {l.stock_quantity} {l.unit || 'units'}</div>
                            </div>
                            <Button size="sm" variant="link" className="p-0 text-danger" onClick={() => removeLine(l.product_id)} title="Remove">
                              <TrashIcon size={15} />
                            </Button>
                          </div>
                          <div className="d-flex gap-2 mt-2 align-items-center">
                            <div className="d-inline-flex align-items-center border rounded">
                              <Button size="sm" variant="light" className="border-0" onClick={() => updateLine(l.product_id, { quantity: Math.max(1, (Number(l.quantity) || 1) - 1) })}>
                                <MinusIcon size={14} />
                              </Button>
                              <span className="px-2 small">{l.quantity}</span>
                              <Button size="sm" variant="light" className="border-0" onClick={() => updateLine(l.product_id, { quantity: (Number(l.quantity) || 1) + 1 })}>
                                <PlusIcon size={14} />
                              </Button>
                            </div>
                            <div className="d-flex align-items-center gap-1 ms-auto">
                              <span className="text-muted small">$</span>
                              <Form.Control
                                type="number"
                                min="0"
                                step="0.01"
                                size="sm"
                                style={{ width: 90 }}
                                value={l.unit_cost}
                                onChange={(e) => updateLine(l.product_id, { unit_cost: e.target.value })}
                              />
                            </div>
                          </div>
                          <div className="text-end small fw-medium mt-1">
                            {formatPrice((Number(l.unit_cost) || 0) * (Number(l.quantity) || 0))}
                          </div>
                        </div>
                      ))}
                    </div>

                    <Form.Group className="mb-3">
                      <Form.Label className="small">Shipping fee</Form.Label>
                      <Form.Control
                        type="number"
                        min="0"
                        step="0.01"
                        value={shippingFee}
                        onChange={(e) => setShippingFee(e.target.value)}
                      />
                    </Form.Group>

                    <Form.Group className="mb-3">
                      <Form.Label className="small">Expected delivery date</Form.Label>
                      <Form.Control
                        type="date"
                        value={expectedDelivery}
                        onChange={(e) => setExpectedDelivery(e.target.value)}
                      />
                    </Form.Group>

                    <Form.Group className="mb-3">
                      <Form.Label className="small">Notes</Form.Label>
                      <Form.Control
                        as="textarea"
                        rows={2}
                        value={notes}
                        onChange={(e) => setNotes(e.target.value)}
                        placeholder="Optional notes for this purchase order"
                      />
                    </Form.Group>

                    <div className="d-flex justify-content-between border-top pt-2 mb-3">
                      <span className="text-muted">Subtotal</span>
                      <span className="fw-semibold">{formatPrice(subtotal)}</span>
                    </div>
                    <div className="d-flex justify-content-between mb-3">
                      <span className="text-muted">Shipping</span>
                      <span className="fw-semibold">{formatPrice(Number(shippingFee) || 0)}</span>
                    </div>
                    <div className="d-flex justify-content-between border-top pt-2 mb-3">
                      <span className="fw-semibold">Total</span>
                      <span className="fw-bold fs-6">{formatPrice(total)}</span>
                    </div>

                    <Button
                      variant="success"
                      className="w-100"
                      onClick={handleSubmit}
                      disabled={saving || !selectedSupplierId || lines.length === 0}
                    >
                      {saving ? (
                        <>
                          <Spinner as="span" animation="border" size="sm" className="me-2" />
                          Creating order...
                        </>
                      ) : (
                        'Create purchase order'
                      )}
                    </Button>
                  </>
                )}
              </Card.Body>
            </Card>
          </Col>
        </Row>
      )}
    </div>
  );
}