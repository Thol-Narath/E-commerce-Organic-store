import { Navigate, useLocation, useNavigate } from 'react-router-dom';
import { Button, Card, Col, Container, Row } from 'react-bootstrap';
import usePageTitle from '../../hooks/usePageTitle';
import { formatPrice, formatDate } from '../../utils/format';
import { CheckCircleIcon, TruckIcon } from '../../assets/icons';

/**
 * Order confirmation shown right after a successful checkout. The created
 * order travels through router state; a hard refresh (no state) bounces to
 * the order history page.
 */
export default function CheckoutSuccessPage() {
  usePageTitle('Order Confirmed');
  const location = useLocation();
  const navigate = useNavigate();

  const order = location.state?.order;

  if (!order) {
    return <Navigate to="/account/orders" replace />;
  }

  return (
    <Container className="py-5">
      <Row className="justify-content-center">
        <Col lg={7}>
          <Card className="shadow-sm text-center">
            <Card.Body className="p-4 p-md-5">
              <CheckCircleIcon size={64} className="text-success mb-3" />

              <h1 className="h4 mb-2">Order Confirmed!</h1>
              <p className="text-muted mb-4">
                Thank you for shopping with us. Your order has been placed and you will receive a
                confirmation shortly.
              </p>

              <div className="d-flex flex-wrap justify-content-center gap-3 mb-4">
                <div className="order-fact">
                  <span className="text-muted d-block small">Order number</span>
                  <strong>{order.order_number}</strong>
                </div>
                <div className="order-fact">
                  <span className="text-muted d-block small">Placed on</span>
                  <strong>{formatDate(order.placed_at || order.created_at)}</strong>
                </div>
                <div className="order-fact">
                  <span className="text-muted d-block small">Total</span>
                  <strong>{formatPrice(order.total)}</strong>
                </div>
                <div className="order-fact">
                  <span className="text-muted d-block small">Status</span>
                  <span className="text-capitalize fw-semibold">{order.status}</span>
                </div>
              </div>

              <div className="d-flex flex-column flex-sm-row justify-content-center gap-2">
                {order.payment_status !== 'paid' && order.status !== 'cancelled' && (
                  <Button variant="success" onClick={() => navigate(`/payment/${order.order_number}`)}>
                    Complete Payment
                  </Button>
                )}
                <Button variant={order.payment_status !== 'paid' ? 'outline-success' : 'success'} onClick={() => navigate(`/orders/${order.order_number}`)}>
                  <TruckIcon size={16} className="me-1" />
                  Track Order
                </Button>
                <Button variant="outline-success" onClick={() => navigate('/shop')}>
                  Continue Shopping
                </Button>
              </div>
            </Card.Body>
          </Card>
        </Col>
      </Row>
    </Container>
  );
}