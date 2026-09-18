import { Alert, Button } from 'react-bootstrap';
import PaymentQr from './PaymentQr';
import { WalletIcon } from '../../assets/icons';

/**
 * Bakong KHQR flow: scan with the Bakong app or any KHQR-enabled banking app.
 * QR string is generated locally by the backend (no PayWay gateway involved).
 */
export default function BakongPayment({ payment, onRetry }) {
  return (
    <>
      <Alert variant="success" className="mb-3">
        <p className="mb-1">
          Open the <strong>Bakong app</strong> or your banking app and choose <strong>Scan to Pay</strong>.
        </p>
        <ul className="mb-0 small">
          <li>Scan the KHQR below and confirm the amount.</li>
          <li>Approve the payment inside the app.</li>
          <li>Keep this page open — the payment is confirmed automatically.</li>
        </ul>
      </Alert>

      <PaymentQr
        value={payment?.qr_string}
        caption="Open your banking app and scan this KHQR"
        onRetry={onRetry}
        currency={payment?.currency}
      />

      {payment?.deeplink && (
        <Button href={payment.deeplink} variant="success" size="lg" className="mt-3">
          <WalletIcon size={20} className="me-2" />
          Pay with Bakong
        </Button>
      )}

      <p className="payment-apps mb-0 mt-3">ABA &bull; Bakong &bull; ACLEDA &bull; KHQR</p>
    </>
  );
}