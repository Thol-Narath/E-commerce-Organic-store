import { useState } from 'react';
import { Link, useLocation, useNavigate } from 'react-router-dom';
import { Badge, Button, Card, Spinner } from 'react-bootstrap';
import ImageWithFallback from '../common/ImageWithFallback';
import { useCart } from '../../context/CartContext';
import { useWishlist } from '../../context/WishlistContext';
import { useToast } from '../../context/ToastContext';
import { useAuth } from '../../context/AuthContext';
import { getErrorMessage } from '../../utils/error';
import { CartIcon, HeartIcon } from '../../assets/icons';
import { discountPercent, formatPrice } from '../../utils/format';
import InventoryStatusBadge from '../inventory/InventoryStatusBadge';

/**
 * Product listing card. Adds full cart + wishlist actions: Add to Cart and a
 * wishlist toggle (add/remove). Guests are redirected to the login page and
 * returned to the current product afterwards.
 */
export default function ProductCard({ product }) {
  const { user } = useAuth();
  const { addToCart } = useCart();
  const { toggleWishlist, isWishlisted, isProductBusy } = useWishlist();
  const { showToast } = useToast();
  const navigate = useNavigate();
  const location = useLocation();

  const [adding, setAdding] = useState(false);

  const imageUrl = product.primary_image?.url || product.images?.[0]?.url || null;
  const percent = discountPercent(product.price, product.compare_at_price);
  const wishlisted = isWishlisted(product.id);
  const wishlistBusy = isProductBusy(product.id);

  const ensureAuth = () => {
    if (user) return true;
    navigate('/login', { state: { from: location } });
    return false;
  };

  const handleAddToCart = async () => {
    if (!ensureAuth()) return;
    setAdding(true);
    try {
      await addToCart(product.id, 1);
      showToast(`${product.name} added to your cart.`);
    } catch (err) {
      showToast(getErrorMessage(err), 'danger');
    } finally {
      setAdding(false);
    }
  };

  const handleToggleWishlist = async () => {
    if (!ensureAuth()) return;
    try {
      const { added } = await toggleWishlist(product.id);
      showToast(added ? `${product.name} added to your wishlist.` : 'Removed from your wishlist.');
    } catch (err) {
      showToast(getErrorMessage(err), 'danger');
    }
  };

  return (
    <Card className="h-100 w-100 product-card shadow-sm">
      <Link to={`/products/${product.slug}`} className="product-card-image-link" aria-label={product.name}>
        <div className="product-card-image">
          <ImageWithFallback src={imageUrl} alt={product.name} className="w-100 h-100" />
          {percent !== null && (
            <span className="discount-badge">{percent}% off</span>
          )}
          {product.is_featured && (
            <Badge bg="success" pill className="featured-badge">
              Featured
            </Badge>
          )}
        </div>
      </Link>

      <Card.Body className="d-flex flex-column">
        {product.category?.name && (
          <Link
            to={`/shop?category_id=${product.category.id}`}
            className="product-card-category text-decoration-none"
            tabIndex={-1}
          >
            {product.category.name}
          </Link>
        )}

        <Card.Title className="product-card-title fs-6 mt-1">
          <Link to={`/products/${product.slug}`} className="text-reset text-decoration-none">
            {product.name}
          </Link>
        </Card.Title>

        <div className="product-card-price mt-auto pt-2">
          <span className="price-current">{formatPrice(product.price)}</span>
          {product.compare_at_price && (
            <span className="price-compare text-muted">{formatPrice(product.compare_at_price)}</span>
          )}
        </div>

        <div className="mt-2">
          <InventoryStatusBadge status={product.availability} className="small" />
        </div>

        <div className="d-flex gap-2 mt-3 product-card-actions flex-column">
          <div className="d-flex gap-2">
            <Button
              variant="success"
              size="sm"
              className="flex-grow-1 d-inline-flex align-items-center justify-content-center gap-2"
              onClick={handleAddToCart}
              disabled={adding || product.availability === 'out_of_stock'}
              aria-label={`Add ${product.name} to cart`}
            >
              {adding ? (
                <Spinner animation="border" size="sm" />
              ) : (
                <CartIcon size={16} />
              )}
              {product.availability === 'out_of_stock' ? 'Out of Stock' : 'Add to Cart'}
            </Button>
            <Button
              variant={wishlisted ? 'outline-danger' : 'outline-secondary'}
              size="sm"
              className="wishlist-toggle"
              onClick={handleToggleWishlist}
              disabled={wishlistBusy}
              aria-pressed={wishlisted}
              aria-label={wishlisted ? `Remove ${product.name} from wishlist` : `Add ${product.name} to wishlist`}
            >
              <HeartIcon size={17} fill={wishlisted ? 'currentColor' : 'none'} />
            </Button>
          </div>

          <Button
            as={Link}
            to={`/products/${product.slug}`}
            variant="outline-success"
            size="sm"
            className="w-100"
          >
            View Details
          </Button>
        </div>
      </Card.Body>
    </Card>
  );
}