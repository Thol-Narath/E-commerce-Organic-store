import { useCallback, useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import {
  Alert, Badge, Button, Card, Col, Form, ListGroup, Row, Table,
} from 'react-bootstrap';
import { adminOrderService } from '../../services/adminOrderService';
import OrderStatusBadge from '../../components/orders/OrderStatusBadge';
import ConfirmDialog from '../../components/common/ConfirmDialog';
import LoadingSpinner from '../../components/common/LoadingSpinner';
import ErrorState from '../../components/common/ErrorState';
import usePageTitle from '../../hooks/usePageTitle';
import { useToast } from '../../context/ToastContext';
import { formatPrice, formatDate } from '../../utils/format';
import { getErrorMessage } from '../../utils/error';

const NEXT_STATES = {
  pending: ['confirmed'],
  confirmed: ['processing'],
  processing: ['shipped'],
  shipped: ['delivered'],
  delivered: [],
  cancelled: [],
  refunded: [],
};

const CANCELLABLE = ['pending', 'confirmed', 'processing'];

export default function AdminOrderDetail() {
  const { id } = useParams();
  usePageTitle('Order Details');
  const { showToast } = useToast();

  const [order, setOrder] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  const [nextStatus, setNextStatus] = useState('');
  const [statusNote, setStatusNote] = useState('');
  const [statusBusy, setStatusBusy] = useState(false);

  const [showCancel, setShowCancel] = useState(false);
  const [cancelNote, setCancelNote] = useState('');
  const [cancelBusy, setCancelBusy] = useState(false);

  const [noteText, setNoteText] = useState('');
  const [noteBusy, setNoteBusy] = useState(false);

  const load = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      const data = await adminOrderService.get(id);
      setOrder(data);
      setNextStatus('');
    } catch (err) {
      setError(getErrorMessage(err));
    } finally {
      setLoading(false);
    }
  }, [id]);

  useEffect(() => {
    load();
  }, [load]);

  const applyStatus = async () => {
    if (!nextStatus) return;
    setStatusBusy(true);
    try {
      const updated = await adminOrderService.updateStatus(id, nextStatus, statusNote);
      setOrder(updated);
      setStatusNote('');
      showToast('Order status updated.');
    } catch (err) {
      showToast(getErrorMessage(err), 'danger');
    } finally {
      setStatusBusy(false);
    }
  };

  const confirmCancel = async () => {
    setCancelBusy(true);
    try {
      const updated = await adminOrderService.cancel(id, cancelNote);
      setOrder(updated);
      setShowCancel(false);
      setCancelNote('');
      if (updated.payment_status === 'paid') {
        showToast('Order cancelled. Refund processing required.');
      } else {
        showToast('Order cancelled.');
      }
    } catch (err) {
      showToast(getErrorMessage(err), 'danger');
    } finally {
      setCancelBusy(false);
    }
  };

  const addNote = async () => {
    if (!noteText.trim()) return;
    setNoteBusy(true);
    try {
      await adminOrderService.addNote(id, noteText.trim());
      setNoteText('');
      showToast('Note added.');
      await load();
    } catch (err) {
      showToast(getErrorMessage(err), 'danger');
    } finally {
      setNoteBusy(false);
    }
  };

  if (loading) {
    return <LoadingSpinner label="Loading order..." />;
  }

  if (error || !order) {
    return (
      <ErrorState
        title="Order not found"
        message={error}
        onRetry={order ? load : undefined}
      />
    );
  }

  const allowedNext = NEXT_STATES[order.status] || [];
  const canCancel = CANCELLABLE.includes(order.status);
  const needsRefund = order.status === 'cancelled' && order.payment_status === 'paid';

  return (
    <div>
      <div className="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <div>
          <Link to="/admin/orders" className="link-success small me-3">
            ← Back to orders
          </Link>
          <h2 className="h4 mb-0 d-inline-flex align-items-center gap-2 flex-wrap">
            {order.order_number}
            <OrderStatusBadge status={order.status} />
            <OrderStatusBadge status={order.payment_status} type="payment" />
          </h2>
          <div className="text-muted small mt-1">
            {formatDate(order.placed_at || order.created_at)}
          </div>
        </div>
      </div>

      {needsRefund && (
        <Alert variant="warning">
          This paid order was cancelled. The refund must be processed manually by the finance team — the
          system does not auto-refund.
        </Alert>
      )}

      <Row className="g-3">
        <Col lg={8}>
          <Row className="g-3">
            <Col md={6}>
              <Card className="shadow-sm h-100">
                <Card.Header className="bg-white">Customer</Card.Header>
                <Card.Body>
                  <div className="fw-semibold">{order.customer?.name || '—'}</div>
                  <div className="text-muted small">{order.customer?.email || ''}</div>
                  <div className="text-muted small">{order.customer?.phone || ''}</div>
                </Card.Body>
              </Card>
            </Col>

            <Col md={6}>
              <Card className="shadow-sm h-100">
                <Card.Header className="bg-white">Shipping address</Card.Header>
                <Card.Body className="small">
                  {order.shipping_address ? (
                    <ListGroup variant="flush">
                      <ListGroup.Item className="px-0 py-1">{order.shipping_address.full_name || order.shipping_address.name || ''}</ListGroup.Item>
                      <ListGroup.Item className="px-0 py-1">{order.shipping_address.phone || ''}</ListGroup.Item>
                      <ListGroup.Item className="px-0 py-1">
                        {order.shipping_address.street_address || ''}
                        {order.shipping_address.ward ? `, ${order.shipping_address.ward}` : ''}
                      </ListGroup.Item>
                      <ListGroup.Item className="px-0 py-1">
                        {[order.shipping_address.city, order.shipping_address.state, order.shipping_address.country]
                          .filter(Boolean)
                          .join(', ')}
                      </ListGroup.Item>
                    </ListGroup>
                  ) : (
                    <span className="text-muted">No address snapshot.</span>
                  )}
                </Card.Body>
              </Card>
            </Col>
          </Row>

          <Card className="shadow-sm mt-3">
            <Card.Header className="bg-white">Items</Card.Header>
            <Card.Body className="p-0">
              <div className="table-responsive">
                <Table hover className="mb-0 align-middle">
                  <thead>
                    <tr>
                      <th>Product</th>
                      <th>SKU</th>
                      <th className="text-end">Price</th>
                      <th className="text-center">Qty</th>
                      <th className="text-end">Total</th>
                    </tr>
                  </thead>
                  <tbody>
                    {(order.items || []).map((item) => (
                      <tr key={item.id}>
                        <td>{item.product_name}</td>
                        <td className="text-muted small">{item.product_sku}</td>
                        <td className="text-end">{formatPrice(item.unit_price)}</td>
                        <td className="text-center">{item.quantity}</td>
                        <td className="text-end fw-semibold">{formatPrice(item.line_total)}</td>
                      </tr>
                    ))}
                  </tbody>
                </Table>
              </div>
              <div className="d-flex flex-column align-items-end p-3 border-top">
                <div className="d-flex justify-content-between w-100 w-sm-auto" style={{ minWidth: 220 }}>
                  <span className="text-muted">Subtotal</span>
                  <span>{formatPrice(order.subtotal)}</span>
                </div>
                {Number(order.discount) > 0 && (
                  <div className="d-flex justify-content-between w-100 w-sm-auto" style={{ minWidth: 220 }}>
                    <span className="text-muted">Discount</span>
                    <span className="text-danger">-{formatPrice(order.discount)}</span>
                  </div>
                )}
                <div className="d-flex justify-content-between w-100 w-sm-auto" style={{ minWidth: 220 }}>
                  <span className="text-muted">Shipping</span>
                  <span>{formatPrice(order.shipping_fee)}</span>
                </div>
                <div className="d-flex justify-content-between w-100 w-sm-auto" style={{ minWidth: 220 }}>
                  <span className="text-muted">Tax</span>
                  <span>{formatPrice(order.tax)}</span>
                </div>
                <div className="d-flex justify-content-between w-100 w-sm-auto border-top pt-2 mt-1 fw-bold" style={{ minWidth: 220 }}>
                  <span>Total</span>
                  <span>{formatPrice(order.total)}</span>
                </div>
              </div>
            </Card.Body>
          </Card>

          <Card className="shadow-sm mt-3">
            <Card.Header className="bg-white">Payments</Card.Header>
            <Card.Body className="p-0">
              <div className="table-responsive">
                <Table hover className="mb-0 align-middle">
                  <thead>
                    <tr>
                      <th>Payment #</th>
                      <th>Method</th>
                      <th className="text-end">Amount</th>
                      <th>Status</th>
                      <th>Gateway</th>
                    </tr>
                  </thead>
                  <tbody>
                    {(order.payments || []).length === 0 ? (
                      <tr>
                        <td colSpan={5} className="text-muted">
                          No payment attempts.
                        </td>
                      </tr>
                    ) : (
                      (order.payments || []).map((payment) => (
                        <tr key={payment.id}>
                          <td>
                            <div>{payment.payment_number}</div>
                            {payment.gateway_transaction_id && (
                              <div className="text-muted small">Txn: {payment.gateway_transaction_id}</div>
                            )}
                          </td>
                          <td>{payment.payment_method_label || payment.payment_method}</td>
                          <td className="text-end">{formatPrice(payment.amount)}</td>
                          <td>
                            <OrderStatusBadge status={payment.payment_status} type="payment" />
                          </td>
                          <td className="text-muted small">{payment.gateway || '—'}</td>
                        </tr>
                      ))
                    )}
                  </tbody>
                </Table>
              </div>
            </Card.Body>
          </Card>

          <Card className="shadow-sm mt-3">
            <Card.Header className="bg-white">Order timeline</Card.Header>
            <Card.Body>
              {(order.status_history || []).length === 0 ? (
                <span className="text-muted">No timeline events yet.</span>
              ) : (
                <ListGroup variant="flush">
                  {(order.status_history || []).map((event) => (
                    <ListGroup.Item key={event.id} className="px-0">
                      <div className="d-flex flex-wrap align-items-center gap-2">
                        <Badge bg="secondary" className="text-capitalize">{event.old_status}</Badge>
                        <span>→</span>
                        <Badge bg="success" className="text-capitalize">{event.new_status}</Badge>
                        <span className="text-muted small ms-auto">{formatDate(event.created_at)}</span>
                      </div>
                      {event.note && <div className="text-muted small mt-1">“{event.note}”</div>}
                      {event.admin?.name && <div className="text-muted small">by {event.admin.name}</div>}
                    </ListGroup.Item>
                  ))}
                </ListGroup>
              )}
            </Card.Body>
          </Card>
        </Col>

        <Col lg={4}>
          <Card className="shadow-sm">
            <Card.Header className="bg-white">Order actions</Card.Header>
            <Card.Body>
              {allowedNext.length === 0 ? (
                <Alert variant="light" className="mb-0">
                  No further status changes are available for this order.
                </Alert>
              ) : (
                <Form>
                  <Form.Group className="mb-3">
                    <Form.Label className="small">Advance to</Form.Label>
                    <Form.Select value={nextStatus} onChange={(e) => setNextStatus(e.target.value)}>
                      <option value="">Select next status...</option>
                      {allowedNext.map((s) => (
                        <option key={s} value={s}>
                          {s.charAt(0).toUpperCase() + s.slice(1)}
                        </option>
                      ))}
                    </Form.Select>
                  </Form.Group>
                  <Form.Group className="mb-3">
                    <Form.Label className="small">Note (optional)</Form.Label>
                    <Form.Control
                      as="textarea"
                      rows={2}
                      placeholder="Internal note for the timeline..."
                      value={statusNote}
                      onChange={(e) => setStatusNote(e.target.value)}
                      maxLength={500}
                    />
                  </Form.Group>
                  <Button
                    variant="success"
                    className="w-100"
                    disabled={!nextStatus || statusBusy}
                    onClick={applyStatus}
                  >
                    {statusBusy ? 'Updating...' : 'Update status'}
                  </Button>
                </Form>
              )}

              {canCancel && (
                <div className="border-top mt-3 pt-3">
                  <Button variant="outline-danger" className="w-100" onClick={() => setShowCancel(true)}>
                    Cancel order
                  </Button>
                  <p className="text-muted small mt-2 mb-0">
                    Stock will be restored and pending payments retried as cancelled.
                  </p>
                </div>
              )}
            </Card.Body>
          </Card>

          <Card className="shadow-sm mt-3">
            <Card.Header className="bg-white">Admin notes</Card.Header>
            <Card.Body>
              {(order.admin_notes || []).length === 0 ? (
                <p className="text-muted small">No internal notes yet.</p>
              ) : (
                <ListGroup variant="flush" className="mb-3">
                  {(order.admin_notes || []).map((note) => (
                    <ListGroup.Item key={note.id} className="px-0">
                      <div>{note.note}</div>
                      <div className="text-muted small">
                        {note.admin?.name || 'Admin'} · {formatDate(note.created_at)}
                      </div>
                    </ListGroup.Item>
                  ))}
                </ListGroup>
              )}
              <Form.Group className="mb-2">
                <Form.Control
                  as="textarea"
                  rows={2}
                  placeholder="Add an internal note..."
                  value={noteText}
                  onChange={(e) => setNoteText(e.target.value)}
                  maxLength={1000}
                />
              </Form.Group>
              <Button
                variant="outline-success"
                size="sm"
                className="w-100"
                disabled={!noteText.trim() || noteBusy}
                onClick={addNote}
              >
                {noteBusy ? 'Adding...' : 'Add note'}
              </Button>
            </Card.Body>
          </Card>
        </Col>
      </Row>

      <ConfirmDialog
        show={showCancel}
        title="Cancel this order?"
        message="This will restore reserved stock and cancel pending payment attempts. This action cannot be undone."
        confirmLabel="Yes, cancel order"
        busy={cancelBusy}
        onCancel={() => {
          setShowCancel(false);
          setCancelNote('');
        }}
        onConfirm={confirmCancel}
      />
    </div>
  );
}