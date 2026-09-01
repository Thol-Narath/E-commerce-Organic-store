import { useState } from 'react';
import { Link } from 'react-router-dom';
import { Alert, Badge, Button, Card, Col, Container, Row } from 'react-bootstrap';
import { useCart } from '../../context/CartContext';
import { useToast } from '../../context/ToastContext';
import QuantityControl from '../../components/cart/QuantityControl';
import ConfirmDialog from '../../components/common/ConfirmDialog';
import EmptyState from '../../components/common/EmptyState';
import LoadingSpinner from '../../components/common/LoadingSpinner';
import ImageWithFallback from '../../components/common/ImageWithFallback';
import usePageTitle from '../../hooks/usePageTitle';
import { formatPrice } from '../../utils/format';
import { getErrorMessage } from '../../utils/error';
import { CartIcon, TrashIcon } from '../../assets/icons';

export default function CartPage() {
  const {
    cart,
    loading,
    error,
    updateCartItem,
    removeCartItem,
    clearCart,
    isItemBusy,
    clearing,
  } = useCart();
  const { showToast } = useToast();
  const [confirmingClear, setConfirmingClear] = useState(false);

  usePageTitle('Shopping Cart');

  if (loading) {
    return (
      <Container className="py-5">
        <LoadingSpinner label="Loading your cart..." />
      </Container>
    );
  }

  if (error) {
    return (
      <Container className="py-5">
        <Alert variant="danger">{error}</Alert>
      </Container>
    );
  }

  const items = cart.items || [];

  if (items.length === 0) {
    return (
      <Container className="py-5">
        <EmptyState
          title="Your cart is empty"
          message="Browse the shop and add organic products to your cart."
          actionLabel="Start Shopping"
          actionTo="/shop"
        />
      </Container>
    );
  }

  const availableItems = items.filter((item) => item.available);
  const unavailableItems = items.filter((item) => !item.available);

  const handleQuantityChange = async (item, quantity) => {
    if (Number.isNaN(quantity) || quantity < 1) return;
    try {
      await updateCartItem(item.id, quantity);
    } catch (err) {
      showToast(getErrorMessage(err), 'danger');
    }
  };

  const handleRemove = async (item) => {
    try {
      await removeCartItem(item.id);
      showToast('Item removed from your cart.');
    } catch (err) {
      showToast(getErrorMessage(err), 'danger');
    }
  };

  const handleClear = async () => {
    try {
      await clearCart();
      setConfirmingClear(false);
      showToast('Your cart has been cleared.');
    } catch (err) {
      showToast(getErrorMessage(err), 'danger');
    }
  };

  return (
    <Container className="py-4">
      <h1 className="h3 mb-4 d-flex align-items-center gap-2">
        <CartIcon size={26} className="text-success" />
        Shopping Cart
      </h1>

      {unavailableItems.length > 0 && (
        <Alert variant="warning" className="d-flex align-items-center gap-2">
          Some items are no longer available. Remove them to continue.
        </Alert>
      )}

      <Row className="g-4">
        <Col lg={8}>
          <div className="cart-list">
            {items.map((item) => {
              const product = item.product;
              const busy = isItemBusy(item.id);
              const unavailable = !item.available;

              return (
                <Card key={item.id} className="mb-3 shadow-sm cart-item">
                  <Card.Body className="d-flex flex-column flex-sm-row gap-3 align-items-sm-center">
                    {unavailable && (
                      <Badge bg="warning" text="dark" pill className="cart-item-unavailable-badge">
                        Unavailable
                      </Badge>
                    )}

                    <Link to={`/products/${product?.slug}`} className="cart-item-image-link flex-shrink-0">
                      <div className="cart-item-image">
                        <ImageWithFallback
                          src={product?.primary_image?.url || product?.images?.[0]?.url}
                          alt={product?.name || 'Product'}
                          className="w-100 h-100"
                        />
                      </div>
                    </Link>

                    <div className="flex-grow-1">
                      <Link to={`/products/${product?.slug}`} className="text-reset text-decoration-none">
                        <h2 className="h6 mb-1">{product?.name}</h2>
                      </Link>
                      {product?.category?.name && (
                        <span className="text-muted small text-capitalize">{product.category.name}</span>
                      )}
                      <p className="mb-0 mt-1">
                        <span className="fw-semibold">{formatPrice(item.unit_price)}</span>
                        <span className="text-muted"> each</span>
                      </p>
                    </div>

                    <QuantityControl
                      value={item.quantity}
                      min={1}
                      onChange={(next) => handleQuantityChange(item, next)}
                      disabled={busy || unavailable}
                    />

                    <div className="cart-item-line-total text-sm-center">
                      <div className="small text-muted d-sm-none">Line total</div>
                      <strong>{formatPrice(item.line_total)}</strong>
                    </div>

                    <Button
                      variant="outline-danger"
                      size="sm"
                      className="cart-item-remove"
                      onClick={() => handleRemove(item)}
                      disabled={busy}
                      aria-label={`Remove ${product?.name} from cart`}
                    >
                      <TrashIcon size={16} />
                    </Button>
                  </Card.Body>
                </Card>
              );
            })}
          </div>

          <div className="d-flex justify-content-between align-items-center mt-2">
            <Button
              variant="outline-secondary"
              size="sm"
              onClick={() => setConfirmingClear(true)}
              disabled={clearing}
            >
              <TrashIcon size={15} className="me-1" />
              Clear Cart
            </Button>
            <Button as={Link} to="/shop" variant="link" className="text-decoration-none">
              Continue shopping
            </Button>
          </div>
        </Col>

        <Col lg={4}>
          <Card className="shadow-sm cart-summary sticky-lg-top">
            <Card.Body>
              <h2 className="h6 text-uppercase text-muted mb-3">Order Summary</h2>
              <div className="d-flex justify-content-between mb-2">
                <span className="text-muted">
                  Subtotal ({availableItems.length} item{availableItems.length === 1 ? '' : 's'})
                </span>
                <span>{formatPrice(cart.subtotal)}</span>
              </div>
              {unavailableItems.length > 0 && (
                <div className="d-flex justify-content-between mb-2 text-danger">
                  <span>Unavailable items</span>
                  <span>
                    {formatPrice(
                      unavailableItems.reduce((sum, item) => sum + Number(item.line_total), 0)
                    )}
                  </span>
                </div>
              )}
              <hr />
              <div className="d-flex justify-content-between mb-3 fs-5">
                <strong>Total</strong>
                <strong>{formatPrice(cart.subtotal)}</strong>
              </div>
              <Button
                as={Link}
                to="/checkout"
                variant="success"
                size="lg"
                className="w-100"
                disabled={unavailableItems.length > 0}
              >
                Proceed to Checkout
              </Button>
              {unavailableItems.length > 0 && (
                <p className="text-danger small mb-0 mt-2 text-center">
                  Remove unavailable items to check out.
                </p>
              )}
              <p className="text-muted small mb-0 mt-2 text-center">
                Shipping calculated at checkout.
              </p>
            </Card.Body>
          </Card>
        </Col>
      </Row>

      <ConfirmDialog
        show={confirmingClear}
        title="Clear your cart?"
        message="All items will be removed from your cart. This cannot be undone."
        confirmLabel="Clear Cart"
        busy={clearing}
        onConfirm={handleClear}
        onCancel={() => setConfirmingClear(false)}
      />
    </Container>
  );
}