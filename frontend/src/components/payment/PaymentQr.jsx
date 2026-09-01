import { QRCodeSVG } from 'qrcode.react';

/**
 * The QR payload comes from the backend (gateway-generated), never composed
 * in the browser. It is only present while the attempt is pending.
 */
export default function PaymentQr({ value, caption }) {
  if (!value) return null;

  return (
    <div className="payment-qr mx-auto">
      <QRCodeSVG value={value} size={220} level="M" marginSize={2} />
      <p className="small text-muted my-2 mb-0">{caption}</p>
    </div>
  );
}