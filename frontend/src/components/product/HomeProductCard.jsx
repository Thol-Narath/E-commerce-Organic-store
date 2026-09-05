import { useState } from 'react';
import { Link, useLocation, useNavigate } from 'react-router-dom';
import { Button, Card, Spinner } from 'react-bootstrap';
import ImageWithFallback from '../common/ImageWithFallback';
import { useCart } from '../../context/CartContext';
import { useWishlist } from '../../context/WishlistContext';
import { useToast } from '../../context/ToastContext';
import { useAuth } from '../../context/AuthContext';
import { getErrorMessage } from '../../utils/error';
import { CartIcon, HeartIcon, MinusIcon, PlusIcon } from '../../assets/icons';
import { discountPercent, formatPrice } from '../../utils/format';

export default function HomeProductCard({ product }) {
  const { user } = useAuth();
  const { addToCart } = useCart();
  const { toggleWishlist, isWishlisted, isProductBusy } = useWishlist();
  const { showToast } = useToast();
  const navigate = useNavigate();
  const location = useLocation();

  const [qty, setQty] = useState(1);
  const [adding, setAdding] = useState(false);

  const imageUrl = product.primary_image?.url || product.images?.[0]?.url || null;
  const percent = discountPercent(product.price, product.compare_at_price);
  const wishlisted = isWishlisted(product.id);
  const wishlistBusy = isProductBusy(product.id);
  const outOfStock = product.availability === 'out_of_stock';

  const ensureAuth = () => {
    if (user) return true;
    navigate('/login', { state: { from: location } });
    return false;
  };

  const handleAddToCart = async () => {
    if (!ensureAuth()) return;
    setAdding(true);
    try {
      await addToCart(product.id, qty);
      showToast(`${product.name} added to your cart.`);
      setQty(1);
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
      showToast(added ? `${product.name} added to wishlist.` : 'Removed from wishlist.');
    } catch (err) {
      showToast(getErrorMessage(err), 'danger');
    }
  };

  return (
    <Card className="h-100 w-100 home-product-card">
      <Link to={`/products/${product.slug}`} className="home-product-card-image-link" aria-label={product.name}>
        <div className="home-product-card-image">
          <ImageWithFallback src={imageUrl} alt={product.name} className="w-100 h-100" />
          {percent !== null && (
            <span className="discount-badge">{percent}% OFF</span>
          )}
          <button
            type="button"
            className={`home-product-wishlist-btn ${wishlisted ? 'active' : ''}`}
            onClick={(e) => { e.preventDefault(); handleToggleWishlist(); }}
            disabled={wishlistBusy}
            aria-label={wishlisted ? 'Remove from wishlist' : 'Add to wishlist'}
          >
            <HeartIcon size={18} fill={wishlisted ? 'currentColor' : 'none'} />
          </button>
        </div>
      </Link>

      <div className="home-product-card-body">
        {product.category?.name && (
          <span className="home-product-category">{product.category.name}</span>
        )}
        <Link to={`/products/${product.slug}`} className="home-product-name text-decoration-none">
          {product.name}
        </Link>
        <div className="home-product-price">
          <span className="price-current">{formatPrice(product.price)}</span>
          {product.compare_at_price && (
            <span className="price-compare">{formatPrice(product.compare_at_price)}</span>
          )}
        </div>

        <div className="home-product-actions">
          <div className="qty-stepper">
            <button
              type="button"
              className="qty-btn"
              onClick={() => setQty(Math.max(1, qty - 1))}
              disabled={qty <= 1}
              aria-label="Decrease quantity"
            >
              <MinusIcon size={14} />
            </button>
            <span className="qty-value">{qty}</span>
            <button
              type="button"
              className="qty-btn"
              onClick={() => setQty(qty + 1)}
              aria-label="Increase quantity"
            >
              <PlusIcon size={14} />
            </button>
          </div>
          <Button
            variant="success"
            size="sm"
            className="home-product-add-btn"
            onClick={handleAddToCart}
            disabled={adding || outOfStock}
          >
            {adding ? (
              <Spinner animation="border" size="sm" />
            ) : (
              <>
                <CartIcon size={14} />
                {outOfStock ? 'Out of Stock' : 'Add'}
              </>
            )}
          </Button>
        </div>
      </div>
    </Card>
  );
}
