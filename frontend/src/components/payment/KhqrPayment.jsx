import { Alert } from 'react-bootstrap';
import PaymentQr from './PaymentQr';

/**
 * KHQR flow: universal QR readable by any KHQR-enabled banking app.
 */
export default function KhqrPayment({ payment }) {
  return (
    <>
      <Alert variant="info" className="mb-3">
        <p className="mb-1">
          Scan with any <strong>KHQR-enabled banking app</strong> (ABA, Bakong, ACLEDA, …).
        </p>
        <ul className="mb-0 small">
          <li>Open your banking app and choose <strong>Scan to Pay</strong>.</li>
          <li>Scan the QR code and approve the payment.</li>
          <li>Keep this page open — the payment is confirmed automatically.</li>
        </ul>
      </Alert>

      <div className="text-center">
        <PaymentQr value={payment?.qr_string} caption="Scan with your banking app" />
      </div>
    </>
  );
}