import { Alert, Button } from 'react-bootstrap';
import PaymentQr from './PaymentQr';
import { WalletIcon } from '../../assets/icons';

/**
 * Bakong KHQR flow: scan with the Bakong app or any KHQR-enabled banking app.
 * QR string is generated locally by the backend (no PayWay gateway involved).
 */
export default function BakongPayment({ payment }) {
  return (
    <>
      <Alert variant="success" className="mb-3">
        <p className="mb-1">
          Pay with <strong>Bakong</strong> or any <strong>KHQR-enabled banking app</strong>.
        </p>
        <ul className="mb-0 small">
          <li>Open the <strong>Bakong app</strong> or your banking app and choose <strong>Scan to Pay</strong>.</li>
          <li>Scan the QR code below and confirm the payment.</li>
          <li>Keep this page open — the payment is confirmed automatically.</li>
        </ul>
      </Alert>

      <div className="text-center">
        <PaymentQr value={payment?.qr_string} caption="Scan with Bakong or banking app" />

        {payment?.deeplink && (
          <Button href={payment.deeplink} variant="success" size="lg" className="mt-2">
            <WalletIcon size={20} className="me-2" />
            Pay with Bakong
          </Button>
        )}
      </div>
    </>
  );
}
