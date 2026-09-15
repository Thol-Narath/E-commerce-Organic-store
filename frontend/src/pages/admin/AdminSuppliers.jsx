import { useCallback, useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { Alert, Badge, Button, Form, InputGroup, Modal, Spinner, Table } from 'react-bootstrap';
import { adminSupplierService } from '../../services/adminSupplierService';
import { normalizeError } from '../../services/api';
import StorePagination from '../../components/common/StorePagination';
import LoadingSpinner from '../../components/common/LoadingSpinner';
import EmptyState from '../../components/common/EmptyState';
import usePageTitle from '../../hooks/usePageTitle';
import { PackageIcon, PhoneIcon, MailIcon, MapPinIcon, SearchIcon, PlusIcon } from '../../assets/icons';

const EMPTY_SUPPLIER = {
  name: '',
  contact_person: '',
  email: '',
  phone: '',
  address: '',
  city: '',
  country: '',
  tax_id: '',
  notes: '',
  is_active: true,
};

const PER_PAGE = 15;

export default function AdminSuppliers() {
  usePageTitle('Suppliers');

  const [items, setItems] = useState([]);
  const [pagination, setPagination] = useState({ current_page: 1, per_page: PER_PAGE, total: 0, last_page: 1 });
  const [page, setPage] = useState(1);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [search, setSearch] = useState('');
  const [statusFilter, setStatusFilter] = useState('');

  const [showModal, setShowModal] = useState(false);
  const [editing, setEditing] = useState(null);
  const [form, setForm] = useState(EMPTY_SUPPLIER);
  const [saving, setSaving] = useState(false);
  const [fieldErrors, setFieldErrors] = useState({});

  const load = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      const { items: list, pagination: meta } = await adminSupplierService.list({
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
    setForm(EMPTY_SUPPLIER);
    setFieldErrors({});
    setShowModal(true);
  };

  const openEdit = (s) => {
    setEditing(s);
    setForm({
      name: s.name || '',
      contact_person: s.contact_person || '',
      email: s.email || '',
      phone: s.phone || '',
      address: s.address || '',
      city: s.city || '',
      country: s.country || '',
      tax_id: s.tax_id || '',
      notes: s.notes || '',
      is_active: s.is_active,
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
      contact_person: form.contact_person || undefined,
      email: form.email || undefined,
      phone: form.phone || undefined,
      address: form.address || undefined,
      city: form.city || undefined,
      country: form.country || undefined,
      tax_id: form.tax_id || undefined,
      notes: form.notes || undefined,
      is_active: !!form.is_active,
    };

    try {
      if (editing) {
        await adminSupplierService.update(editing.id, payload);
      } else {
        await adminSupplierService.create(payload);
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

  const remove = async (s) => {
    if (!window.confirm(`Delete supplier "${s.name}"?`)) return;
    try {
      await adminSupplierService.remove(s.id);
      await load();
    } catch (e) {
      setError(normalizeError(e).message);
    }
  };

  const handleToggle = async (s) => {
    try {
      await adminSupplierService.toggle(s.id);
      await load();
    } catch (e) {
      setError(normalizeError(e).message);
    }
  };

  return (
    <div>
      <div className="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <div>
          <h2 className="h4 mb-0">Suppliers</h2>
          <div className="text-muted small">
            Manage suppliers and restock low-inventory products directly from them.
          </div>
        </div>
        <div className="d-flex gap-2">
          <Button variant="outline-primary" as={Link} to="/admin/supplier-orders">
            <PackageIcon size={16} /> Purchase Orders
          </Button>
          <Button variant="success" onClick={openCreate}>
            <PlusIcon size={16} /> Add Supplier
          </Button>
        </div>
      </div>

      {error && <Alert variant="danger">{error}</Alert>}

      <div className="d-flex flex-wrap gap-2 mb-3">
        <InputGroup style={{ maxWidth: 360 }}>
          <InputGroup.Text className="bg-white">
            <SearchIcon size={16} className="text-muted" />
          </InputGroup.Text>
          <Form.Control
            type="search"
            placeholder="Name, email, phone..."
            value={search}
            onChange={(e) => { setSearch(e.target.value); setPage(1); }}
          />
        </InputGroup>
        <Form.Select
          style={{ maxWidth: 200 }}
          value={statusFilter}
          onChange={(e) => { setStatusFilter(e.target.value); setPage(1); }}
        >
          <option value="">All suppliers</option>
          <option value="active">Active</option>
          <option value="inactive">Inactive</option>
        </Form.Select>
      </div>

      {loading ? (
        <LoadingSpinner label="Loading suppliers..." />
      ) : items.length === 0 ? (
        <EmptyState title="No suppliers found" message="Add your first supplier to start ordering stock." />
      ) : (
        <>
          <div className="table-responsive">
            <Table hover striped className="align-middle">
              <thead>
                <tr>
                  <th>Supplier</th>
                  <th>Contact</th>
                  <th className="text-center">Orders</th>
                  <th>Status</th>
                  <th className="text-end">Actions</th>
                </tr>
              </thead>
              <tbody>
                {items.map((s) => (
                  <tr key={s.id} className={!s.is_active ? 'table-active' : ''}>
                    <td>
                      <div className="fw-semibold">{s.name}</div>
                      {s.tax_id && <div className="text-muted small">Tax ID: {s.tax_id}</div>}
                    </td>
                    <td>
                      {s.contact_person && <div className="small fw-medium">{s.contact_person}</div>}
                      {s.email && (
                        <div className="text-muted small mt-1 d-flex align-items-center gap-1">
                          <MailIcon size={13} /> {s.email}
                        </div>
                      )}
                      {s.phone && (
                        <div className="text-muted small d-flex align-items-center gap-1">
                          <PhoneIcon size={13} /> {s.phone}
                        </div>
                      )}
                    </td>
                    <td className="text-center">{s.orders_count ?? 0}</td>
                    <td>
                      <Form.Check
                        type="switch"
                        checked={s.is_active}
                        onChange={() => handleToggle(s)}
                        label={s.is_active ? 'Active' : 'Inactive'}
                      />
                    </td>
                    <td className="text-end">
                      <div className="d-inline-flex gap-1 flex-wrap justify-content-end">
                        <Button size="sm" variant="success" as={Link} to={`/admin/suppliers/${s.id}/order`}>
                          Order stock
                        </Button>
                        <Button size="sm" variant="outline-primary" onClick={() => openEdit(s)}>
                          Edit
                        </Button>
                        <Button size="sm" variant="outline-danger" onClick={() => remove(s)}>
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
              ariaLabel="Suppliers pagination"
            />
          )}
        </>
      )}

      <Modal show={showModal} onHide={() => setShowModal(false)} size="lg" centered>
        <Modal.Header closeButton>
          <Modal.Title>{editing ? 'Edit Supplier' : 'Add Supplier'}</Modal.Title>
        </Modal.Header>
        <Form onSubmit={handleSubmit}>
          <Modal.Body>
            {error && <Alert variant="danger">{error}</Alert>}

            <Form.Group className="mb-3" controlId="supplier-name">
              <Form.Label>Supplier name *</Form.Label>
              <Form.Control
                type="text"
                value={form.name}
                onChange={(e) => setForm({ ...form, name: e.target.value })}
                isInvalid={!!fieldErrors.name}
                placeholder="e.g. Green Valley Farms"
              />
              <Form.Control.Feedback type="invalid">{fieldErrors.name}</Form.Control.Feedback>
            </Form.Group>

            <div className="row">
              <div className="col-md-6">
                <Form.Group className="mb-3">
                  <Form.Label>Contact person</Form.Label>
                  <Form.Control
                    type="text"
                    value={form.contact_person}
                    onChange={(e) => setForm({ ...form, contact_person: e.target.value })}
                    placeholder="e.g. Sok Dara"
                  />
                </Form.Group>
              </div>
              <div className="col-md-6">
                <Form.Group className="mb-3">
                  <Form.Label>Phone</Form.Label>
                  <Form.Control
                    type="text"
                    value={form.phone}
                    onChange={(e) => setForm({ ...form, phone: e.target.value })}
                    placeholder="e.g. +855 12 345 678"
                  />
                </Form.Group>
              </div>
            </div>

            <Form.Group className="mb-3">
              <Form.Label>Email</Form.Label>
              <Form.Control
                type="email"
                value={form.email}
                onChange={(e) => setForm({ ...form, email: e.target.value })}
                isInvalid={!!fieldErrors.email}
                placeholder="supplier@example.com"
              />
              <Form.Control.Feedback type="invalid">{fieldErrors.email}</Form.Control.Feedback>
            </Form.Group>

            <div className="row">
              <div className="col-md-6">
                <Form.Group className="mb-3">
                  <Form.Label>Address</Form.Label>
                  <Form.Control
                    type="text"
                    value={form.address}
                    onChange={(e) => setForm({ ...form, address: e.target.value })}
                    placeholder="Street address"
                  />
                </Form.Group>
              </div>
              <div className="col-md-3">
                <Form.Group className="mb-3">
                  <Form.Label>City</Form.Label>
                  <Form.Control
                    type="text"
                    value={form.city}
                    onChange={(e) => setForm({ ...form, city: e.target.value })}
                  />
                </Form.Group>
              </div>
              <div className="col-md-3">
                <Form.Group className="mb-3">
                  <Form.Label>Country</Form.Label>
                  <Form.Control
                    type="text"
                    value={form.country}
                    onChange={(e) => setForm({ ...form, country: e.target.value })}
                  />
                </Form.Group>
              </div>
            </div>

            <div className="row">
              <div className="col-md-6">
                <Form.Group className="mb-3">
                  <Form.Label>Tax ID</Form.Label>
                  <Form.Control
                    type="text"
                    value={form.tax_id}
                    onChange={(e) => setForm({ ...form, tax_id: e.target.value })}
                    placeholder="Optional"
                  />
                </Form.Group>
              </div>
              <div className="col-md-6">
                <Form.Group className="mb-3">
                  <Form.Label>Active</Form.Label>
                  <div className="pt-2">
                    <Form.Check
                      type="switch"
                      checked={form.is_active}
                      onChange={(e) => setForm({ ...form, is_active: e.target.checked })}
                      label={form.is_active ? 'Active supplier' : 'Inactive supplier'}
                    />
                  </div>
                </Form.Group>
              </div>
            </div>

            <Form.Group className="mb-2">
              <Form.Label>Notes</Form.Label>
              <Form.Control
                as="textarea"
                rows={3}
                value={form.notes}
                onChange={(e) => setForm({ ...form, notes: e.target.value })}
                placeholder="Payment terms, lead times, etc."
              />
            </Form.Group>
          </Modal.Body>
          <Modal.Footer>
            <Button variant="outline-secondary" onClick={() => setShowModal(false)}>
              Cancel
            </Button>
            <Button type="submit" variant="success" disabled={saving}>
              {saving ? 'Saving...' : editing ? 'Update Supplier' : 'Create Supplier'}
            </Button>
          </Modal.Footer>
        </Form>
      </Modal>
    </div>
  );
}