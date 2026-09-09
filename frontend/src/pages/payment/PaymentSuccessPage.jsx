import { useEffect, useState } from 'react';
import { Button, Card, Container } from 'react-bootstrap';
import { Link, useParams } from 'react-router-dom';
import LoadingSpinner from '../../components/common/LoadingSpinner';
import PaymentSettled from '../../components/payment/PaymentSettled';
import { usePaymentStatus } from '../../hooks/usePaymentStatus';
import usePageTitle from '../../hooks/usePageTitle';

export default function PaymentSuccessPage() {
  usePageTitle('Payment Confirmed');
  const { orderNumber } = useParams();
  const { status, pollOnce } = usePaymentStatus(orderNumber);
  const [loaded, setLoaded] = useState(false);

  useEffect(() => {
    let active = true;
    (async () => {
      await pollOnce(true);
      if (active) setLoaded(true);
    })();
    return () => {
      active = false;
    };
  }, [pollOnce]);

  if (!loaded) {
    return (
      <Container className="py-5" style={{ maxWidth: 640 }}>
        <LoadingSpinner label="Confirming your payment..." />
      </Container>
    );
  }

  const paid = status?.order_payment_status === 'paid' || status?.payment?.payment_status === 'paid';

  return (
    <Container className="py-5" style={{ maxWidth: 640 }}>
      {paid ? (
        <PaymentSettled orderNumber={orderNumber} payment={status.payment} />
      ) : (
        <Card className="shadow-sm text-center">
          <Card.Body className="p-4 p-md-5">
            <h1 className="h4 mb-2">Payment not confirmed yet</h1>
            <p className="text-muted mb-4">
              Order {orderNumber} hasn’t been marked as paid. If you completed your payment, it may still be
              pending confirmation — please try again in a moment.
            </p>
            <div className="d-flex flex-column flex-sm-row gap-2 justify-content-center">
              <Button as={Link} to={`/payment/${orderNumber}`} variant="success">
                Continue to Payment
              </Button>
              <Button as={Link} to="/account/orders" variant="outline-success">
                My Orders
              </Button>
            </div>
          </Card.Body>
        </Card>
      )}
    </Container>
  );
}