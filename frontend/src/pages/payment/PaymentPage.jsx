import { useCallback, useEffect, useMemo, useState } from 'react';
import { Link, useNavigate, useParams, useSearchParams } from 'react-router-dom';
import { Alert, Button, Card, Col, Container, Row } from 'react-bootstrap';
import AbaPayPayment from '../../components/payment/AbaPayPayment';
import CardPayment from '../../components/payment/CardPayment';
import KhqrPayment from '../../components/payment/KhqrPayment';
import OrderStatusBadge from '../../components/orders/OrderStatusBadge';
import PaymentMethodSelector from '../../components/payment/PaymentMethodSelector';
import PaymentSummary from '../../components/payment/PaymentSummary';
import PaymentTimer from '../../components/payment/PaymentTimer';
import LoadingSpinner from '../../components/common/LoadingSpinner';
import { orderService } from '../../services/orderService';
import { paymentService } from '../../services/paymentService';
import { useToast } from '../../context/ToastContext';
import { usePaymentStatus } from '../../hooks/usePaymentStatus';
import usePageTitle from '../../hooks/usePageTitle';
import { getErrorMessage } from '../../utils/error';
import { ShieldIcon } from '../../assets/icons';

const POLL_INTERVAL_MS = 4000;

export default function PaymentPage() {
  usePageTitle('Secure Checkout');
  const { orderNumber } = useParams();
  const navigate = useNavigate();
  const [searchParams] = useSearchParams();
  const { showToast } = useToast();

  const { status, error, pollOnce, startPolling, stopPolling } = usePaymentStatus(orderNumber);

  const [booting, setBooting] = useState(true);
  const [methods, setMethods] = useState([]);
  const [selectedMethod, setSelectedMethod] = useState('');
  const [orderTotal, setOrderTotal] = useState(null);
  const [creating, setCreating] = useState(false);
  const [refreshing, setRefreshing] = useState(false);
  const [activePayment, setActivePayment] = useState(null);

  // Load the method list, order total and current status once.
  useEffect(() => {
    let active = true;

    (async () => {
      try {
        const [list, order] = await Promise.all([
          paymentService.methods().catch(() => []),
          orderService.get(orderNumber).catch(() => null),
        ]);
        if (active) {
          setMethods(list || []);
          setOrderTotal(order?.total ?? null);
        }
      } catch {
        // individual failures above are swallowed; status surface still works.
      }

      const data = await pollOnce(true);
      if (!active) return;

      if (data) {
        if (data.order_payment_status === 'paid' || data.payment?.payment_status === 'paid') {
          navigate(`/payment/success/${orderNumber}`, { replace: true });
        } else if (['cancelled', 'refunded'].includes(data.order_status)) {
          navigate(`/payment/failed/${orderNumber}`, { replace: true });
        } else if (data.payment?.payment_status === 'pending') {
          setActivePayment(data.payment);
        }
      }

      if (active) setBooting(false);
    })();

    return () => {
      active = false;
    };
  }, [orderNumber, navigate, pollOnce]);

  // While a pending attempt exists, keep polling the backend.
  useEffect(() => {
    if (!activePayment || activePayment.payment_status !== 'pending') return undefined;
    startPolling(POLL_INTERVAL_MS);
    return () => stopPolling();
  }, [activePayment, startPolling, stopPolling]);

  // Settle the attempt based on the latest polled status.
  useEffect(() => {
    if (!status) return undefined;

    const paid = status.order_payment_status === 'paid' || status.payment?.payment_status === 'paid';
    if (paid) {
      stopPolling();
      navigate(`/payment/success/${orderNumber}`, { replace: true });
      return undefined;
    }

    if (['cancelled', 'refunded'].includes(status.order_status)) {
      stopPolling();
      navigate(`/payment/failed/${orderNumber}`, { replace: true });
      return undefined;
    }

    const wasPending = activePayment?.payment_status === 'pending';
    const attemptStatus = status.payment?.payment_status;

    if (wasPending && attemptStatus && attemptStatus !== 'pending' && attemptStatus !== 'paid') {
      stopPolling();
      if (attemptStatus === 'expired') {
        navigate(`/payment/expired/${orderNumber}`, { replace: true });
      } else {
        navigate(`/payment/failed/${orderNumber}`, { replace: true });
      }
    }

    return undefined;
  }, [status, activePayment, orderNumber, navigate, stopPolling]);

  const handleChooseMethod = (method) => {
    setSelectedMethod(method);
  };

  const handleCreate = useCallback(async () => {
    if (!selectedMethod) {
      showToast('Please choose a payment method.', 'warning');
      return;
    }
    setCreating(true);
    try {
      const payment = await paymentService.create(orderNumber, selectedMethod);
      setActivePayment(payment);
      setSelectedMethod('');
    } catch (err) {
      showToast(getErrorMessage(err), 'danger');
    } finally {
      setCreating(false);
    }
  }, [selectedMethod, orderNumber, showToast]);

  const handleManualCheck = useCallback(async () => {
    if (!activePayment) return;
    setRefreshing(true);
    try {
      await paymentService.refresh(orderNumber, activePayment.id);
      await pollOnce(true);
    } catch (err) {
      showToast(getErrorMessage(err), 'danger');
    } finally {
      setRefreshing(false);
    }
  }, [activePayment, orderNumber, pollOnce, showToast]);

  const handleExpire = useCallback(() => {
    stopPolling();
    navigate(`/payment/expired/${orderNumber}`, { replace: true });
  }, [orderNumber, navigate, stopPolling]);

  const methodContent = useMemo(() => {
    switch (activePayment?.payment_method) {
      case 'aba_pay':
        return <AbaPayPayment payment={activePayment} />;
      case 'khqr':
        return <KhqrPayment payment={activePayment} />;
      case 'card':
        return <CardPayment payment={activePayment} />;
      default:
        return null;
    }
  }, [activePayment]);

  if (booting) {
    return (
      <Container className="py-5">
        <LoadingSpinner label="Preparing secure payment..." />
      </Container>
    );
  }

  const wasCancelled = searchParams.get('status') === 'cancelled';

  return (
    <Container className="py-4">
      <h1 className="h3 mb-1 d-flex align-items-center gap-2">
        <ShieldIcon size={24} className="text-success" />
        Secure Checkout
      </h1>
      <p className="text-muted mb-4">
        Pay for order{' '}
        <Link to={`/orders/${orderNumber}`} className="fw-semibold text-reset">
          {orderNumber}
        </Link>{' '}
        securely through ABA PayWay.
      </p>

      {wasCancelled && (
        <Alert variant="warning">Your previous payment attempt was cancelled. You can try again below.</Alert>
      )}
      {error && <Alert variant="danger">{error}</Alert>}

      <Row className="g-4">
        <Col lg={8}>
          {!activePayment ? (
            <Card className="shadow-sm">
              <Card.Body>
                <h2 className="h5 mb-1">Choose a payment method</h2>
                <p className="text-muted small mb-3">
                  Prices and totals are always calculated and verified on our server.
                </p>

                <PaymentMethodSelector
                  methods={methods}
                  value={selectedMethod}
                  onChange={handleChooseMethod}
                  disabled={creating}
                />

                {methods.length === 0 && !error && (
                  <Alert variant="warning" className="mb-0">
                    No online payment methods are available right now. Please check back shortly.
                  </Alert>
                )}

                <Button
                  variant="success"
                  size="lg"
                  className="mt-4"
                  onClick={handleCreate}
                  disabled={creating || methods.length === 0}
                >
                  {creating ? 'Starting payment…' : 'Continue to Payment'}
                </Button>
              </Card.Body>
            </Card>
          ) : (
            <Card className="shadow-sm">
              <Card.Body>
                <div className="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                  <h2 className="h5 mb-0 d-flex align-items-center gap-2">
                    Complete your payment
                    <OrderStatusBadge
                      status={status?.payment?.payment_status || activePayment.payment_status}
                      type="payment"
                    />
                  </h2>
                  <PaymentTimer expiresAt={activePayment.expires_at} onExpire={handleExpire} />
                </div>

                {methodContent}

                <div className="d-flex flex-wrap align-items-center justify-content-between gap-2 mt-3 pt-3 border-top">
                  <span className="text-muted small">
                    We check for confirmation automatically every few seconds.
                  </span>
                  <Button
                    variant="outline-success"
                    size="sm"
                    onClick={handleManualCheck}
                    disabled={refreshing}
                  >
                    {refreshing ? 'Checking…' : 'Check payment status'}
                  </Button>
                </div>
              </Card.Body>
            </Card>
          )}
        </Col>

        <Col lg={4}>
          <PaymentSummary
            orderNumber={orderNumber}
            amount={orderTotal || activePayment?.amount || status?.payment?.amount}
            payment={activePayment || status?.payment}
          />

          <Card className="shadow-sm mt-3">
            <Card.Body>
              <h2 className="h6 text-uppercase text-muted mb-3">Secure by design</h2>
              <ul className="small mb-0 ps-3">
                <li>Card details are entered on ABA PayWay’s secure pages, never on this store.</li>
                <li>Order totals are always calculated and verified on the server.</li>
                <li>Payments are confirmed only after a signature-verified callback.</li>
              </ul>
            </Card.Body>
          </Card>
        </Col>
      </Row>
    </Container>
  );
}