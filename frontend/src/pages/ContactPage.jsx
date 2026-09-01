import { useState } from 'react';
import { Alert, Button, Card, Col, Container, Form, Row } from 'react-bootstrap';
import PageHeader from '../components/common/PageHeader';
import usePageTitle from '../hooks/usePageTitle';
import { MailIcon, MapPinIcon, PhoneIcon } from '../assets/icons';

const EMPTY_FORM = { name: '', email: '', subject: '', message: '' };

export default function ContactPage() {
  usePageTitle('Contact');

  const [form, setForm] = useState(EMPTY_FORM);

  const handleChange = (e) => {
    setForm({ ...form, [e.target.name]: e.target.value });
  };

  const handleSubmit = (e) => {
    // UI-only for now — a backend contact/notification API arrives in a later phase.
    e.preventDefault();
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
                  <MapPinIcon size={18} /> 123 Demo Road, Greenville
                </p>
                <p className="d-flex align-items-center gap-2 mb-2">
                  <PhoneIcon size={18} /> +1 555 0100
                </p>
                <p className="d-flex align-items-center gap-2 mb-2">
                  <MailIcon size={18} /> hello@organicstore.demo
                </p>
              </address>
              <p className="small text-muted mt-3 mb-0">
                Placeholder contact details — this is a demo store.
              </p>
            </Card.Body>
          </Card>
        </Col>

        <Col md={7} lg={8}>
          <Card>
            <Card.Body className="p-4">
              <Alert variant="info" className="mb-4">
                This form is display-only. Message delivery will be connected to the
                store backend in a later phase.
              </Alert>

              <Form onSubmit={handleSubmit}>
                <Row className="g-3">
                  <Col sm={6}>
                    <Form.Group controlId="contactName">
                      <Form.Label>Name</Form.Label>
                      <Form.Control type="text" name="name" value={form.name} onChange={handleChange} required />
                    </Form.Group>
                  </Col>
                  <Col sm={6}>
                    <Form.Group controlId="contactEmail">
                      <Form.Label>Email</Form.Label>
                      <Form.Control type="email" name="email" value={form.email} onChange={handleChange} required />
                    </Form.Group>
                  </Col>
                  <Col xs={12}>
                    <Form.Group controlId="contactSubject">
                      <Form.Label>Subject</Form.Label>
                      <Form.Control type="text" name="subject" value={form.subject} onChange={handleChange} required />
                    </Form.Group>
                  </Col>
                  <Col xs={12}>
                    <Form.Group controlId="contactMessage">
                      <Form.Label>Message</Form.Label>
                      <Form.Control as="textarea" rows={5} name="message" value={form.message} onChange={handleChange} required />
                    </Form.Group>
                  </Col>
                </Row>
                <div className="mt-4">
                  <Button type="submit" variant="success" disabled>
                    Send message — coming soon
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