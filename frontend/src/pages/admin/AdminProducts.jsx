import { useCallback, useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { Alert, Badge, Button, Col, Form, Row, Spinner, Table } from 'react-bootstrap';
import { adminProductService } from '../../services/adminProductService';
import { adminCategoryService } from '../../services/adminCategoryService';
import { normalizeError } from '../../services/api';
import InventoryStatusBadge from '../../components/inventory/InventoryStatusBadge';
import { stockStatusFor } from '../../utils/inventory';

export default function AdminProducts() {
  const [items, setItems] = useState([]);
  const [categories, setCategories] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [search, setSearch] = useState('');
  const [category, setCategory] = useState('');

  const load = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      const data = await adminProductService.list({
        search: search || undefined,
        category_id: category || undefined,
        per_page: 100,
      });
      setItems(data.items);
    } catch (e) {
      setError(normalizeError(e).message);
    } finally {
      setLoading(false);
    }
  }, [search, category]);

  useEffect(() => {
    load();
  }, [load]);

  useEffect(() => {
    adminCategoryService.list().then(setCategories).catch(() => {});
  }, []);

  const toggleStatus = async (product) => {
    const next = product.status === 'active' ? 'inactive' : 'active';
    try {
      await adminProductService.updateStatus(product.id, next);
      await load();
    } catch (e) {
      setError(normalizeError(e).message);
    }
  };

  const toggleFeatured = async (product) => {
    try {
      await adminProductService.updateFeatured(product.id, !product.is_featured);
      await load();
    } catch (e) {
      setError(normalizeError(e).message);
    }
  };

  const remove = async (product) => {
    if (!window.confirm(`Delete "${product.name}"?`)) return;
    try {
      await adminProductService.remove(product.id);
      await load();
    } catch (e) {
      setError(normalizeError(e).message);
    }
  };

  return (
    <div>
      <div className="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <h2 className="h4 mb-0">Products</h2>
        <Button as={Link} to="/admin/products/new" variant="success">
          New Product
        </Button>
      </div>

      <Row className="g-2 mb-3">
        <Col md={4}>
          <Form.Control
            type="search"
            placeholder="Search products..."
            value={search}
            onChange={(e) => setSearch(e.target.value)}
          />
        </Col>
        <Col md={3}>
          <Form.Select value={category} onChange={(e) => setCategory(e.target.value)}>
            <option value="">All categories</option>
            {categories.map((c) => (
              <option key={c.id} value={c.id}>
                {c.name}
              </option>
            ))}
          </Form.Select>
        </Col>
      </Row>

      {error && <Alert variant="danger">{error}</Alert>}

      {loading ? (
        <div className="text-center py-5">
          <Spinner animation="border" variant="success" />
        </div>
      ) : items.length === 0 ? (
        <Alert variant="info">No products found.</Alert>
      ) : (
        <div className="table-responsive">
          <Table hover striped>
            <thead>
              <tr>
                <th>Product</th>
                <th>Category</th>
                <th>Price</th>
                <th>Stock</th>
                <th>Status</th>
                <th>Featured</th>
                <th className="text-end">Actions</th>
              </tr>
            </thead>
            <tbody>
              {items.map((p) => (
                <tr key={p.id}>
                  <td>
                    <div className="d-flex align-items-center gap-2">
                      {p.primary_image?.url ? (
                        <img src={p.primary_image.url} alt={p.name} width="40" height="40" className="rounded" />
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
                  <td>${p.price}</td>
                  <td>
                    <div className="fw-semibold">{p.stock_quantity}</div>
                    <InventoryStatusBadge status={stockStatusFor(p.stock_quantity, p.low_stock_threshold)} className="small" />
                  </td>
                  <td>
                    <Badge pill bg={p.status === 'active' ? 'success' : 'secondary'}>
                      {p.status}
                    </Badge>
                  </td>
                  <td>
                    {p.is_featured ? <Badge pill bg="warning" text="dark">Yes</Badge> : <span className="text-muted">No</span>}
                  </td>
                  <td className="text-end">
                    <div className="d-inline-flex gap-1 flex-wrap justify-content-end">
                      <Button size="sm" variant="outline-secondary" onClick={() => toggleStatus(p)}>
                        {p.status === 'active' ? 'Deactivate' : 'Activate'}
                      </Button>
                      <Button size="sm" variant="outline-warning" onClick={() => toggleFeatured(p)}>
                        {p.is_featured ? 'Unfeature' : 'Feature'}
                      </Button>
                      <Button size="sm" variant="outline-primary" as={Link} to={`/admin/products/${p.id}/edit`}>
                        Edit
                      </Button>
                      <Button size="sm" variant="outline-success" as={Link} to={`/admin/inventory/${p.id}`} title="Manage stock">
                        Stock
                      </Button>
                      <Button size="sm" variant="outline-danger" onClick={() => remove(p)}>
                        Delete
                      </Button>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </Table>
        </div>
      )}
    </div>
  );
}
