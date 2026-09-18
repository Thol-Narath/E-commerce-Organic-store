import { useCallback, useEffect, useState } from 'react';
import { Alert, Badge, Button, Col, Form, InputGroup, Modal, Row, Spinner, Table } from 'react-bootstrap';
import { adminShippingService } from '../../services/adminShippingService';
import { normalizeError } from '../../services/api';
import StorePagination from '../../components/common/StorePagination';
import LoadingSpinner from '../../components/common/LoadingSpinner';
import EmptyState from '../../components/common/EmptyState';
import usePageTitle from '../../hooks/usePageTitle';
import { TruckIcon, SearchIcon, PlusIcon, CheckCircleIcon, ClockIcon, SaveIcon } from '../../assets/icons';
import { formatPrice } from '../../utils/format';

const EMPTY_METHOD = {
  name: '',
  code: '',
  description: '',
  base_rate: '',
  free_over: '',
  estimated_days: '',
  is_active: true,
  is_default: false,
  sort_order: 0,
};

const PER_PAGE = 15;

export default function AdminShippingMethods() {
  usePageTitle('Shipping Methods');

  const [items, setItems] = useState([]);
  const [pagination, setPagination] = useState({ current_page: 1, per_page: PER_PAGE, total: 0, last_page: 1 });
  const [page, setPage] = useState(1);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [search, setSearch] = useState('');
  const [statusFilter, setStatusFilter] = useState('');

  const [showModal, setShowModal] = useState(false);
  const [editing, setEditing] = useState(null);
  const [form, setForm] = useState(EMPTY_METHOD);
  const [saving, setSaving] = useState(false);
  const [fieldErrors, setFieldErrors] = useState({});

  const load = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      const { items: list, pagination: meta } = await adminShippingService.list({
        page,
        per_page: PER_PAGE,
        search: search || undefined,
        status: statusFilter || undefined,
      });
      setItems(list || []);
      setPagination(meta || {});
    } catch (err) {
      setError(normalizeError(err).message);
    } finally {
      setLoading(false);
    }
  }, [page, search, statusFilter]);

  useEffect(() => { load(); }, [load]);

  const openCreate = () => {
    setEditing(null);
    setForm(EMPTY_METHOD);
    setFieldErrors({});
    setShowModal(true);
  };

  const openEdit = (m) => {
    setEditing(m);
    setForm({
      name: m.name || '',
      code: m.code || '',
      description: m.description || '',
      base_rate: m.base_rate ?? '',
      free_over: m.free_over ?? '',
      estimated_days: m.estimated_days ?? '',
      is_active: !!m.is_active,
      is_default: !!m.is_default,
      sort_order: m.sort_order ?? 0,
    });
    setFieldErrors({});
    setShowModal(true);
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setSaving(true);
    setError('');
    setFieldErrors({});

    const payload = {
      name: form.name,
      code: form.code,
      description: form.description || undefined,
      base_rate: form.base_rate === '' ? 0 : Number(form.base_rate),
      free_over: form.free_over === '' ? undefined : Number(form.free_over),
      estimated_days: form.estimated_days === '' ? undefined : Number(form.estimated_days),
      is_active: !!form.is_active,
      is_default: !!form.is_default,
      sort_order: Number(form.sort_order) || 0,
    };

    try {
      if (editing) {
        await adminShippingService.update(editing.id, payload);
      } else {
        await adminShippingService.create(payload);
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

  const remove = async (m) => {
    if (!window.confirm(`Delete shipping method "${m.name}"? Existing orders keep their snapshots.`)) return;
    try {
      await adminShippingService.remove(m.id);
      await load();
    } catch (e) {
      setError(normalizeError(e).message);
    }
  };

  const handleToggle = async (m) => {
    try {
      await adminShippingService.toggle(m.id);
      await load();
    } catch (e) {
      setError(normalizeError(e).message);
    }
  };

  const handleDefault = async (m) => {
    try {
      await adminShippingService.setDefault(m.id);
      await load();
    } catch (e) {
      setError(normalizeError(e).message);
    }
  };

  return (
    <div>
      <div className="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <div>
          <h2 className="h4 mb-0 d-flex align-items-center gap-2">
            <TruckIcon size={22} className="text-success" />
            Shipping Methods
          </h2>
          <div className="text-muted small">
            Control the delivery options customers can choose at checkout and their rates.
          </div>
        </div>
        <Button variant="success" onClick={openCreate}>
          <PlusIcon size={16} /> Add Method
        </Button>
      </div>

      {error && <Alert variant="danger">{error}</Alert>}

      <div className="d-flex flex-wrap gap-2 mb-3">
        <InputGroup style={{ maxWidth: 320 }}>
          <InputGroup.Text className="bg-white">
            <SearchIcon size={16} className="text-muted" />
          </InputGroup.Text>
          <Form.Control
            type="search"
            placeholder="Search name or code..."
            value={search}
            onChange={(e) => { setSearch(e.target.value); setPage(1); }}
          />
        </InputGroup>
        <Form.Select
          style={{ maxWidth: 200 }}
          value={statusFilter}
          onChange={(e) => { setStatusFilter(e.target.value); setPage(1); }}
        >
          <option value="">All methods</option>
          <option value="active">Active</option>
          <option value="inactive">Inactive</option>
        </Form.Select>
      </div>

      {loading ? (
        <LoadingSpinner label="Loading shipping methods..." />
      ) : items.length === 0 ? (
        <EmptyState title="No shipping methods found" message="Add a shipping method to start offering delivery at checkout." />
      ) : (
        <>
          <div className="table-responsive">
            <Table hover striped className="align-middle">
              <thead>
                <tr>
                  <th>Method</th>
                  <th className="text-end">Rate</th>
                  <th className="text-end">Free over</th>
                  <th className="text-center">Delivery</th>
                  <th className="text-center">Default</th>
                  <th className="text-center">Status</th>
                  <th className="text-end">Actions</th>
                </tr>
              </thead>
              <tbody>
                {items.map((m) => (
                  <tr key={m.id} className={!m.is_active ? 'table-active' : ''}>
                    <td>
                      <div className="fw-semibold">{m.name}</div>
                      {m.code && (
                        <div className="text-muted small">
                          <span className="badge bg-light text-secondary border">{m.code}</span>
                        </div>
                      )}
                      {m.description && (
                        <div className="text-muted small mt-1 text-truncate" style={{ maxWidth: 260 }}>
                          {m.description}
                        </div>
                      )}
                    </td>
                    <td className="text-end fw-semibold">{formatPrice(m.base_rate)}</td>
                    <td className="text-end">
                      {m.free_over === null || m.free_over === '' ? <span className="text-muted">—</span> : formatPrice(m.free_over)}
                    </td>
                    <td className="text-center">
                      {m.estimated_days ? (
                        <span className="d-inline-flex align-items-center gap-1">
                          <ClockIcon size={14} className="text-muted" /> {m.estimated_days}d
                        </span>
                      ) : (
                        <span className="text-muted">—</span>
                      )}
                    </td>
                    <td className="text-center">
                      {m.is_default ? (
                        <Badge bg="success">
                          <span className="d-inline-flex align-items-center gap-1">
                            <CheckCircleIcon size={12} /> Default
                          </span>
                        </Badge>
                      ) : (
                        <Button size="sm" variant="outline-secondary" onClick={() => handleDefault(m)}>
                          Set default
                        </Button>
                      )}
                    </td>
                    <td className="text-center">
                      <Form.Check
                        type="switch"
                        checked={m.is_active}
                        onChange={() => handleToggle(m)}
                        label={m.is_active ? 'Active' : 'Inactive'}
                      />
                    </td>
                    <td className="text-end">
                      <div className="d-inline-flex gap-1">
                        <Button size="sm" variant="outline-primary" onClick={() => openEdit(m)}>
                          Edit
                        </Button>
                        <Button size="sm" variant="outline-danger" onClick={() => remove(m)}>
                          Delete
                        </Button>
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </Table>
          </div>

          {pagination.last_page > 1 && (
            <StorePagination
              pagination={pagination}
              onPageChange={setPage}
              disabled={loading}
              ariaLabel="Shipping methods pagination"
            />
          )}
        </>
      )}

      <Modal show={showModal} onHide={() => setShowModal(false)} size="lg" centered>
        <Modal.Header closeButton>
          <Modal.Title>{editing ? 'Edit Shipping Method' : 'Add Shipping Method'}</Modal.Title>
        </Modal.Header>
        <Form onSubmit={handleSubmit}>
          <Modal.Body>
            {error && <Alert variant="danger">{error}</Alert>}

            <Row className="g-3">
              <Col md={8}>
                <Form.Group controlId="shipping-name">
                  <Form.Label>Name *</Form.Label>
                  <Form.Control
                    type="text"
                    value={form.name}
                    onChange={(e) => setForm({ ...form, name: e.target.value })}
                    isInvalid={!!fieldErrors.name}
                    placeholder="e.g. Express Shipping"
                  />
                  <Form.Control.Feedback type="invalid">{fieldErrors.name}</Form.Control.Feedback>
                </Form.Group>
              </Col>
              <Col md={4}>
                <Form.Group controlId="shipping-code">
                  <Form.Label>Code *</Form.Label>
                  <Form.Control
                    type="text"
                    value={form.code}
                    onChange={(e) => setForm({ ...form, code: e.target.value })}
                    isInvalid={!!fieldErrors.code}
                    placeholder="e.g. express"
                    disabled={!!editing}
                  />
                  <Form.Control.Feedback type="invalid">{fieldErrors.code}</Form.Control.Feedback>
                  <Form.Text className="text-muted">Single lowercase word, editable on create only.</Form.Text>
                </Form.Group>
              </Col>
            </Row>

            <Form.Group className="mb-3 mt-3" controlId="shipping-description">
              <Form.Label>Description (optional)</Form.Label>
              <Form.Control
                as="textarea"
                rows={2}
                value={form.description}
                onChange={(e) => setForm({ ...form, description: e.target.value })}
                placeholder="Shown next to the method at checkout."
                maxLength={1000}
              />
            </Form.Group>

            <Row className="g-3">
              <Col md={4}>
                <Form.Group controlId="shipping-base-rate">
                  <Form.Label>Base rate *</Form.Label>
                  <Form.Control
                    type="number"
                    step="0.01"
                    min="0"
                    value={form.base_rate}
                    onChange={(e) => setForm({ ...form, base_rate: e.target.value })}
                    isInvalid={!!fieldErrors.base_rate}
                    placeholder="0.00"
                  />
                  <Form.Control.Feedback type="invalid">{fieldErrors.base_rate}</Form.Control.Feedback>
                </Form.Group>
              </Col>
              <Col md={4}>
                <Form.Group controlId="shipping-free-over">
                  <Form.Label>Free over (optional)</Form.Label>
                  <Form.Control
                    type="number"
                    step="0.01"
                    min="0"
                    value={form.free_over}
                    onChange={(e) => setForm({ ...form, free_over: e.target.value })}
                    isInvalid={!!fieldErrors.free_over}
                    placeholder="e.g. 50.00"
                  />
                  <Form.Control.Feedback type="invalid">{fieldErrors.free_over}</Form.Control.Feedback>
                  <Form.Text className="text-muted">Leave blank for no free shipping.</Form.Text>
                </Form.Group>
              </Col>
              <Col md={4}>
                <Form.Group controlId="shipping-estimated-days">
                  <Form.Label>Delivery (days)</Form.Label>
                  <Form.Control
                    type="number"
                    min="1"
                    max="365"
                    value={form.estimated_days}
                    onChange={(e) => setForm({ ...form, estimated_days: e.target.value })}
                    isInvalid={!!fieldErrors.estimated_days}
                    placeholder="e.g. 3"
                  />
                  <Form.Control.Feedback type="invalid">{fieldErrors.estimated_days}</Form.Control.Feedback>
                </Form.Group>
              </Col>
            </Row>

            <Row className="g-3 mt-1">
              <Col md={4}>
                <Form.Group controlId="shipping-sort-order">
                  <Form.Label>Sort order</Form.Label>
                  <Form.Control
                    type="number"
                    min="0"
                    value={form.sort_order}
                    onChange={(e) => setForm({ ...form, sort_order: e.target.value })}
                    isInvalid={!!fieldErrors.sort_order}
                  />
                  <Form.Text className="text-muted">Lower numbers appear first.</Form.Text>
                </Form.Group>
              </Col>
              <Col md={4}>
                <Form.Label>Active</Form.Label>
                <div className="pt-2">
                  <Form.Check
                    type="switch"
                    checked={form.is_active}
                    onChange={(e) => setForm({ ...form, is_active: e.target.checked })}
                    label={form.is_active ? 'Offered at checkout' : 'Hidden from checkout'}
                  />
                </div>
              </Col>
              <Col md={4}>
                <Form.Label>Default</Form.Label>
                <div className="pt-2">
                  <Form.Check
                    type="switch"
                    checked={form.is_default}
                    onChange={(e) => setForm({ ...form, is_default: e.target.checked })}
                    label="Preselect this method at checkout"
                  />
                  {fieldErrors.is_default && <div className="text-danger small">{fieldErrors.is_default}</div>}
                </div>
              </Col>
            </Row>
          </Modal.Body>
          <Modal.Footer>
            <Button variant="outline-secondary" onClick={() => setShowModal(false)}>
              Cancel
            </Button>
            <Button type="submit" variant="success" disabled={saving}>
              {saving ? (
                <><Spinner as="span" animation="border" size="sm" className="me-1" /> Saving...</>
              ) : (
                <><SaveIcon size={16} className="me-1" /> {editing ? 'Update Method' : 'Create Method'}</>
              )}
            </Button>
          </Modal.Footer>
        </Form>
      </Modal>
    </div>
  );
}