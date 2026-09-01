import { Alert } from 'react-bootstrap';
import { ShieldIcon } from '../../assets/icons';

/**
 * Card flow: the backend hands back a short-lived signed URL to ABA PayWay's
 * hosted checkout page, embedded in an iframe. Card data is entered on the
 * bank's page — this store never sees or stores it.
 */
export default function CardPayment({ payment }) {
  return (
    <>
      <Alert variant="info" className="mb-3">
        <div className="d-flex gap-2 align-items-start">
          <ShieldIcon size={20} className="flex-shrink-0 mt-1" />
          <div>
            <p className="mb-1">
              Complete your card payment on ABA PayWay’s <strong>secure hosted page</strong>.
            </p>
            <ul className="mb-0 small">
              <li>Your card details are entered on the bank’s page and never reach this store.</li>
              <li>Keep this page open — the payment is confirmed automatically.</li>
            </ul>
          </div>
        </div>
      </Alert>

      <div className="payment-iframe-wrap">
        {payment?.checkout_url ? (
          <iframe
            title="ABA PayWay secure card payment"
            src={payment.checkout_url}
            className="payment-iframe"
            allow="payment"
          />
        ) : (
          <p className="text-muted small mb-0">The payment page is being prepared. Please wait…</p>
        )}
      </div>
    </>
  );
}