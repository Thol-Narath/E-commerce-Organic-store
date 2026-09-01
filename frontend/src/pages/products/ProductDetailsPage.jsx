import { useEffect, useState } from 'react';
import { Link, useLocation, useNavigate, useParams } from 'react-router-dom';
import { Badge, Breadcrumb, Button, Col, Container, Row, Spinner } from 'react-bootstrap';
import ProductGallery from '../../components/product/ProductGallery';
import QuantityControl from '../../components/cart/QuantityControl';
import ErrorState from '../../components/common/ErrorState';
import LoadingSpinner from '../../components/common/LoadingSpinner';
import EmptyState from '../../components/common/EmptyState';
import usePageTitle from '../../hooks/usePageTitle';
import { productService } from '../../services/productService';
import { normalizeError } from '../../services/api';
import { useAuth } from '../../context/AuthContext';
import { useCart } from '../../context/CartContext';
import { useWishlist } from '../../context/WishlistContext';
import { useToast } from '../../context/ToastContext';
import { getErrorMessage } from '../../utils/error';
import { CartIcon, HeartIcon } from '../../assets/icons';
import { discountPercent, formatPrice } from '../../utils/format';
import InventoryStatusBadge from '../../components/inventory/InventoryStatusBadge';

export default function ProductDetailsPage() {
  const { slug } = useParams();
  const { user } = useAuth();
  const { addToCart } = useCart();
  const { toggleWishlist, isWishlisted, isProductBusy } = useWishlist();
  const { showToast } = useToast();
  const navigate = useNavigate();
  const location = useLocation();

  const [product, setProduct] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [notFound, setNotFound] = useState(false);
  const [quantity, setQuantity] = useState(1);
  const [adding, setAdding] = useState(false);

  usePageTitle(product?.name);

  useEffect(() => {
    let cancelled = false;
    setLoading(true);
    setError('');
    setNotFound(false);
    setProduct(null);

    productService
      .getProduct(slug)
      .then((data) => {
        if (!cancelled) setProduct(data);
      })
      .catch((err) => {
        if (cancelled) return;
        const apiError = normalizeError(err);
        setError(apiError.message);
        if (apiError.status === 404) setNotFound(true);
      })
      .finally(() => {
        if (!cancelled) setLoading(false);
      });

    return () => {
      cancelled = true;
    };
  }, [slug]);

  if (loading) {
    return (
      <Container className="py-5">
        <LoadingSpinner label="Loading product..." />
      </Container>
    );
  }

  if (notFound || (!product && error)) {
    return (
      <Container className="py-5">
        <EmptyState
          title="Product Not Found"
          message="The product you are looking for does not exist or is no longer available."
          actionLabel="Return to Shop"
          actionTo="/shop"
        />
      </Container>
    );
  }

  if (error) {
    return (
      <Container className="py-5">
        <ErrorState message={error} onRetry={() => window.location.reload()} />
      </Container>
    );
  }

  const images = product.images && product.images.length ? product.images : [];
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
      await addToCart(product.id, quantity);
      showToast(`${product.name} added to your cart.`);
      setQuantity(1);
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
    <Container className="py-4">
      <Breadcrumb className="mb-4">
        <Breadcrumb.Item linkAs={Link} linkProps={{ to: '/' }}>Home</Breadcrumb.Item>
        <Breadcrumb.Item linkAs={Link} linkProps={{ to: '/shop' }}>Shop</Breadcrumb.Item>
        {product.category && (
          <Breadcrumb.Item linkAs={Link} linkProps={{ to: `/shop?category_id=${product.category.id}` }}>
            {product.category.name}
          </Breadcrumb.Item>
        )}
        <Breadcrumb.Item active>{product.name}</Breadcrumb.Item>
      </Breadcrumb>

      <Row>
        <Col lg={6} className="mb-4 mb-lg-0">
          <ProductGallery images={images} name={product.name} />
        </Col>

        <Col lg={6}>
          <div className="mb-3">
            <h1 className="h2 mb-2">{product.name}</h1>
            {product.category && (
              <Link to={`/shop?category_id=${product.category.id}`} className="text-decoration-none text-muted">
                {product.category.name}
              </Link>
            )}
          </div>

          {product.unit && <Badge bg="light" text="dark" pill className="me-2">{product.unit}</Badge>}
          {product.is_featured && (
            <Badge bg="success" pill>Featured</Badge>
          )}
          <InventoryStatusBadge status={product.availability} className="ms-1" />

          <div className="product-detail-price d-flex align-items-baseline gap-2 my-3">
            <span className="price-current h3 mb-0">{formatPrice(product.price)}</span>
            {product.compare_at_price && (
              <span className="price-compare text-muted">{formatPrice(product.compare_at_price)}</span>
            )}
            {percent !== null && <Badge bg="danger" pill>{percent}% off</Badge>}
          </div>

          {product.short_description && (
            <p className="lead text-muted">{product.short_description}</p>
          )}

          <div className="d-flex flex-wrap align-items-center gap-3 my-4">
            <QuantityControl
              value={quantity}
              min={1}
              onChange={setQuantity}
              disabled={adding || product.availability === 'out_of_stock'}
            />

            <div className="d-flex flex-wrap gap-2">
              <Button
                variant="success"
                size="lg"
                className="d-inline-flex align-items-center gap-2"
                onClick={handleAddToCart}
                disabled={adding || product.availability === 'out_of_stock'}
              >
                {adding ? <Spinner animation="border" size="sm" /> : <CartIcon size={20} />}
                {product.availability === 'out_of_stock' ? 'Out of Stock' : 'Add to Cart'}
              </Button>
              <Button
                variant={wishlisted ? 'outline-danger' : 'outline-secondary'}
                size="lg"
                className="d-inline-flex align-items-center gap-2"
                onClick={handleToggleWishlist}
                disabled={wishlistBusy}
                aria-pressed={wishlisted}
              >
                <HeartIcon size={20} fill={wishlisted ? 'currentColor' : 'none'} />
                {wishlisted ? 'Saved' : 'Save for later'}
              </Button>
            </div>

            <Button as={Link} to="/shop" variant="outline-success" size="lg">
              Back to Shop
            </Button>
          </div>

          <dl className="product-meta row mb-4">
            {product.sku && (
              <>
                <dt className="col-sm-4 text-muted">SKU</dt>
                <dd className="col-sm-8">{product.sku}</dd>
              </>
            )}
            {product.unit && (
              <>
                <dt className="col-sm-4 text-muted">Unit</dt>
                <dd className="col-sm-8">{product.unit}</dd>
              </>
            )}
            {product.category?.id && (
              <>
                <dt className="col-sm-4 text-muted">Category</dt>
                <dd className="col-sm-8 text-capitalize">{product.category.name}</dd>
              </>
            )}
          </dl>

          {product.description && (
            <div className="product-description">
              <h2 className="h6 text-uppercase text-muted mb-2">Description</h2>
              <p>{product.description}</p>
            </div>
          )}
        </Col>
      </Row>
    </Container>
  );
}