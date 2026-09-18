import { useEffect, useState } from 'react';
import { Navigate, useNavigate } from 'react-router-dom';
import { Alert, Button, Card, Col, Container, Form, Row } from 'react-bootstrap';
import AddressSelector from '../../components/address/AddressSelector';
import OrderSummary from '../../components/checkout/OrderSummary';
import LoadingSpinner from '../../components/common/LoadingSpinner';
import { addressService } from '../../services/addressService';
import { checkoutService } from '../../services/checkoutService';
import { settingsService } from '../../services/settingsService';
import { shippingService } from '../../services/shippingService';
import { useCart } from '../../context/CartContext';
import { useToast } from '../../context/ToastContext';
import usePageTitle from '../../hooks/usePageTitle';
import { getErrorMessage } from '../../utils/error';
import { TruckIcon, ClockIcon, CheckCircleIcon } from '../../assets/icons';
import { formatPrice } from '../../utils/format';

const EMPTY_CART_PAYLOAD = { id: null, items: [], subtotal: '0.00', total_items: 0 };

export default function CheckoutPage() {
  usePageTitle('Checkout');
  const navigate = useNavigate();
  const { cart, loading: cartLoading, replaceCart } = useCart();
  const { showToast } = useToast();

  const [addresses, setAddresses] = useState([]);
  const [selectedAddressId, setSelectedAddressId] = useState(null);
  const [shippingMethods, setShippingMethods] = useState([]);
  const [selectedMethodId, setSelectedMethodId] = useState(null);
  const [fallbackShippingFee, setFallbackShippingFee] = useState('0.00');
  const [preparing, setPreparing] = useState(true);
  const [loadError, setLoadError] = useState('');
  const [placing, setPlacing] = useState(false);

  useEffect(() => {
    let active = true;
    (async () => {
      try {
        const [addr, settings, methods] = await Promise.all([
          addressService.list(),
          settingsService.publicSettings().catch(() => null),
          shippingService.list().catch(() => []),
        ]);
        if (!active) return;
        setAddresses(addr);
        setFallbackShippingFee(settings?.shipping?.flat_rate ?? '0.00');
        setShippingMethods(methods);
        const defaultMethod = methods.find((m) => m.is_default) || methods[0];
        if (defaultMethod) setSelectedMethodId(defaultMethod.id);
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
  const subtotal = Number(cart.subtotal) || 0;

  const selectedMethod = shippingMethods.find((m) => m.id === selectedMethodId) || null;
  const shippingFee =
    shippingMethods.length > 0
      ? shippingService.feeFor(selectedMethod, subtotal)
      : fallbackShippingFee;

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
      const order = await checkoutService.placeOrder(selectedAddressId, selectedMethodId);
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

          {shippingMethods.length > 0 && (
            <Card className="shadow-sm mb-4">
              <Card.Body>
                <h2 className="h5 mb-1">2. Shipping Method</h2>
                <p className="text-muted small mb-3">
                  Choose how your order is delivered. The final fee is confirmed at checkout.
                </p>
                <Form.Group>
                  <div className="d-flex flex-column gap-2">
                    {shippingMethods.map((method) => {
                      const fee = shippingService.feeFor(method, subtotal);
                      const free = method.free_over !== null && subtotal >= Number(method.free_over);
                      const checked = method.id === selectedMethodId;
                      return (
                        <label
                          key={method.id}
                          className={`border rounded p-3 d-flex align-items-start gap-3 cursor-pointer ${
                            checked ? 'border-success bg-success-subtle' : ''
                          }`}
                          style={{ cursor: 'pointer' }}
                        >
                          <Form.Check.Input
                            type="radio"
                            name="shipping_method"
                            checked={checked}
                            onChange={() => setSelectedMethodId(method.id)}
                            className="mt-1"
                          />
                          <div className="flex-grow-1">
                            <div className="fw-semibold d-flex align-items-center gap-2 flex-wrap">
                              {method.name}
                              {method.is_default && (
                                <span className="badge bg-success-subtle text-success border border-success-subtle">
                                  Default
                                </span>
                              )}
                            </div>
                            {method.description && (
                              <div className="text-muted small">{method.description}</div>
                            )}
                            {method.estimated_days && (
                              <div className="small text-muted d-flex align-items-center gap-1">
                                <ClockIcon size={13} />
                                Estimated delivery within {method.estimated_days} day(s)
                              </div>
                            )}
                            {free && (
                              <div className="small text-success d-flex align-items-center gap-1">
                                <CheckCircleIcon size={13} />
                                Free shipping on orders over {formatPrice(method.free_over)}
                              </div>
                            )}
                          </div>
                          <div className="fw-bold text-nowrap">
                            {free ? 'Free' : formatPrice(fee)}
                          </div>
                        </label>
                      );
                    })}
                  </div>
                </Form.Group>
              </Card.Body>
            </Card>
          )}
        </Col>

        <Col lg={4}>
          <OrderSummary
            items={items}
            subtotal={cart.subtotal}
            shippingFee={shippingFee}
            shippingMethodName={selectedMethod?.name}
            total={subtotal + Number(shippingFee)}
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
            {shippingMethods.length === 0
              ? 'Shipping fee applies to all orders.'
              : !selectedMethod
                ? 'Shipping fee is calculated at checkout.'
                : 'Shipping fee is calculated from live product prices.'}
          </p>
        </Col>
      </Row>
    </Container>
  );
}