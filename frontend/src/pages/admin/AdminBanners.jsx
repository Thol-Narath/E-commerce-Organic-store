import { useCallback, useEffect, useRef, useState } from 'react';
import { Alert, Badge, Button, Carousel, Form, Modal, Spinner, Table } from 'react-bootstrap';
import { adminBannerService } from '../../services/adminBannerService';
import { normalizeError } from '../../services/api';
import { ChevronUpIcon, ChevronDownIcon } from '../../assets/icons';
import ImageWithFallback from '../../components/common/ImageWithFallback';

const EMPTY = {
  title: '',
  subtitle: '',
  discount_label: '',
  bg_color: '#f97316',
  cta_text: 'Shop Now',
  cta_link: '/shop',
  is_active: true,
  sort_order: 0,
};

export default function AdminBanners() {
  const [banners, setBanners] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  const [showModal, setShowModal] = useState(false);
  const [editing, setEditing] = useState(null);
  const [form, setForm] = useState(EMPTY);
  const [imageFile, setImageFile] = useState(null);
  const [imagePreview, setImagePreview] = useState(null);
  const [saving, setSaving] = useState(false);
  const [fieldErrors, setFieldErrors] = useState({});
  const fileInputRef = useRef(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      setBanners(await adminBannerService.list());
    } catch (e) {
      setError(normalizeError(e).message);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { load(); }, [load]);

  const openCreate = () => {
    setEditing(null);
    setForm(EMPTY);
    setImageFile(null);
    setImagePreview(null);
    setFieldErrors({});
    setShowModal(true);
  };

  const openEdit = (b) => {
    setEditing(b);
    setForm({
      title: b.title || '',
      subtitle: b.subtitle || '',
      discount_label: b.discount_label || '',
      bg_color: b.bg_color || '#f97316',
      cta_text: b.cta_text || 'Shop Now',
      cta_link: b.cta_link || '/shop',
      is_active: b.is_active,
      sort_order: b.sort_order || 0,
    });
    setImageFile(null);
    setImagePreview(b.image_url || null);
    setFieldErrors({});
    setShowModal(true);
  };

  const handleImageChange = (e) => {
    const file = e.target.files?.[0] || null;
    setImageFile(file);
    if (file) {
      setImagePreview(URL.createObjectURL(file));
    } else {
      setImagePreview(editing?.image_url || null);
    }
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setSaving(true);
    setError('');
    setFieldErrors({});

    const payload = {
      title: form.title,
      subtitle: form.subtitle || undefined,
      discount_label: form.discount_label || undefined,
      bg_color: form.bg_color || undefined,
      cta_text: form.cta_text || undefined,
      cta_link: form.cta_link || undefined,
      is_active: form.is_active,
      sort_order: Number(form.sort_order) || 0,
    };

    try {
      if (editing) {
        await adminBannerService.update(editing.id, payload, imageFile);
      } else {
        await adminBannerService.create(payload, imageFile);
      }
      setShowModal(false);
      await load();
    } catch (err) {
      const normalized = normalizeError(err);
      setError(normalized.message);
      if (normalized.errors) setFieldErrors(normalized.errors);
    } finally {
      setSaving(false);
    }
  };

  const remove = async (b) => {
    if (!window.confirm(`Delete banner "${b.title}"?`)) return;
    try {
      await adminBannerService.remove(b.id);
      await load();
    } catch (e) {
      setError(normalizeError(e).message);
    }
  };

  const handleToggle = async (b) => {
    try {
      await adminBannerService.toggle(b.id);
      await load();
    } catch (e) {
      setError(normalizeError(e).message);
    }
  };

  const move = async (index, direction) => {
    const copy = [...banners];
    const swapIndex = index + direction;
    if (swapIndex < 0 || swapIndex >= copy.length) return;

    [copy[index], copy[swapIndex]] = [copy[swapIndex], copy[index]];
    const orders = copy.map((b, i) => ({ id: b.id, sort_order: i }));

    setBanners(copy);
    try {
      await adminBannerService.reorder(orders);
    } catch (e) {
      setError(normalizeError(e).message);
      await load();
    }
  };

  const bannersPreview = banners.filter((b) => b.is_active);

  return (
    <div>
      <div className="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <h2 className="h4 mb-0">Banners</h2>
        <Button variant="success" onClick={openCreate}>Add Banner</Button>
      </div>

      {error && <Alert variant="danger">{error}</Alert>}

      {loading ? (
        <div className="text-center py-5">
          <Spinner animation="border" variant="success" />
        </div>
      ) : banners.length === 0 ? (
        <Alert variant="info">No banners found. Create your first banner slide.</Alert>
      ) : (
        <>
          {bannersPreview.length > 0 && (
            <Carousel className="admin-banner-preview mb-4" interval={4000} controls indicators pause={false}>
              {bannersPreview.map((b) => (
                <Carousel.Item key={b.id}>
                  <div
                    className="admin-banner-preview-slide"
                    style={{ backgroundColor: b.bg_color || '#f97316' }}
                  >
                    {b.image_url && (
                      <div className="admin-banner-preview-bg">
                        <img src={b.image_url} alt={b.title} />
                      </div>
                    )}
                    <div className="admin-banner-preview-overlay" />
                    <div className="admin-banner-preview-content">
                      {(b.discount_label || b.discount_percent) && (
                        <Badge bg="light" text="dark" pill className="mb-2">
                          {b.discount_label || `${b.discount_percent}% OFF`}
                        </Badge>
                      )}
                      <h5 className="mb-1 fw-bold">{b.title}</h5>
                      {b.subtitle && (
                        <div className="small opacity-90 mb-1">{b.subtitle}</div>
                      )}
                      <span className="btn btn-sm btn-light mt-2 fw-semibold">
                        {b.cta_text || 'Shop Now'}
                      </span>
                    </div>
                  </div>
                </Carousel.Item>
              ))}
            </Carousel>
          )}

        <div className="table-responsive">
          <Table hover striped>
            <thead>
              <tr>
                <th style={{ width: '40px' }}></th>
                <th style={{ width: '80px' }}>Image</th>
                <th>Title</th>
                <th>Discount</th>
                <th>CTA</th>
                <th>Active</th>
                <th className="text-end">Actions</th>
              </tr>
            </thead>
            <tbody>
              {banners.map((b, idx) => (
                <tr key={b.id} className={!b.is_active ? 'table-active' : ''}>
                  <td className="text-muted">
                    <div className="d-flex flex-column gap-0">
                      <button
                        type="button"
                        className="border-0 bg-transparent p-0 text-muted"
                        style={{ lineHeight: 1 }}
                        onClick={() => move(idx, -1)}
                        disabled={idx === 0}
                        title="Move up"
                      >
                        <ChevronUpIcon size={14} />
                      </button>
                      <button
                        type="button"
                        className="border-0 bg-transparent p-0 text-muted"
                        style={{ lineHeight: 1 }}
                        onClick={() => move(idx, 1)}
                        disabled={idx === banners.length - 1}
                        title="Move down"
                      >
                        <ChevronDownIcon size={14} />
                      </button>
                    </div>
                  </td>
                  <td>
                    <div
                      className="d-flex align-items-center justify-content-center rounded"
                      style={{
                        width: 60,
                        height: 40,
                        backgroundColor: b.bg_color || '#f97316',
                        overflow: 'hidden',
                      }}
                    >
                      {b.image_url ? (
                        <ImageWithFallback
                          src={b.image_url}
                          alt={b.title}
                          className="w-100 h-100"
                          style={{ objectFit: 'cover' }}
                        />
                      ) : (
                        <span className="text-white small fw-bold">
                          {b.discount_label || b.title?.substring(0, 3)}
                        </span>
                      )}
                    </div>
                  </td>
                  <td>
                    <div className="fw-semibold">{b.title}</div>
                    {b.subtitle && (
                      <div className="text-muted small text-truncate" style={{ maxWidth: 250 }}>
                        {b.subtitle}
                      </div>
                    )}
                  </td>
                  <td>
                    {b.discount_label ? (
                      <Badge bg="success" pill>{b.discount_label}</Badge>
                    ) : (
                      <span className="text-muted">—</span>
                    )}
                  </td>
                  <td>
                    <span className="small text-muted">{b.cta_text || 'Shop Now'}</span>
                  </td>
                  <td>
                    <Form.Check
                      type="switch"
                      checked={b.is_active}
                      onChange={() => handleToggle(b)}
                      label={b.is_active ? 'Active' : 'Inactive'}
                    />
                  </td>
                  <td className="text-end">
                    <div className="d-inline-flex gap-1">
                      <Button size="sm" variant="outline-primary" onClick={() => openEdit(b)}>
                        Edit
                      </Button>
                      <Button size="sm" variant="outline-danger" onClick={() => remove(b)}>
                        Delete
                      </Button>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </Table>
        </div>
        </>
      )}

      <Modal show={showModal} onHide={() => setShowModal(false)} size="lg" centered>
        <Modal.Header closeButton>
          <Modal.Title>{editing ? 'Edit Banner' : 'Add Banner'}</Modal.Title>
        </Modal.Header>
        <Form onSubmit={handleSubmit}>
          <Modal.Body>
            {error && <Alert variant="danger">{error}</Alert>}

            <div className="row">
              <div className="col-md-8">
                <Form.Group className="mb-3" controlId="banner-title">
                  <Form.Label>Title *</Form.Label>
                  <Form.Control
                    type="text"
                    value={form.title}
                    onChange={(e) => setForm({ ...form, title: e.target.value })}
                    isInvalid={!!fieldErrors.title}
                    placeholder="e.g. Fresh Organic Vegetables"
                  />
                  <Form.Control.Feedback type="invalid">{fieldErrors.title}</Form.Control.Feedback>
                </Form.Group>

                <Form.Group className="mb-3" controlId="banner-subtitle">
                  <Form.Label>Subtitle</Form.Label>
                  <Form.Control
                    type="text"
                    value={form.subtitle}
                    onChange={(e) => setForm({ ...form, subtitle: e.target.value })}
                    placeholder="e.g. Farm to table goodness delivered to your door"
                  />
                </Form.Group>

                <div className="row">
                  <div className="col-md-6">
                    <Form.Group className="mb-3" controlId="banner-discount">
                      <Form.Label>Discount Label</Form.Label>
                      <Form.Control
                        type="text"
                        value={form.discount_label}
                        onChange={(e) => setForm({ ...form, discount_label: e.target.value })}
                        placeholder="e.g. 25% OFF"
                      />
                      <Form.Text className="text-muted">
                        Text shown in the badge (e.g. "25% OFF", "SALE", "NEW")
                      </Form.Text>
                    </Form.Group>
                  </div>
                  <div className="col-md-6">
                    <Form.Group className="mb-3" controlId="banner-bg">
                      <Form.Label>Background Color</Form.Label>
                      <div className="d-flex align-items-center gap-2">
                        <Form.Control
                          type="color"
                          value={form.bg_color || '#f97316'}
                          onChange={(e) => setForm({ ...form, bg_color: e.target.value })}
                          style={{ width: 48, height: 38, padding: 2 }}
                        />
                        <Form.Control
                          type="text"
                          value={form.bg_color}
                          onChange={(e) => setForm({ ...form, bg_color: e.target.value })}
                          placeholder="#f97316"
                          style={{ maxWidth: 120 }}
                        />
                      </div>
                    </Form.Group>
                  </div>
                </div>

                <div className="row">
                  <div className="col-md-6">
                    <Form.Group className="mb-3" controlId="banner-cta-text">
                      <Form.Label>Button Text</Form.Label>
                      <Form.Control
                        type="text"
                        value={form.cta_text}
                        onChange={(e) => setForm({ ...form, cta_text: e.target.value })}
                        placeholder="Shop Now"
                      />
                    </Form.Group>
                  </div>
                  <div className="col-md-6">
                    <Form.Group className="mb-3" controlId="banner-cta-link">
                      <Form.Label>Button Link</Form.Label>
                      <Form.Control
                        type="text"
                        value={form.cta_link}
                        onChange={(e) => setForm({ ...form, cta_link: e.target.value })}
                        placeholder="/shop"
                      />
                    </Form.Group>
                  </div>
                </div>

                <div className="row">
                  <div className="col-md-6">
                    <Form.Group className="mb-3" controlId="banner-active">
                      <Form.Label>Active</Form.Label>
                      <div>
                        <Form.Check
                          type="switch"
                          checked={form.is_active}
                          onChange={(e) => setForm({ ...form, is_active: e.target.checked })}
                          label={form.is_active ? 'Visible on storefront' : 'Hidden from storefront'}
                        />
                      </div>
                    </Form.Group>
                  </div>
                  <div className="col-md-6">
                    <Form.Group className="mb-3" controlId="banner-sort">
                      <Form.Label>Sort Order</Form.Label>
                      <Form.Control
                        type="number"
                        min="0"
                        value={form.sort_order}
                        onChange={(e) => setForm({ ...form, sort_order: e.target.value })}
                      />
                      <Form.Text className="text-muted">Lower = shown first</Form.Text>
                    </Form.Group>
                  </div>
                </div>
              </div>

              <div className="col-md-4">
                <Form.Group className="mb-3" controlId="banner-image">
                  <Form.Label>Banner Image</Form.Label>
                  <div
                    className="border rounded d-flex align-items-center justify-content-center mb-2"
                    style={{
                      height: 120,
                      backgroundColor: form.bg_color || '#f97316',
                      cursor: 'pointer',
                      overflow: 'hidden',
                    }}
                    onClick={() => fileInputRef.current?.click()}
                  >
                    {imagePreview ? (
                      <img
                        src={imagePreview}
                        alt="Preview"
                        style={{ width: '100%', height: '100%', objectFit: 'cover' }}
                      />
                    ) : (
                      <div className="text-center text-white">
                        <div className="small fw-semibold">Click to upload</div>
                        <div className="small opacity-75">or drag and drop</div>
                      </div>
                    )}
                  </div>
                  <Form.Control
                    ref={fileInputRef}
                    type="file"
                    accept="image/*"
                    onChange={handleImageChange}
                    style={{ display: 'none' }}
                  />
                  {imageFile && (
                    <Button
                      variant="outline-danger"
                      size="sm"
                      className="mt-1"
                      onClick={() => {
                        setImageFile(null);
                        setImagePreview(editing?.image_url || null);
                        if (fileInputRef.current) fileInputRef.current.value = '';
                      }}
                    >
                      Remove image
                    </Button>
                  )}
                  <Form.Text className="text-muted">
                    Optional. Recommended: 1200x500px. Max 20MB.
                  </Form.Text>
                </Form.Group>

                <div
                  className="rounded p-3"
                  style={{ backgroundColor: '#f8f9fa' }}
                >
                  <div className="small fw-semibold text-muted mb-2">Preview</div>
                  <div
                    className="rounded text-white p-3 text-center"
                    style={{ backgroundColor: form.bg_color || '#f97316' }}
                  >
                    {form.discount_label && (
                      <div className="d-inline-block bg-white bg-opacity-25 rounded-pill px-2 py-1 small fw-bold mb-2">
                        {form.discount_label}
                      </div>
                    )}
                    <div className="fw-bold">{form.title || 'Banner Title'}</div>
                    {form.subtitle && (
                      <div className="small opacity-90 mt-1">{form.subtitle}</div>
                    )}
                    <div className="mt-2">
                      <span className="btn btn-sm btn-white text-dark fw-semibold">
                        {form.cta_text || 'Shop Now'}
                      </span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </Modal.Body>
          <Modal.Footer>
            <Button variant="outline-secondary" onClick={() => setShowModal(false)}>
              Cancel
            </Button>
            <Button type="submit" variant="success" disabled={saving}>
              {saving ? 'Saving...' : editing ? 'Update Banner' : 'Create Banner'}
            </Button>
          </Modal.Footer>
        </Form>
      </Modal>
    </div>
  );
}
