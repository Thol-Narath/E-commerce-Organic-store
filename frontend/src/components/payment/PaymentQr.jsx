import { Button } from 'react-bootstrap';
import { QRCodeSVG } from 'qrcode.react';
import { RefreshIcon } from '../../assets/icons';

/**
 * Renders the backend-supplied KHQR payload as a scannable QR inside a clean
 * white container with enough quiet space. The QR value always comes from the
 * Laravel backend (gateway-generated) — it is never composed in the browser.
 *
 * When no QR payload exists (e.g. generation failed before saving), a clear
 * error state is shown instead of a broken image, with an optional retry.
 */
export default function PaymentQr({ value, caption, onRetry }) {
  if (!value) {
    return (
      <div className="payment-qr-error mx-auto">
        <p className="payment-qr-error-title mb-1">Unable to generate payment QR</p>
        <p className="payment-qr-error-text mb-3">
          The payment network could not generate a QR code for this order. Please try again.
        </p>
        {onRetry ? (
          <Button variant="success" onClick={onRetry}>
            <RefreshIcon size={18} className="me-2" />
            Try Again
          </Button>
        ) : null}
      </div>
    );
  }

  return (
    <div className="payment-qr-block">
      <div className="payment-qr mx-auto">
        <QRCodeSVG value={value} size={248} level="M" marginSize={4} />
      </div>
      <p className="payment-qr-scan-label mt-3 mb-1">Scan to Pay</p>
      {caption ? <p className="payment-qr-caption mb-0">{caption}</p> : null}
    </div>
  );
}