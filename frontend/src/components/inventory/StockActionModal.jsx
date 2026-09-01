import { useEffect, useState } from 'react';
import { Button, Form, Modal, Alert, InputGroup, Spinner } from 'react-bootstrap';
import { adminInventoryService } from '../../services/adminInventoryService';
import { normalizeError } from '../../services/api';

const MODE_META = {
  add: {
    title: 'Add stock',
    submitLabel: 'Add stock',
    help: 'Adds inbound stock (purchase) and records a purchase ledger entry.',
  },
  remove: {
    title: 'Remove stock',
    submitLabel: 'Remove stock',
    help: 'Records an adjustment. Stock can never go below zero.',
  },
  adjust: {
    title: 'Adjust stock',
    submitLabel: 'Save adjustment',
    help: 'Sets the stock to an absolute target and records the difference.',
  },
};

const REMOVE_REASONS = [
  { value: 'damage', label: 'Damage' },
  { value: 'loss', label: 'Loss' },
  { value: 'expired', label: 'Expired' },
  { value: 'other', label: 'Other' },
];

const ADJUST_REASONS = [
  { value: 'physical_count', label: 'Physical count' },
  { value: 'damaged', label: 'Damaged' },
  { value: 'expired', label: 'Expired' },
  { value: 'data_correction', label: 'Data correction' },
  { value: 'other', label: 'Other' },
];

/**
 * Modal that performs a single stock mutation (add / remove / adjust) against
 * the per-product inventory endpoints. Requires a reason for destructive
 * changes; the resulting stock is previewed before submitting.
 */
export default function StockActionModal({ show, mode, product, onHide, onSaved }) {
  const meta = MODE_META[mode] || MODE_META.add;
  const current = Number(product?.stock_quantity) || 0;

  const [quantity, setQuantity] = useState(1);
  const [reason, setReason] = useState('');
  const [reasonNote, setReasonNote] = useState('');
  const [error, setError] = useState('');
  const [fieldErrors, setFieldErrors] = useState({});
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    if (!show) return;
    setQuantity(mode === 'adjust' ? current : 1);
    setReason(mode === 'remove' ? REMOVE_REASONS[0].value : ADJUST_REASONS[0].value);
    setReasonNote('');
    setError('');
    setFieldErrors({});
    setBusy(false);
  }, [show, mode, current]);

  const qtyNum = Number(quantity);
  const qtyInvalid = !Number.isInteger(qtyNum) || qtyNum < (mode === 'adjust' ? 0 : 1);
  const overStock = mode === 'remove' && qtyNum > current;

  const preview =
    mode === 'add' ? current + qtyNum : mode === 'remove' ? current - qtyNum : qtyNum;
  const previewText = Number.isNaN(preview) ? '—' : Math.max(preview, 0);

  const handleSubmit = async (e) => {
    e.preventDefault();
    if (qtyInvalid) return;
    if (mode !== 'add' && reasonNote && !reason) return;
    if (mode === 'remove' && overStock) return;

    const reasonValue =
      mode === 'add' ? reasonNote : reason === 'other' && reasonNote ? reasonNote : reason;

    setBusy(true);
    setError('');
    setFieldErrors({});
    try {
      let result;
      if (mode === 'add') {
        result = await adminInventoryService.addStock(product.id, qtyNum, reasonValue);
      } else if (mode === 'remove') {
        result = await adminInventoryService.removeStock(product.id, qtyNum, reasonValue);
      } else {
        result = await adminInventoryService.adjustStock(product.id, qtyNum, reasonValue);
      }
      onSaved?.(result);
      onHide();
    } catch (err) {
      const normalized = normalizeError(err);
      setError(normalized.message);
      setFieldErrors(normalized.errors || {});
    } finally {
      setBusy(false);
    }
  };

  return (
    <Modal show={show} onHide={onHide} centered>
      <Modal.Header closeButton>
        <Modal.Title>{meta.title}</Modal.Title>
      </Modal.Header>
      <Form onSubmit={handleSubmit}>
        <Modal.Body>
          <p className="text-muted small mb-3">{meta.help}</p>
          <InputGroup className="mb-3">
            <InputGroup.Text>{product?.unit || 'units'}</InputGroup.Text>
            <Form.Control
              type="number"
              inputMode="numeric"
              min={mode === 'adjust' ? 0 : 1}
              max={mode === 'remove' ? current : undefined}
              value={quantity}
              onChange={(e) => setQuantity(e.target.value)}
              isInvalid={qtyInvalid || overStock}
              aria-label="Quantity"
              required
            />
            {overStock && <Form.Control.Feedback type="invalid">Cannot remove more than {current}.</Form.Control.Feedback>}
          </InputGroup>

          {mode === 'remove' && (
            <p className={`small mb-2 ${overStock ? 'text-danger' : ''}`}>
              Current stock: {current}. You are removing {Number.isInteger(qtyNum) ? qtyNum : '—'}.
            </p>
          )}
          {mode === 'adjust' && (
            <p className="small text-muted mb-2">
              Current stock: {current}. The ledger records the difference, not the target.
            </p>
          )}
          {mode !== 'add' && (
            <div className="small mb-2 text-muted">Resulting stock: <strong>{previewText}</strong></div>
          )}

          {mode !== 'add' ? (
            <>
              <Form.Label className="small fw-semibold text-muted">Reason</Form.Label>
              <Form.Select
                value={reason}
                onChange={(e) => setReason(e.target.value)}
                className="mb-2"
                required
              >
                {(mode === 'remove' ? REMOVE_REASONS : ADJUST_REASONS).map((r) => (
                  <option key={r.value} value={r.value}>
                    {r.label}
                  </option>
                ))}
              </Form.Select>
              <Form.Control
                type="text"
                placeholder="Add a note (optional)"
                value={reasonNote}
                onChange={(e) => setReasonNote(e.target.value)}
                maxLength={255}
              />
            </>
          ) : (
            <Form.Control
              type="text"
              placeholder="Reason / note (optional)"
              value={reasonNote}
              onChange={(e) => setReasonNote(e.target.value)}
              maxLength={255}
            />
          )}

          {error && <Alert variant="danger" className="mt-3 mb-0">{error}</Alert>}
          {fieldErrors && Object.keys(fieldErrors).length > 0 && (
            <Alert variant="danger" className="mt-3 mb-0">
              {Object.values(fieldErrors).flat().join(' ')}
            </Alert>
          )}
        </Modal.Body>
        <Modal.Footer>
          <Button variant="outline-secondary" onClick={onHide} disabled={busy}>
            Cancel
          </Button>
          <Button type="submit" variant="success" disabled={busy || qtyInvalid || overStock}>
            {busy ? <Spinner animation="border" size="sm" /> : meta.submitLabel}
          </Button>
        </Modal.Footer>
      </Form>
    </Modal>
  );
}