import { Link } from 'react-router-dom';
import { Alert, Badge, Button, Card, Col, Container, Row, Spinner } from 'react-bootstrap';
import { useWishlist } from '../../context/WishlistContext';
import { useCart } from '../../context/CartContext';
import { useToast } from '../../context/ToastContext';
import EmptyState from '../../components/common/EmptyState';
import LoadingSpinner from '../../components/common/LoadingSpinner';
import ImageWithFallback from '../../components/common/ImageWithFallback';
import Breadcrumbs from '../../components/common/Breadcrumbs';
import AccountLayout from '../../layouts/AccountLayout';
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
      <AccountLayout>
        <Container className="py-5">
          <LoadingSpinner label="Loading your wishlist..." />
        </Container>
      </AccountLayout>
    );
  }

  if (error) {
    return (
      <AccountLayout>
        <Container className="py-5">
          <Alert variant="danger">{error}</Alert>
        </Container>
      </AccountLayout>
    );
  }

  const items = wishlist.items || [];

  if (items.length === 0) {
    return (
      <AccountLayout>
        <EmptyState
          title="Your wishlist is empty"
          message="Save your favourite organic products and come back to them anytime."
          actionLabel="Continue Shopping"
          actionTo="/shop"
        />
      </AccountLayout>
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
    <AccountLayout>
      <Breadcrumbs items={[{ label: 'Home', to: '/' }, { label: 'My Account', to: '/account/profile' }, { label: 'Wishlist' }]} />

      <div className="account-page-header">
        <h1 className="h3 mb-1 d-flex align-items-center gap-2">
          <HeartIcon size={26} className="text-success" />
          My Wishlist
          <Badge bg="light" text="dark" pill>
            {items.length}
          </Badge>
        </h1>
        <p className="text-muted mb-0">Your saved organic products.</p>
      </div>

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
          const hasDiscount =
            product?.compare_at_price && Number(product.compare_at_price) > Number(product.price);
          const discountPercentValue = hasDiscount
            ? Math.round((1 - Number(product.price) / Number(product.compare_at_price)) * 100)
            : 0;

          return (
            <Col xs={12} md={6} lg={4} key={item.id}>
              <Card className="h-100 shadow-sm wishlist-item account-card">
                <Card.Body className="d-flex flex-column">
                  <div className="position-relative mb-3">
                    <Link to={`/products/${product?.slug}`} className="wishlist-item-image-link d-block">
                      <div className="wishlist-item-image w-100" style={{ height: 180 }}>
                        <ImageWithFallback
                          src={product?.primary_image?.url || product?.images?.[0]?.url}
                          alt={product?.name || 'Product'}
                          className="w-100 h-100"
                          placeholderClassName="w-100 h-100"
                        />
                      </div>
                    </Link>
                    {hasDiscount && (
                      <Badge bg="danger" pill className="position-absolute top-0 start-0 m-2">
                        −{discountPercentValue}%
                      </Badge>
                    )}
                  </div>

                  <div className="flex-grow-1">
                    <Link to={`/products/${product?.slug}`} className="text-reset text-decoration-none">
                      <h2 className="h6 mb-1">{product?.name}</h2>
                    </Link>
                    {product?.category?.name && (
                      <span className="text-muted small text-capitalize">{product.category.name}</span>
                    )}

                    <div className="d-flex align-items-center gap-2 mt-2">
                      <span className="fw-semibold text-success">{formatPrice(product?.price)}</span>
                      {hasDiscount && (
                        <span className="text-muted small text-decoration-line-through">
                          {formatPrice(product?.compare_at_price)}
                        </span>
                      )}
                    </div>

                    {!available ? (
                      <Badge bg="secondary" pill className="mt-2">Out of stock</Badge>
                    ) : (
                      <Badge bg="success" pill className="mt-2">In stock</Badge>
                    )}
                  </div>

                  <div className="d-flex gap-2 mt-3">
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
                      Add to Cart
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
    </AccountLayout>
  );
}