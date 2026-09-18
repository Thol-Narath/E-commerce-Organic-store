import { useEffect, useState } from 'react';
import { Alert, Button, Card, Col, Container, Form, Row, Spinner } from 'react-bootstrap';
import PageHeader from '../components/common/PageHeader';
import usePageTitle from '../hooks/usePageTitle';
import { settingsService } from '../services/settingsService';
import { contactService } from '../services/contactService';
import { normalizeError } from '../services/api';
import { MailIcon, MapPinIcon, PhoneIcon } from '../assets/icons';

const EMPTY_FORM = { name: '', email: '', subject: '', message: '' };

export default function ContactPage() {
  usePageTitle('Contact');

  const [contact, setContact] = useState({ address: '', phone: '', email: '' });
  const [form, setForm] = useState(EMPTY_FORM);
  const [submitting, setSubmitting] = useState(false);
  const [successMessage, setSuccessMessage] = useState('');
  const [errorMessage, setErrorMessage] = useState('');

  useEffect(() => {
    let active = true;
    settingsService
      .publicSettings()
      .then((data) => {
        if (!active) return;
        setContact({
          address: data?.contact?.address || '',
          phone: data?.contact?.phone || '',
          email: data?.contact?.email || '',
        });
      })
      .catch(() => {});
    return () => {
      active = false;
    };
  }, []);

  const handleChange = (e) => {
    setForm({ ...form, [e.target.name]: e.target.value });
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    if (submitting) return;
    setSubmitting(true);
    setSuccessMessage('');
    setErrorMessage('');
    try {
      await contactService.sendMessage(form);
      setSuccessMessage('Thank you! Your message has been received. We will get back to you soon.');
      setForm(EMPTY_FORM);
    } catch (err) {
      setErrorMessage(normalizeError(err).message);
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <Container className="py-4">
      <PageHeader title="Contact Us" subtitle="We would love to hear from you." />

      <Row className="g-4 justify-content-center">
        <Col md={5} lg={4}>
          <Card className="border-0 bg-light h-100">
            <Card.Body className="p-4">
              <h2 className="h6 mb-3">Contact information</h2>
              <address className="mb-0">
                <p className="d-flex align-items-center gap-2 mb-2">
                  <MapPinIcon size={18} /> {contact.address || 'Please contact us by email or phone.'}
                </p>
                <p className="d-flex align-items-center gap-2 mb-2">
                  <PhoneIcon size={18} /> {contact.phone || '—'}
                </p>
                <p className="d-flex align-items-center gap-2 mb-2">
                  <MailIcon size={18} /> {contact.email || '—'}
                </p>
              </address>
            </Card.Body>
          </Card>
        </Col>

        <Col md={7} lg={8}>
          <Card>
            <Card.Body className="p-4">
              {successMessage && (
                <Alert variant="success" onClose={() => setSuccessMessage('')} dismissible>
                  {successMessage}
                </Alert>
              )}
              {errorMessage && (
                <Alert variant="danger" onClose={() => setErrorMessage('')} dismissible>
                  {errorMessage}
                </Alert>
              )}

              <Form onSubmit={handleSubmit}>
                <Row className="g-3">
                  <Col sm={6}>
                    <Form.Group controlId="contactName">
                      <Form.Label>Name</Form.Label>
                      <Form.Control type="text" name="name" value={form.name} onChange={handleChange} required disabled={submitting} />
                    </Form.Group>
                  </Col>
                  <Col sm={6}>
                    <Form.Group controlId="contactEmail">
                      <Form.Label>Email</Form.Label>
                      <Form.Control type="email" name="email" value={form.email} onChange={handleChange} required disabled={submitting} />
                    </Form.Group>
                  </Col>
                  <Col xs={12}>
                    <Form.Group controlId="contactSubject">
                      <Form.Label>Subject</Form.Label>
                      <Form.Control type="text" name="subject" value={form.subject} onChange={handleChange} required disabled={submitting} />
                    </Form.Group>
                  </Col>
                  <Col xs={12}>
                    <Form.Group controlId="contactMessage">
                      <Form.Label>Message</Form.Label>
                      <Form.Control as="textarea" rows={5} name="message" value={form.message} onChange={handleChange} required disabled={submitting} />
                    </Form.Group>
                  </Col>
                </Row>
                <div className="mt-4">
                  <Button type="submit" variant="success" disabled={submitting}>
                    {submitting ? (
                      <>
                        <Spinner as="span" animation="border" size="sm" className="me-2" /> Sending…
                      </>
                    ) : (
                      'Send message'
                    )}
                  </Button>
                </div>
              </Form>
            </Card.Body>
          </Card>
        </Col>
      </Row>
    </Container>
  );
}