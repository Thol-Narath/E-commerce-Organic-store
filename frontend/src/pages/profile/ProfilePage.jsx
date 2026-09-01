import { useState } from 'react';
import { Link } from 'react-router-dom';
import { Alert, Badge, Button, Card, Col, Form, Row } from 'react-bootstrap';
import { useAuth } from '../../context/AuthContext';
import { authService } from '../../services/authService';
import { normalizeError } from '../../services/api';
import usePageTitle from '../../hooks/usePageTitle';
import { formatDate } from '../../utils/format';

export default function ProfilePage() {
  usePageTitle('My Profile');
  const { user, updateUser } = useAuth();

  const [editing, setEditing] = useState(false);
  const [form, setForm] = useState({
    name: user?.name || '',
    email: user?.email || '',
    phone: user?.phone || '',
  });
  const [saving, setSaving] = useState(false);
  const [success, setSuccess] = useState('');
  const [errors, setErrors] = useState(null);
  const [serverError, setServerError] = useState('');

  const startEditing = () => {
    setForm({ name: user?.name || '', email: user?.email || '', phone: user?.phone || '' });
    setErrors(null);
    setServerError('');
    setSuccess('');
    setEditing(true);
  };

  const handleChange = (e) => {
    setForm({ ...form, [e.target.name]: e.target.value });
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setSaving(true);
    setSuccess('');
    setErrors(null);
    setServerError('');

    try {
      const result = await authService.updateProfile(form);
      updateUser(result.user);
      setSuccess('Your profile has been updated.');
      setEditing(false);
    } catch (err) {
      const apiError = normalizeError(err);
      setServerError(apiError.message);
      setErrors(apiError.errors);
    } finally {
      setSaving(false);
    }
  };

  return (
    <Container className="py-4">
      <Row className="justify-content-center">
        <Col md={9} lg={8}>
          <h1 className="h2 mb-4">My Profile</h1>

          <Row className="g-4">
            <Col md={4}>
              <Card className="shadow-sm">
                <Card.Body className="text-center">
                  <div className="avatar-circle mx-auto mb-3" aria-hidden="true">
                    {(user?.name || '?').charAt(0).toUpperCase()}
                  </div>
                  <h2 className="h5 mb-1">{user?.name}</h2>
                  <p className="text-muted small mb-2">{user?.email}</p>
                  <div className="d-flex justify-content-center gap-2 flex-wrap">
                    <Badge bg="light" text="dark" pill>Role: {user?.role}</Badge>
                    <Badge bg={user?.status === 'active' ? 'success' : 'secondary'} pill>
                      {user?.status}
                    </Badge>
                  </div>
                </Card.Body>
              </Card>
            </Col>

            <Col md={8}>
              <Card className="shadow-sm">
                <Card.Header className="d-flex justify-content-between align-items-center">
                  <span>Account details</span>
                  {!editing && (
                    <Button variant="outline-success" size="sm" onClick={startEditing}>
                      Edit
                    </Button>
                  )}
                </Card.Header>
                <Card.Body>
                  {success && <Alert variant="success">{success}</Alert>}
                  {serverError && <Alert variant="danger">{serverError}</Alert>}

                  {editing ? (
                    <Form onSubmit={handleSubmit} noValidate>
                      <Form.Group className="mb-3" controlId="profileName">
                        <Form.Label>Name</Form.Label>
                        <Form.Control
                          type="text"
                          name="name"
                          value={form.name}
                          onChange={handleChange}
                          isInvalid={Boolean(errors?.name)}
                          required
                        />
                        <Form.Control.Feedback type="invalid">{errors?.name?.[0]}</Form.Control.Feedback>
                      </Form.Group>

                      <Form.Group className="mb-3" controlId="profileEmail">
                        <Form.Label>Email address</Form.Label>
                        <Form.Control
                          type="email"
                          name="email"
                          value={form.email}
                          onChange={handleChange}
                          isInvalid={Boolean(errors?.email)}
                          required
                        />
                        <Form.Control.Feedback type="invalid">{errors?.email?.[0]}</Form.Control.Feedback>
                      </Form.Group>

                      <Form.Group className="mb-4" controlId="profilePhone">
                        <Form.Label>Phone</Form.Label>
                        <Form.Control
                          type="tel"
                          name="phone"
                          value={form.phone}
                          onChange={handleChange}
                          isInvalid={Boolean(errors?.phone)}
                        />
                        <Form.Control.Feedback type="invalid">{errors?.phone?.[0]}</Form.Control.Feedback>
                      </Form.Group>

                      <div className="d-flex gap-2">
                        <Button type="submit" variant="success" disabled={saving}>
                          {saving ? 'Saving...' : 'Save changes'}
                        </Button>
                        <Button type="button" variant="outline-secondary" onClick={() => setEditing(false)} disabled={saving}>
                          Cancel
                        </Button>
                      </div>
                    </Form>
                  ) : (
                    <dl className="row mb-0">
                      <dt className="col-sm-4 text-muted">Name</dt>
                      <dd className="col-sm-8">{user?.name}</dd>
                      <dt className="col-sm-4 text-muted">Email</dt>
                      <dd className="col-sm-8">{user?.email}</dd>
                      <dt className="col-sm-4 text-muted">Phone</dt>
                      <dd className="col-sm-8">{user?.phone || '—'}</dd>
                      <dt className="col-sm-4 text-muted">Member since</dt>
                      <dd className="col-sm-8">{formatDate(user?.created_at)}</dd>
                    </dl>
                  )}
                </Card.Body>
              </Card>

              <p className="text-muted small mt-3">
                Return to the <Link to="/shop">shop</Link> to keep browsing organic products.
              </p>
            </Col>
          </Row>
        </Col>
      </Row>
    </Container>
  );
}