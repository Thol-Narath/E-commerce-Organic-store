import { useState } from 'react';
import { Button, Card, Col, Container, Form, Row, Alert } from 'react-bootstrap';
import { Link, Navigate, useLocation, useNavigate } from 'react-router-dom';
import { useAuth } from '../../context/AuthContext';
import GoogleSignInButton from '../../components/auth/GoogleSignInButton';

export default function Login() {
  const { user, login, loginWithGoogle } = useAuth();
  const navigate = useNavigate();
  const location = useLocation();
  const from = location.state?.from?.pathname || '/';

  const [form, setForm] = useState({ email: '', password: '' });
  const [errors, setErrors] = useState(null);
  const [serverError, setServerError] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [googleLoading, setGoogleLoading] = useState(false);

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
      await login(form);
      navigate(from, { replace: true });
    } catch (err) {
      setServerError(err.message);
      setErrors(err.errors);
    } finally {
      setSubmitting(false);
    }
  };

  const handleGoogleToken = async (credential) => {
    setErrors(null);
    setServerError('');
    setGoogleLoading(true);

    try {
      await loginWithGoogle(credential);
      navigate(from, { replace: true });
    } catch (err) {
      setServerError(err.message);
      setErrors(err.errors);
    } finally {
      setGoogleLoading(false);
    }
  };

  return (
    <Container className="py-5">
      <Row className="justify-content-center">
        <Col md={5} lg={4}>
          <Card className="shadow-sm">
            <Card.Body className="p-4">
              <h4 className="text-center mb-4">Sign In</h4>

              {serverError && <Alert variant="danger">{serverError}</Alert>}

              <Form onSubmit={handleSubmit} noValidate>
                <Form.Group className="mb-3" controlId="loginEmail">
                  <Form.Label>Email address</Form.Label>
                  <Form.Control
                    type="email"
                    name="email"
                    value={form.email}
                    onChange={handleChange}
                    isInvalid={Boolean(errors?.email)}
                    required
                  />
                  <Form.Control.Feedback type="invalid">
                    {errors?.email?.[0]}
                  </Form.Control.Feedback>
                </Form.Group>

                <Form.Group className="mb-4" controlId="loginPassword">
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

                <Button type="submit" variant="success" className="w-100" disabled={submitting}>
                  {submitting ? 'Signing in...' : 'Sign In'}
                </Button>
              </Form>

              <div className="d-flex align-items-center my-4">
                <hr className="flex-grow-1" />
                <span className="text-muted px-3 small">or</span>
                <hr className="flex-grow-1" />
              </div>

              <div className="d-flex justify-content-center">
                <GoogleSignInButton
                  onToken={handleGoogleToken}
                  loading={googleLoading}
                />
              </div>

              <p className="text-center mt-3 mb-0">
                Don&apos;t have an account? <Link to="/register">Register</Link>
              </p>
            </Card.Body>
          </Card>
        </Col>
      </Row>
    </Container>
  );
}
