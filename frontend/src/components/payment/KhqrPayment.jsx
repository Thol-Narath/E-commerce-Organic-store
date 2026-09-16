import { Alert } from 'react-bootstrap';
import PaymentQr from './PaymentQr';

/**
 * KHQR flow: universal QR readable by any KHQR-enabled banking app.
 */
export default function KhqrPayment({ payment, onRetry }) {
  return (
    <>
      <Alert variant="info" className="mb-3">
        <p className="mb-1">
          Open your banking app and scan this <strong>KHQR</strong>.
        </p>
        <ul className="mb-0 small">
          <li>Choose <strong>Scan to Pay</strong> in your banking app.</li>
          <li>Scan the QR code below and approve the payment.</li>
          <li>Keep this page open — the payment is confirmed automatically.</li>
        </ul>
      </Alert>

      <PaymentQr
        value={payment?.qr_string}
        caption="Open your banking app and scan this KHQR"
        onRetry={onRetry}
      />

      <p className="payment-apps mb-0 mt-3">ABA &bull; Bakong &bull; ACLEDA &bull; KHQR</p>
    </>
  );
}