import { useCallback, useEffect, useState } from 'react';
import { Alert, Badge, Button, Form, Modal, Spinner, Table } from 'react-bootstrap';
import { adminCategoryService } from '../../services/adminCategoryService';
import { normalizeError } from '../../services/api';

const EMPTY = { name: '', description: '', status: 'active', sort_order: 0 };

export default function AdminCategories() {
  const [categories, setCategories] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  const [showModal, setShowModal] = useState(false);
  const [editing, setEditing] = useState(null);
  const [form, setForm] = useState(EMPTY);
  const [icon, setIcon] = useState(null);
  const [saving, setSaving] = useState(false);
  const [fieldErrors, setFieldErrors] = useState({});

  const load = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      setCategories(await adminCategoryService.list());
    } catch (e) {
      setError(normalizeError(e).message);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    load();
  }, [load]);

  const openCreate = () => {
    setEditing(null);
    setForm(EMPTY);
    setIcon(null);
    setFieldErrors({});
    setShowModal(true);
  };

  const openEdit = (category) => {
    setEditing(category);
    setForm({
      name: category.name,
      description: category.description || '',
      status: category.status,
      sort_order: category.sort_order || 0,
    });
    setIcon(null);
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
      description: form.description || undefined,
      status: form.status,
      sort_order: Number(form.sort_order) || 0,
    };

    try {
      if (editing) {
        await adminCategoryService.update(editing.id, payload, icon);
      } else {
        await adminCategoryService.create(payload, icon);
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

  const remove = async (category) => {
    if (!window.confirm(`Delete category "${category.name}"?`)) return;
    try {
      await adminCategoryService.remove(category.id);
      await load();
    } catch (e) {
      setError(normalizeError(e).message);
    }
  };

  return (
    <div>
      <div className="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <h2 className="h4 mb-0">Categories</h2>
        <Button variant="success" onClick={openCreate}>
          New Category
        </Button>
      </div>

      {error && <Alert variant="danger">{error}</Alert>}

      {loading ? (
        <div className="text-center py-5">
          <Spinner animation="border" variant="success" />
        </div>
      ) : categories.length === 0 ? (
        <Alert variant="info">No categories found.</Alert>
      ) : (
        <div className="table-responsive">
          <Table hover striped>
            <thead>
              <tr>
                <th>Name</th>
                <th>Status</th>
                <th>Products</th>
                <th className="text-end">Actions</th>
              </tr>
            </thead>
            <tbody>
              {categories.map((c) => (
                <tr key={c.id}>
                  <td>
                    <div className="d-flex align-items-center gap-2">
                      {c.icon_url ? (
                        <img src={c.icon_url} alt={c.name} width="32" height="32" className="category-cell-icon" />
                      ) : null}
                      <span className="fw-semibold">{c.name}</span>
                    </div>
                  </td>
                  <td>
                    <Badge pill bg={c.status === 'active' ? 'success' : 'secondary'}>
                      {c.status}
                    </Badge>
                  </td>
                  <td>{c.products_count}</td>
                  <td className="text-end">
                    <div className="d-inline-flex gap-1">
                      <Button size="sm" variant="outline-primary" onClick={() => openEdit(c)}>
                        Edit
                      </Button>
                      <Button size="sm" variant="outline-danger" onClick={() => remove(c)}>
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

      <Modal show={showModal} onHide={() => setShowModal(false)}>
        <Modal.Header closeButton>
          <Modal.Title>{editing ? 'Edit Category' : 'New Category'}</Modal.Title>
        </Modal.Header>
        <Form onSubmit={handleSubmit}>
          <Modal.Body>
            <Form.Group className="mb-3" controlId="cat-name">
              <Form.Label>Name *</Form.Label>
              <Form.Control
                type="text"
                value={form.name}
                onChange={(e) => setForm({ ...form, name: e.target.value })}
                isInvalid={!!fieldErrors.name}
              />
              <Form.Control.Feedback type="invalid">{fieldErrors.name}</Form.Control.Feedback>
            </Form.Group>

            <Form.Group className="mb-3" controlId="cat-description">
              <Form.Label>Description</Form.Label>
              <Form.Control
                as="textarea"
                rows={2}
                value={form.description}
                onChange={(e) => setForm({ ...form, description: e.target.value })}
              />
            </Form.Group>

            <div className="row mb-3">
              <div className="col-6">
                <Form.Group controlId="cat-status">
                  <Form.Label>Status *</Form.Label>
                  <Form.Select value={form.status} onChange={(e) => setForm({ ...form, status: e.target.value })}>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                  </Form.Select>
                </Form.Group>
              </div>
              <div className="col-6">
                <Form.Group controlId="cat-sort">
                  <Form.Label>Sort order</Form.Label>
                  <Form.Control
                    type="number"
                    min="0"
                    value={form.sort_order}
                    onChange={(e) => setForm({ ...form, sort_order: e.target.value })}
                  />
                </Form.Group>
              </div>
            </div>

            <Form.Group controlId="cat-icon">
              <Form.Label>Icon</Form.Label>
              <Form.Control type="file" accept="image/*" onChange={(e) => setIcon(e.target.files[0] || null)} />
            </Form.Group>
          </Modal.Body>
          <Modal.Footer>
            <Button variant="outline-secondary" onClick={() => setShowModal(false)}>
              Cancel
            </Button>
            <Button type="submit" variant="success" disabled={saving}>
              {saving ? 'Saving...' : editing ? 'Update Category' : 'Create Category'}
            </Button>
          </Modal.Footer>
        </Form>
      </Modal>
    </div>
  );
}
