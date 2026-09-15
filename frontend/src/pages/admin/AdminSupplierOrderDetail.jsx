import { useCallback, useEffect, useMemo, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { Alert, Badge, Button, Card, Col, Form, Modal, Row, Table } from 'react-bootstrap';
import { adminSupplierService } from '../../services/adminSupplierService';
import { normalizeError } from '../../services/api';
import LoadingSpinner from '../../components/common/LoadingSpinner';
import usePageTitle from '../../hooks/usePageTitle';
import { formatPrice, formatDate } from '../../utils/format';

const STATUS_BADGES = {
  draft: { label: 'Draft', bg: 'secondary' },
  ordered: { label: 'Ordered', bg: 'info' },
  received: { label: 'Received', bg: 'success' },
  cancelled: { label: 'Cancelled', bg: 'danger' },
};

export default function AdminSupplierOrderDetail() {
  usePageTitle('Purchase Order');
  const { id } = useParams();

  const [order, setOrder] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [actionError, setActionError] = useState('');
  const [busy, setBusy] = useState(false);

  const [receiveModal, setReceiveModal] = useState(false);
  const [received, setReceived] = useState({});
  const [notes, setNotes] = useState('');
  const [cancelModal, setCancelModal] = useState(false);

  const load = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      setOrder(await adminSupplierService.getOrder(id));
    } catch (err) {
      setError(normalizeError(err).message);
    } finally {
      setLoading(false);
    }
  }, [id]);

  useEffect(() => { load(); }, [load]);

  const place = async () => {
    setBusy(true);
    setActionError('');
    try {
      setOrder(await adminSupplierService.placeOrder(order.id));
    } catch (err) {
      setActionError(normalizeError(err).message);
    } finally {
      setBusy(false);
    }
  };

  const openReceive = () => {
    setActionError('');
    const initial = {};
    (order?.items ?? []).forEach((it) => {
      initial[it.id] = it.quantity;
    });
    setReceived(initial);
    setReceiveModal(true);
  };

  const receive = async () => {
    setBusy(true);
    setActionError('');
    try {
      setOrder(await adminSupplierService.receiveOrder(order.id, received));
      setReceiveModal(false);
    } catch (err) {
      setActionError(normalizeError(err).message);
    } finally {
      setBusy(false);
    }
  };

  const cancel = async () => {
    setBusy(true);
    setActionError('');
    try {
      setOrder(await adminSupplierService.cancelOrder(order.id, notes));
      setCancelModal(false);
    } catch (err) {
      setActionError(normalizeError(err).message);
    } finally {
      setBusy(false);
    }
  };

  const badge = order ? (STATUS_BADGES[order.status] || STATUS_BADGES.draft) : null;

  const receivedTotals = useMemo(() => {
    if (!order) return { qty: 0, value: 0 };
    let qty = 0;
    let value = 0;
    const map = received || {};
    (order.items ?? []).forEach((it) => {
      const q = Number(map[it.id]) || 0;
      qty += q;
      value += (Number(it.unit_cost) || 0) * q;
    });
    return { qty, value };
  }, [order, received]);

  if (loading) {
    return <LoadingSpinner label="Loading purchase order..." />;
  }

  if (error || !order) {
    return <Alert variant="danger">{error || 'Purchase order not found.'}</Alert>;
  }

  return (
    <div>
      <div className="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <div>
          <h2 className="h4 mb-0">{order.order_number}</h2>
          <div className="text-muted small">
            {order.supplier?.name || 'Supplier'} · created {new Date(order.created_at).toLocaleDateString()}
          </div>
        </div>
        <Link to="/admin/supplier-orders" className="link-success small">
          ← Back to purchase orders
        </Link>
      </div>

      <div className="d-flex flex-wrap align-items-center gap-2 mb-3">
        <Badge bg={badge.bg} className="fs-6">{badge.label}</Badge>
        {order.status === 'draft' && (
          <Button variant="info" size="sm" onClick={place} disabled={busy}>
            {busy ? 'Placing...' : 'Place order'}
          </Button>
        )}
        {order.status === 'ordered' && (
          <Button variant="success" size="sm" onClick={openReceive} disabled={busy}>
            Mark as received
          </Button>
        )}
        {(order.status === 'draft' || order.status === 'ordered') && (
          <Button variant="outline-danger" size="sm" onClick={() => { setActionError(''); setCancelModal(true); }} disabled={busy}>
            Cancel order
          </Button>
        )}
      </div>

      {(error && order) && <Alert variant="danger">{error}</Alert>}
      {actionError && <Alert variant="danger">{actionError}</Alert>}

      <Row className="g-3 mb-4">
        <Col xs={6} md={3}>
          <Card className="shadow-sm border-0 admin-stat-card">
            <Card.Body>
              <div className="text-muted small">Subtotal</div>
              <div className="fw-bold">{formatPrice(order.subtotal)}</div>
            </Card.Body>
          </Card>
        </Col>
        <Col xs={6} md={3}>
          <Card className="shadow-sm border-0 admin-stat-card">
            <Card.Body>
              <div className="text-muted small">Shipping</div>
              <div className="fw-bold">{formatPrice(order.shipping_fee)}</div>
            </Card.Body>
          </Card>
        </Col>
        <Col xs={6} md={3}>
          <Card className="shadow-sm border-0 admin-stat-card">
            <Card.Body>
              <div className="text-muted small">Total</div>
              <div className="fw-bold text-success">{formatPrice(order.total)}</div>
            </Card.Body>
          </Card>
        </Col>
        <Col xs={6} md={3}>
          <Card className="shadow-sm border-0 admin-stat-card">
            <Card.Body>
              <div className="text-muted small">Expected delivery</div>
              <div className="fw-semibold">
                {order.expected_delivery_date ? formatDate(order.expected_delivery_date) : '—'}
              </div>
            </Card.Body>
          </Card>
        </Col>
      </Row>

      <Card className="shadow-sm mb-3">
        <Card.Header className="bg-white fw-semibold">
          Items
        </Card.Header>
        <Card.Body className="p-0">
          <div className="table-responsive">
            <Table hover className="mb-0 align-middle">
              <thead>
                <tr>
                  <th>Product</th>
                  <th className="text-end">Ordered</th>
                  <th className="text-end">Received</th>
                  <th className="text-end">Unit cost</th>
                  <th className="text-end">Line total</th>
                </tr>
              </thead>
              <tbody>
                {order.items?.map((it) => (
                  <tr key={it.id}>
                    <td>
                      <div className="fw-medium">{it.product_name}</div>
                      <div className="text-muted small">SKU: {it.sku || '—'}</div>
                    </td>
                    <td className="text-end">{it.quantity}</td>
                    <td className="text-end">
                      {it.quantity_received || 0}
                      {it.quantity_received < it.quantity && order.status === 'received' && (
                        <span className="text-muted small"> (short)</span>
                      )}
                    </td>
                    <td className="text-end">{formatPrice(it.unit_cost)}</td>
                    <td className="text-end fw-semibold">{formatPrice(it.line_total)}</td>
                  </tr>
                ))}
              </tbody>
            </Table>
          </div>
        </Card.Body>
      </Card>

      {order.notes && (
        <Card className="shadow-sm mb-3">
          <Card.Header className="bg-white fw-semibold">Notes</Card.Header>
          <Card.Body className="text-muted small">{order.notes}</Card.Body>
        </Card>
      )}

      <Modal show={receiveModal} onHide={() => setReceiveModal(false)} centered>
        <Modal.Header closeButton>
          <Modal.Title>Receive supplier order</Modal.Title>
        </Modal.Header>
        <Modal.Body>
          <p className="text-muted small">
            Confirm received quantities below. Received quantities are added to product
            stock and recorded in the inventory ledger.
          </p>
          <Table size="sm">
            <thead>
              <tr>
                <th>Product</th>
                <th className="text-end">Ordered</th>
                <th className="text-end">Receive</th>
              </tr>
            </thead>
            <tbody>
              {(order.items ?? []).map((it) => (
                <tr key={it.id}>
                  <td>
                    <div className="fw-medium">{it.product_name}</div>
                    <div className="text-muted small">SKU: {it.sku || '—'}</div>
                  </td>
                  <td className="text-end">{it.quantity}</td>
                  <td className="text-end" style={{ width: 110 }}>
                    <Form.Control
                      type="number"
                      min="0"
                      max={it.quantity}
                      size="sm"
                      value={received[it.id] ?? 0}
                      onChange={(e) => setReceived({ ...received, [it.id]: e.target.value })}
                    />
                  </td>
                </tr>
              ))}
            </tbody>
          </Table>
          <div className="d-flex justify-content-between fw-semibold border-top pt-2">
            <span>Total received</span>
            <span>{receivedTotals.qty} units · {formatPrice(receivedTotals.value)}</span>
          </div>
        </Modal.Body>
        <Modal.Footer>
          <Button variant="outline-secondary" onClick={() => setReceiveModal(false)}>Cancel</Button>
          <Button variant="success" onClick={receive} disabled={busy}>
            {busy ? 'Receiving...' : 'Confirm receipt'}
          </Button>
        </Modal.Footer>
      </Modal>

      <Modal show={cancelModal} onHide={() => setCancelModal(false)} centered>
        <Modal.Header closeButton>
          <Modal.Title>Cancel purchase order</Modal.Title>
        </Modal.Header>
        <Modal.Body>
          <p className="text-muted small">
            This action cannot be undone. Cancelling does not change any stock levels.
          </p>
          <Form.Group>
            <Form.Label>Reason (optional)</Form.Label>
            <Form.Control
              as="textarea"
              rows={3}
              value={notes}
              onChange={(e) => setNotes(e.target.value)}
              placeholder="Why is this order being cancelled?"
            />
          </Form.Group>
        </Modal.Body>
        <Modal.Footer>
          <Button variant="outline-secondary" onClick={() => setCancelModal(false)}>Back</Button>
          <Button variant="danger" onClick={cancel} disabled={busy}>
            {busy ? 'Cancelling...' : 'Cancel order'}
          </Button>
        </Modal.Footer>
      </Modal>
    </div>
  );
}