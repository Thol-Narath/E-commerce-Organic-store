import { useState } from 'react';
import { Button, Card, Col, Container, Form, Row, Alert } from 'react-bootstrap';
import { Link, Navigate, useLocation, useNavigate } from 'react-router-dom';
import { useAuth } from '../../context/AuthContext';

export default function Register() {
  const { user, register } = useAuth();
  const navigate = useNavigate();
  const location = useLocation();
  const from = location.state?.from?.pathname || '/';

  const [form, setForm] = useState({
    name: '',
    email: '',
    phone: '',
    password: '',
    password_confirmation: '',
  });
  const [errors, setErrors] = useState(null);
  const [serverError, setServerError] = useState('');
  const [submitting, setSubmitting] = useState(false);

  if (user) {
    return <Navigate to="/" replace />;
  }

  const handleChange = (e) => {
    setForm({ ...form, [e.target.name]: e.target.value });
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setErrors(null);
    setServerError('');
    setSubmitting(true);

    try {
      await register(form);
      navigate(from, { replace: true });
    } catch (err) {
      setServerError(err.message);
      setErrors(err.errors);
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <Container className="py-5">
      <Row className="justify-content-center">
        <Col md={6} lg={5}>
          <Card className="shadow-sm">
            <Card.Body className="p-4">
              <h4 className="text-center mb-4">Create Account</h4>

              {serverError && <Alert variant="danger">{serverError}</Alert>}

              <Form onSubmit={handleSubmit} noValidate>
                <Form.Group className="mb-3" controlId="regName">
                  <Form.Label>Full name</Form.Label>
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

                <Form.Group className="mb-3" controlId="regEmail">
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

                <Form.Group className="mb-3" controlId="regPhone">
                  <Form.Label>Phone (optional)</Form.Label>
                  <Form.Control
                    type="tel"
                    name="phone"
                    value={form.phone}
                    onChange={handleChange}
                    isInvalid={Boolean(errors?.phone)}
                  />
                  <Form.Control.Feedback type="invalid">{errors?.phone?.[0]}</Form.Control.Feedback>
                </Form.Group>

                <Form.Group className="mb-3" controlId="regPassword">
                  <Form.Label>Password</Form.Label>
                  <Form.Control
                    type="password"
                    name="password"
                    value={form.password}
                    onChange={handleChange}
                    isInvalid={Boolean(errors?.password)}
                    required
                  />
                  <Form.Control.Feedback type="invalid">
                    {errors?.password?.[0]}
                  </Form.Control.Feedback>
                </Form.Group>

                <Form.Group className="mb-4" controlId="regPasswordConfirm">
                  <Form.Label>Confirm password</Form.Label>
                  <Form.Control
                    type="password"
                    name="password_confirmation"
                    value={form.password_confirmation}
                    onChange={handleChange}
                    isInvalid={Boolean(errors?.password)}
                    required
                  />
                </Form.Group>

                <Button type="submit" variant="success" className="w-100" disabled={submitting}>
                  {submitting ? 'Creating account...' : 'Register'}
                </Button>
              </Form>

              <p className="text-center mt-3 mb-0">
                Already have an account? <Link to="/login">Sign In</Link>
              </p>
            </Card.Body>
          </Card>
        </Col>
      </Row>
    </Container>
  );
}
