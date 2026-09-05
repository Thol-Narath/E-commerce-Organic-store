import { useCallback, useEffect, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { Alert, Badge, Button, Col, Form, Row, Spinner } from 'react-bootstrap';
import { adminProductService } from '../../services/adminProductService';
import { adminCategoryService } from '../../services/adminCategoryService';
import { normalizeError } from '../../services/api';

const EMPTY = {
  category_id: '',
  name: '',
  sku: '',
  barcode: '',
  description: '',
  short_description: '',
  price: '',
  compare_at_price: '',
  cost_price: '',
  stock_quantity: '',
  low_stock_threshold: '',
  is_featured: false,
  status: 'active',
  unit: '',
  weight: '',
  min_order_qty: 1,
};

export default function AdminProductForm() {
  const { id } = useParams();
  const isEdit = Boolean(id);
  const navigate = useNavigate();

  const [categories, setCategories] = useState([]);
  const [form, setForm] = useState(EMPTY);
  const [primaryImage, setPrimaryImage] = useState(null);
  const [existingImages, setExistingImages] = useState([]);
  const [loading, setLoading] = useState(isEdit);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');
  const [fieldErrors, setFieldErrors] = useState({});

  const setField = (field, value) => {
    setForm((f) => ({ ...f, [field]: value }));
  };

  const loadProduct = useCallback(async () => {
    try {
      const [product, cats] = await Promise.all([
        adminProductService.get(id),
        adminCategoryService.list(),
      ]);
      setCategories(cats);
      setForm({
        category_id: product.category_id ?? '',
        name: product.name ?? '',
        sku: product.sku ?? '',
        barcode: product.barcode ?? '',
        description: product.description ?? '',
        short_description: product.short_description ?? '',
        price: product.price ?? '',
        compare_at_price: product.compare_at_price ?? '',
        cost_price: product.cost_price ?? '',
        stock_quantity: product.stock_quantity ?? '',
        low_stock_threshold: product.low_stock_threshold ?? '',
        is_featured: Boolean(product.is_featured),
        status: product.status ?? 'active',
        unit: product.unit ?? '',
        weight: product.weight ?? '',
        min_order_qty: product.min_order_qty ?? 1,
      });
      setExistingImages(product.images || []);
    } catch (e) {
      setError(normalizeError(e).message);
    } finally {
      setLoading(false);
    }
  }, [id]);

  useEffect(() => {
    if (isEdit) {
      loadProduct();
    } else {
      adminCategoryService.list().then(setCategories).catch(() => {});
    }
  }, [isEdit, loadProduct]);

  const handleSubmit = async (e) => {
    e.preventDefault();
    setSaving(true);
    setError('');
    setFieldErrors({});

    const payload = {
      category_id: Number(form.category_id) || undefined,
      name: form.name,
      sku: form.sku,
      barcode: form.barcode || undefined,
      description: form.description || undefined,
      short_description: form.short_description || undefined,
      price: form.price,
      compare_at_price: form.compare_at_price || undefined,
      cost_price: form.cost_price || undefined,
      stock_quantity: form.stock_quantity,
      low_stock_threshold: form.low_stock_threshold || 0,
      is_featured: Boolean(form.is_featured),
      status: form.status,
      unit: form.unit || undefined,
      weight: form.weight || undefined,
      min_order_qty: Number(form.min_order_qty) || 1,
    };

    try {
      let product;
      if (isEdit) {
        product = await adminProductService.update(id, payload);
      } else {
        const data = new FormData();
        Object.entries(payload).forEach(([key, value]) => {
          if (value === undefined || value === null) return;
          // FormData serializes all values to strings, so send booleans as
          // 1/0 to satisfy Laravel's boolean validation rule reliably.
          data.append(key, typeof value === 'boolean' ? (value ? '1' : '0') : value);
        });
        if (primaryImage) data.append('image', primaryImage);
        const res = await adminProductService.create(data);
        product = res;
      }
      navigate('/admin/products');
    } catch (err) {
      const normalized = normalizeError(err);
      setError(normalized.message);
      if (normalized.errors) setFieldErrors(normalized.errors);
    } finally {
      setSaving(false);
    }
  };

  const uploadImage = async (file) => {
    try {
      await adminProductService.uploadImage(id, file);
      await loadProduct();
    } catch (err) {
      setError(normalizeError(err).message);
    }
  };

  const setPrimary = async (imageId) => {
    try {
      await adminProductService.setPrimaryImage(id, imageId);
      await loadProduct();
    } catch (err) {
      setError(normalizeError(err).message);
    }
  };

  const removeImage = async (imageId) => {
    if (!window.confirm('Delete this image?')) return;
    try {
      await adminProductService.deleteImage(id, imageId);
      await loadProduct();
    } catch (err) {
      setError(normalizeError(err).message);
    }
  };

  if (loading) {
    return (
      <div className="text-center py-5">
        <Spinner animation="border" variant="success" />
      </div>
    );
  }

  return (
    <div>
      <h2 className="h4 mb-4">{isEdit ? 'Edit Product' : 'New Product'}</h2>

      {error && <Alert variant="danger">{error}</Alert>}

      <Form onSubmit={handleSubmit}>
        <Form.Group className="mb-3" controlId="product-name">
          <Form.Label>Name *</Form.Label>
          <Form.Control
            type="text"
            value={form.name}
            onChange={(e) => setField('name', e.target.value)}
            isInvalid={!!fieldErrors.name}
          />
          <Form.Control.Feedback type="invalid">{fieldErrors.name}</Form.Control.Feedback>
        </Form.Group>

        <Row className="mb-3">
          <Col md={6}>
            <Form.Group controlId="product-sku">
              <Form.Label>SKU *</Form.Label>
              <Form.Control
                type="text"
                value={form.sku}
                onChange={(e) => setField('sku', e.target.value)}
                isInvalid={!!fieldErrors.sku}
              />
              <Form.Control.Feedback type="invalid">{fieldErrors.sku}</Form.Control.Feedback>
            </Form.Group>
          </Col>
          <Col md={6}>
            <Form.Group controlId="product-category">
              <Form.Label>Category *</Form.Label>
              <Form.Select
                value={form.category_id}
                onChange={(e) => setField('category_id', e.target.value)}
                isInvalid={!!fieldErrors.category_id}
              >
                <option value="">Select category</option>
                {categories.map((c) => (
                  <option key={c.id} value={c.id}>
                    {c.name}
                  </option>
                ))}
              </Form.Select>
              <Form.Control.Feedback type="invalid">{fieldErrors.category_id}</Form.Control.Feedback>
            </Form.Group>
          </Col>
        </Row>

        <Row className="mb-3">
          <Col md={3}>
            <Form.Group controlId="product-price">
              <Form.Label>Price *</Form.Label>
              <Form.Control
                type="number"
                min="0"
                step="0.01"
                value={form.price}
                onChange={(e) => setField('price', e.target.value)}
                isInvalid={!!fieldErrors.price}
              />
              <Form.Control.Feedback type="invalid">{fieldErrors.price}</Form.Control.Feedback>
            </Form.Group>
          </Col>
          <Col md={3}>
            <Form.Group controlId="product-compare-price">
              <Form.Label>Compare-at price</Form.Label>
              <Form.Control
                type="number"
                min="0"
                step="0.01"
                value={form.compare_at_price}
                onChange={(e) => setField('compare_at_price', e.target.value)}
              />
            </Form.Group>
          </Col>
          <Col md={3}>
            <Form.Group controlId="product-cost">
              <Form.Label>Cost price</Form.Label>
              <Form.Control
                type="number"
                min="0"
                step="0.01"
                value={form.cost_price}
                onChange={(e) => setField('cost_price', e.target.value)}
              />
            </Form.Group>
          </Col>
          <Col md={3}>
            <Form.Group controlId="product-unit">
              <Form.Label>Unit</Form.Label>
              <Form.Select value={form.unit} onChange={(e) => setField('unit', e.target.value)}>
                <option value="">None</option>
                {['kg', 'g', 'pcs', 'bunch', 'pack', 'litre'].map((u) => (
                  <option key={u} value={u}>
                    {u}
                  </option>
                ))}
              </Form.Select>
            </Form.Group>
          </Col>
        </Row>

        <Row className="mb-3">
          <Col md={4}>
            <Form.Group controlId="product-stock">
              <Form.Label>Stock quantity *</Form.Label>
              <Form.Control
                type="number"
                min="0"
                value={form.stock_quantity}
                onChange={(e) => setField('stock_quantity', e.target.value)}
                isInvalid={!!fieldErrors.stock_quantity}
              />
              <Form.Control.Feedback type="invalid">{fieldErrors.stock_quantity}</Form.Control.Feedback>
            </Form.Group>
          </Col>
          <Col md={4}>
            <Form.Group controlId="product-lowstock">
              <Form.Label>Low stock threshold</Form.Label>
              <Form.Control
                type="number"
                min="0"
                value={form.low_stock_threshold}
                onChange={(e) => setField('low_stock_threshold', e.target.value)}
              />
            </Form.Group>
          </Col>
          <Col md={4}>
            <Form.Group controlId="product-minqty">
              <Form.Label>Min order qty</Form.Label>
              <Form.Control
                type="number"
                min="1"
                value={form.min_order_qty}
                onChange={(e) => setField('min_order_qty', e.target.value)}
              />
            </Form.Group>
          </Col>
        </Row>

        <Row className="mb-3">
          <Col md={4}>
            <Form.Group controlId="product-barcode">
              <Form.Label>Barcode</Form.Label>
              <Form.Control
                type="text"
                value={form.barcode}
                onChange={(e) => setField('barcode', e.target.value)}
              />
            </Form.Group>
          </Col>
          <Col md={4}>
            <Form.Group controlId="product-weight">
              <Form.Label>Weight</Form.Label>
              <Form.Control
                type="number"
                min="0"
                step="0.001"
                value={form.weight}
                onChange={(e) => setField('weight', e.target.value)}
              />
            </Form.Group>
          </Col>
          <Col md={4}>
            <Form.Group controlId="product-status">
              <Form.Label>Status *</Form.Label>
              <Form.Select value={form.status} onChange={(e) => setField('status', e.target.value)}>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
                <option value="draft">Draft</option>
              </Form.Select>
            </Form.Group>
          </Col>
        </Row>

        <Form.Group className="mb-3" controlId="product-short">
          <Form.Label>Short description</Form.Label>
          <Form.Control
            as="textarea"
            rows={2}
            value={form.short_description}
            onChange={(e) => setField('short_description', e.target.value)}
            isInvalid={!!fieldErrors.short_description}
          />
          <Form.Control.Feedback type="invalid">{fieldErrors.short_description}</Form.Control.Feedback>
        </Form.Group>

        <Form.Group className="mb-3" controlId="product-description">
          <Form.Label>Description</Form.Label>
          <Form.Control
            as="textarea"
            rows={4}
            value={form.description}
            onChange={(e) => setField('description', e.target.value)}
          />
        </Form.Group>

        <Form.Check
          type="switch"
          id="product-featured"
          label="Featured"
          checked={form.is_featured}
          onChange={(e) => setField('is_featured', e.target.checked)}
          className="mb-4"
        />

        <div className="mb-4">
          <h3 className="h6">Primary image</h3>
          {!isEdit ? (
            <Form.Control
              type="file"
              accept="image/*"
              onChange={(e) => setPrimaryImage(e.target.files[0] || null)}
            />
          ) : (
            <div className="d-flex flex-wrap align-items-start gap-3">
              {existingImages.length === 0 && <p className="text-muted">No images yet.</p>}
              {existingImages.map((img) => (
                <div key={img.id} className="text-center">
                  <img
                    src={img.url}
                    alt={img.alt_text || 'image'}
                    width="100"
                    height="100"
                    className="rounded border mb-1"
                  />
                  <div className="small">
                    {img.is_primary ? (
                      <Badge bg="success">Primary</Badge>
                    ) : (
                      <Button size="sm" variant="outline-secondary" onClick={() => setPrimary(img.id)}>
                        Set primary
                      </Button>
                    )}
                  </div>
                  <Button size="sm" variant="link" className="text-danger p-0" onClick={() => removeImage(img.id)}>
                    Delete
                  </Button>
                </div>
              ))}
              <Form.Group>
                <Form.Label className="d-block">Add image</Form.Label>
                <Form.Control
                  type="file"
                  accept="image/*"
                  onChange={(e) => e.target.files[0] && uploadImage(e.target.files[0])}
                />
              </Form.Group>
            </div>
          )}
        </div>

        <div className="d-flex gap-2">
          <Button type="submit" variant="success" disabled={saving}>
            {saving ? 'Saving...' : isEdit ? 'Update Product' : 'Create Product'}
          </Button>
          <Button as={Link} to="/admin/products" variant="outline-secondary">
            Cancel
          </Button>
        </div>
      </Form>
    </div>
  );
}
