import { Alert, Button } from 'react-bootstrap';
import PaymentQr from './PaymentQr';
import { WalletIcon } from '../../assets/icons';

/**
 * ABA Pay flow: gateway returns a deeplink (and a matching QR string) that a
 * desktop app or phone can open to approve the payment.
 */
export default function AbaPayPayment({ payment }) {
  return (
    <>
      <Alert variant="info" className="mb-3">
        <p className="mb-1">
          Pay with the <strong>ABA Mobile</strong> app.
        </p>
        <ul className="mb-0 small">
          <li>Open <strong>ABA Mobile</strong> on your phone and tap <strong>Scan to Pay</strong>.</li>
          <li>Scan the QR code below, or tap the button to open the payment directly in the app.</li>
          <li>Keep this page open — the payment is confirmed automatically.</li>
        </ul>
      </Alert>

      <div className="text-center">
        <PaymentQr value={payment?.qr_string} caption="Scan with ABA Mobile" />

        {payment?.deeplink && (
          <Button href={payment.deeplink} variant="success" size="lg" className="mt-2">
            <WalletIcon size={20} className="me-2" />
            Pay with ABA Mobile
          </Button>
        )}
      </div>
    </>
  );
}