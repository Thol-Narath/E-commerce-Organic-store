import { Link } from 'react-router-dom';
import { Alert, Badge, Button, Card, Col, Container, Row, Spinner } from 'react-bootstrap';
import { useWishlist } from '../../context/WishlistContext';
import { useCart } from '../../context/CartContext';
import { useToast } from '../../context/ToastContext';
import EmptyState from '../../components/common/EmptyState';
import LoadingSpinner from '../../components/common/LoadingSpinner';
import ImageWithFallback from '../../components/common/ImageWithFallback';
import usePageTitle from '../../hooks/usePageTitle';
import { formatPrice } from '../../utils/format';
import { getErrorMessage } from '../../utils/error';
import { CartIcon, HeartIcon, TrashIcon } from '../../assets/icons';

export default function WishlistPage() {
  const { wishlist, loading, error, removeFromWishlist, moveToCart, isItemBusy } = useWishlist();
  const { replaceCart } = useCart();
  const { showToast } = useToast();

  usePageTitle('Wishlist');

  if (loading) {
    return (
      <Container className="py-5">
        <LoadingSpinner label="Loading your wishlist..." />
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

  const items = wishlist.items || [];

  if (items.length === 0) {
    return (
      <Container className="py-5">
        <EmptyState
          title="Your wishlist is empty"
          message="Save your favourite organic products and come back to them anytime."
          actionLabel="Discover Products"
          actionTo="/shop"
        />
      </Container>
    );
  }

  const handleRemove = async (item) => {
    try {
      await removeFromWishlist(item.id);
      showToast('Removed from your wishlist.');
    } catch (err) {
      showToast(getErrorMessage(err), 'danger');
    }
  };

  const handleMoveToCart = async (item) => {
    try {
      const result = await moveToCart(item.id);
      replaceCart(result.cart);
      showToast(`${item.product?.name || 'Item'} moved to your cart.`);
    } catch (err) {
      showToast(getErrorMessage(err), 'danger');
    }
  };

  return (
    <Container className="py-4">
      <h1 className="h3 mb-4 d-flex align-items-center gap-2">
        <HeartIcon size={26} className="text-success" />
        My Wishlist
        <Badge bg="light" text="dark" pill>
          {items.length}
        </Badge>
      </h1>

      {items.some((item) => !item.available) && (
        <Alert variant="warning" className="d-flex align-items-center gap-2">
          Some products are no longer available.
        </Alert>
      )}

      <Row className="g-3">
        {items.map((item) => {
          const product = item.product;
          const available = item.available;
          const busy = isItemBusy(item.id);

          return (
            <Col xs={12} md={6} lg={4} key={item.id}>
              <Card className="h-100 shadow-sm wishlist-item">
                <Card.Body className="d-flex flex-column">
                  <div className="d-flex gap-3 mb-3">
                    <Link to={`/products/${product?.slug}`} className="wishlist-item-image-link flex-shrink-0">
                      <div className="wishlist-item-image">
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
                      <p className="mb-0 mt-1 fw-semibold">{formatPrice(product?.price)}</p>
                      {!available && (
                        <Badge bg="warning" text="dark" pill className="mt-1">
                          Unavailable
                        </Badge>
                      )}
                    </div>
                  </div>

                  <div className="d-flex gap-2 mt-auto">
                    <Button
                      variant="success"
                      size="sm"
                      className="flex-grow-1 d-inline-flex align-items-center justify-content-center gap-2"
                      onClick={() => handleMoveToCart(item)}
                      disabled={busy || !available}
                    >
                      {busy ? (
                        <Spinner animation="border" size="sm" />
                      ) : (
                        <CartIcon size={16} />
                      )}
                      Move to Cart
                    </Button>
                    <Button
                      variant="outline-danger"
                      size="sm"
                      onClick={() => handleRemove(item)}
                      disabled={busy}
                      aria-label={`Remove ${product?.name} from wishlist`}
                    >
                      <TrashIcon size={16} />
                    </Button>
                  </div>
                </Card.Body>
              </Card>
            </Col>
          );
        })}
      </Row>
    </Container>
  );
}