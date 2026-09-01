import { Col, Row } from 'react-bootstrap';
import { CardIcon, QrIcon, WalletIcon } from '../../assets/icons';

/**
 * PayWay methods this store exposes. The selector is read-only when the
 * backend has not enabled any method (methods.length === 0).
 */
const METHOD_META = {
  aba_pay: { Icon: QrIcon, hint: 'Scan with the ABA Mobile app, or open the payment directly in the app.' },
  khqr: { Icon: QrIcon, hint: 'Scan with any KHQR-enabled banking app.' },
  card: { Icon: CardIcon, hint: 'Complete with any card on ABA PayWay’s secure hosted page.' },
};

const FALLBACK_ICON = WalletIcon;

export default function PaymentMethodSelector({ methods = [], value, onChange, disabled = false }) {
  if (methods.length === 0) return null;

  return (
    <Row className="g-3">
      {methods.map((method) => {
        const source = METHOD_META[method.method] || {};
        const Icon = source.Icon || FALLBACK_ICON;
        const selected = value === method.method;

        return (
          <Col sm={12} md={4} key={method.method}>
            <button
              type="button"
              className={`payment-method-card ${selected ? 'selected' : ''}`}
              onClick={() => onChange?.(method.method)}
              disabled={disabled}
              aria-pressed={selected}
            >
              <span className="payment-method-radio" aria-hidden="true">
                <span className={selected ? 'inner' : ''} />
              </span>
              <Icon size={28} className="payment-method-icon" />
              <span className="payment-method-label">{method.label}</span>
              <span className="payment-method-hint">{source.hint || 'Secure online payment.'}</span>
            </button>
          </Col>
        );
      })}
    </Row>
  );
}