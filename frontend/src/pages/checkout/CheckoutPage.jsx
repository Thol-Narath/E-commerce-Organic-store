import { useEffect, useState } from 'react';
import { Navigate, useNavigate } from 'react-router-dom';
import { Alert, Button, Card, Col, Container, Row } from 'react-bootstrap';
import AddressSelector from '../../components/address/AddressSelector';
import OrderSummary from '../../components/checkout/OrderSummary';
import LoadingSpinner from '../../components/common/LoadingSpinner';
import { addressService } from '../../services/addressService';
import { checkoutService } from '../../services/checkoutService';
import { settingsService } from '../../services/settingsService';
import { useCart } from '../../context/CartContext';
import { useToast } from '../../context/ToastContext';
import usePageTitle from '../../hooks/usePageTitle';
import { getErrorMessage } from '../../utils/error';
import { TruckIcon } from '../../assets/icons';

const EMPTY_CART_PAYLOAD = { id: null, items: [], subtotal: '0.00', total_items: 0 };

export default function CheckoutPage() {
  usePageTitle('Checkout');
  const navigate = useNavigate();
  const { cart, loading: cartLoading, replaceCart } = useCart();
  const { showToast } = useToast();

  const [addresses, setAddresses] = useState([]);
  const [selectedAddressId, setSelectedAddressId] = useState(null);
  const [shippingFee, setShippingFee] = useState('0.00');
  const [preparing, setPreparing] = useState(true);
  const [loadError, setLoadError] = useState('');
  const [placing, setPlacing] = useState(false);

  useEffect(() => {
    let active = true;
    (async () => {
      try {
        const [addr, settings] = await Promise.all([
          addressService.list(),
          settingsService.publicSettings().catch(() => null),
        ]);
        if (!active) return;
        setAddresses(addr);
        setShippingFee(settings?.shipping?.flat_rate ?? '0.00');
        const defaultAddress = addr.find((a) => a.is_default) || addr[0];
        if (defaultAddress) setSelectedAddressId(defaultAddress.id);
      } catch (err) {
        if (active) setLoadError(getErrorMessage(err));
      } finally {
        if (active) setPreparing(false);
      }
    })();
    return () => {
      active = false;
    };
  }, []);

  if (cartLoading || preparing) {
    return (
      <Container className="py-5">
        <LoadingSpinner label="Preparing checkout..." />
      </Container>
    );
  }

  const items = cart.items || [];
  const unavailableItems = items.filter((item) => !item.available);

  if (items.length === 0) {
    return <Navigate to="/cart" replace />;
  }

  const handleCreateAddress = async (payload) => {
    const created = await addressService.create(payload);
    const next = [...addresses, created];
    setAddresses(next);
    if (selectedAddressId === null) setSelectedAddressId(created.id);
    if (created.is_default) {
      setAddresses(
        next.map((a) => ({ ...a, is_default: a.id === created.id }))
      );
    }
    return created;
  };

  const handlePlaceOrder = async () => {
    if (!selectedAddressId) {
      showToast('Please select a shipping address.', 'warning');
      return;
    }
    setPlacing(true);
    try {
      const order = await checkoutService.placeOrder(selectedAddressId);
      replaceCart(EMPTY_CART_PAYLOAD);
      navigate(`/payment/${order.order_number}`, { replace: true });
    } catch (err) {
      showToast(getErrorMessage(err), 'danger');
    } finally {
      setPlacing(false);
    }
  };

  return (
    <Container className="py-4">
      <h1 className="h3 mb-4 d-flex align-items-center gap-2">
        <TruckIcon size={26} className="text-success" />
        Checkout
      </h1>

      {unavailableItems.length > 0 && (
        <Alert variant="warning">
          Some items in your cart are no longer available. Remove them before continuing to checkout.
        </Alert>
      )}

      {loadError && <Alert variant="danger">{loadError}</Alert>}

      <Row className="g-4">
        <Col lg={8}>
          <Card className="shadow-sm mb-4">
            <Card.Body>
              <h2 className="h5 mb-3">1. Shipping Address</h2>
              <AddressSelector
                addresses={addresses}
                selectedAddressId={selectedAddressId}
                onSelect={(address) => setSelectedAddressId(address.id)}
                onCreate={handleCreateAddress}
                disabled={placing || unavailableItems.length > 0}
              />
            </Card.Body>
          </Card>
        </Col>

        <Col lg={4}>
          <OrderSummary
            items={items}
            subtotal={cart.subtotal}
            shippingFee={shippingFee}
            total={Number(cart.subtotal) + Number(shippingFee)}
          />

          <Button
            variant="success"
            size="lg"
            className="w-100 mt-3"
            onClick={handlePlaceOrder}
            disabled={placing || unavailableItems.length > 0}
          >
            {placing ? 'Placing order...' : 'Place Order'}
          </Button>
          <p className="text-muted small mt-2 mb-0 text-center">
            Shipping fee applies to all orders.
          </p>
        </Col>
      </Row>
    </Container>
  );
}