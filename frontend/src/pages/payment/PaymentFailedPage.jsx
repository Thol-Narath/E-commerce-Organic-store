import { useEffect, useState } from 'react';
import { Button, Card, Container } from 'react-bootstrap';
import { Link, useParams } from 'react-router-dom';
import LoadingSpinner from '../../components/common/LoadingSpinner';
import PaymentSettled from '../../components/payment/PaymentSettled';
import { usePaymentStatus } from '../../hooks/usePaymentStatus';
import usePageTitle from '../../hooks/usePageTitle';
import { XCircleIcon } from '../../assets/icons';

export default function PaymentFailedPage() {
  usePageTitle('Payment Failed');
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
        <LoadingSpinner label="Checking payment status..." />
      </Container>
    );
  }

  const paid = status?.order_payment_status === 'paid' || status?.payment?.payment_status === 'paid';

  if (paid) {
    return (
      <Container className="py-5" style={{ maxWidth: 640 }}>
        <PaymentSettled orderNumber={orderNumber} payment={status.payment} />
      </Container>
    );
  }

  return (
    <Container className="py-5" style={{ maxWidth: 640 }}>
      <Card className="shadow-sm text-center">
        <Card.Body className="p-4 p-md-5">
          <XCircleIcon size={56} className="text-danger mb-3" />
          <h1 className="h4 mb-2">Payment not completed</h1>
          <p className="text-muted mb-4">
            We could not confirm your payment for order {orderNumber}. If any money was taken it will be refunded
            automatically. You can try another payment method below.
          </p>
          <div className="d-flex flex-column flex-sm-row gap-2 justify-content-center">
            <Button as={Link} to={`/payment/${orderNumber}`} variant="success">
              Try Another Payment
            </Button>
            <Button as={Link} to="/shop" variant="outline-success">
              Continue Shopping
            </Button>
          </div>
        </Card.Body>
      </Card>
    </Container>
  );
}