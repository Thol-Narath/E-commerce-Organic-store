import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { Button, Col, Container, Row } from 'react-bootstrap';
import { productService } from '../../services/productService';
import { categoryService } from '../../services/categoryService';
import ProductGrid from '../../components/product/ProductGrid';
import ProductSkeletons from '../../components/product/ProductSkeletons';
import CategoryGrid from '../../components/category/CategoryGrid';
import ErrorState from '../../components/common/ErrorState';
import usePageTitle from '../../hooks/usePageTitle';
import { LeafIcon, ShieldIcon, StoreIcon, TruckIcon } from '../../assets/icons';

const FEATURES = [
  {
    icon: LeafIcon,
    title: '100% Organic',
    text: 'Choose from produce grown without synthetic pesticides or artificial additives.',
  },
  {
    icon: TruckIcon,
    title: 'Fresh Products',
    text: 'Carefully selected, freshly stocked goods ready to arrive at your door.',
  },
  {
    icon: ShieldIcon,
    title: 'Quality Guaranteed',
    text: 'Every item is checked for freshness and quality before it ships.',
  },
  {
    icon: StoreIcon,
    title: 'Easy Shopping',
    text: 'A clear, modern catalog that makes finding organic essentials effortless.',
  },
];

export default function HomePage() {
  usePageTitle();

  const [categories, setCategories] = useState([]);
  const [categoriesLoading, setCategoriesLoading] = useState(true);
  const [categoriesError, setCategoriesError] = useState('');

  const [featured, setFeatured] = useState(null);
  const [featuredLoading, setFeaturedLoading] = useState(true);
  const [featuredError, setFeaturedError] = useState('');

  useEffect(() => {
    let cancelled = false;
    categoryService
      .getCategories()
      .then((data) => {
        if (!cancelled) setCategories(data || []);
      })
      .catch(() => {
        if (!cancelled) setCategoriesError('Unable to load categories right now.');
      })
      .finally(() => {
        if (!cancelled) setCategoriesLoading(false);
      });
    return () => {
      cancelled = true;
    };
  }, []);

  useEffect(() => {
    let cancelled = false;
    productService
      .getFeaturedProducts({ page: 1, per_page: 8 })
      .then((data) => {
        if (!cancelled) setFeatured(data);
      })
      .catch(() => {
        if (!cancelled) setFeaturedError('Unable to load featured products right now.');
      })
      .finally(() => {
        if (!cancelled) setFeaturedLoading(false);
      });
    return () => {
      cancelled = true;
    };
  }, []);

  const featuredItems = featured?.items || [];

  return (
    <>
      <section className="hero py-5">
        <Container className="py-lg-4">
          <Row className="align-items-center g-5">
            <Col lg={7}>
              <p className="hero-eyebrow">Farm-fresh · Naturally grown</p>
              <h1 className="hero-title">
                Fresh &amp; Organic Products Delivered to Your Door
              </h1>
              <p className="hero-subtitle">
                Explore a thoughtfully curated selection of organic vegetables,
                fruits, dairy, bakery and pantry staples — sourced with care and
                quality you can taste.
              </p>
              <div className="d-flex flex-wrap gap-2">
                <Button as={Link} to="/shop" variant="success" size="lg">
                  Shop Now
                </Button>
                <Button as={Link} to="/categories" variant="outline-light" size="lg">
                  Browse Categories
                </Button>
              </div>
            </Col>
            <Col lg={5} className="d-none d-lg-flex justify-content-center">
              <div className="hero-visual" aria-hidden="true">
                <LeafIcon size={120} className="hero-leaf" />
              </div>
            </Col>
          </Row>
        </Container>
      </section>

      <section className="section-categories py-5">
        <Container>
          <div className="d-flex flex-wrap justify-content-between align-items-end mb-4 gap-2">
            <div>
              <h2 className="h3 mb-1">Shop by Category</h2>
              <p className="text-muted mb-0">Find exactly what you are looking for.</p>
            </div>
            <Button as={Link} to="/categories" variant="outline-success" size="sm">
              View all categories
            </Button>
          </div>

          {categoriesError && <ErrorState message={categoriesError} />}

          {categoriesLoading ? (
            <Row className="g-3 g-lg-4">
              {Array.from({ length: 4 }, (_, i) => (
                <Col key={i} xs={6} md={4} lg={3}>
                  <div className="category-skeleton card h-100">
                    <div className="skeleton-circle skeleton-shimmer mx-auto mb-3" />
                    <div className="skeleton-line skeleton-shimmer mx-auto mb-2 w-50" />
                    <div className="skeleton-line skeleton-shimmer mx-auto w-25" />
                  </div>
                </Col>
              ))}
            </Row>
          ) : categories.length === 0 ? (
            <p className="text-muted">No categories are available right now.</p>
          ) : (
            <CategoryGrid categories={categories.slice(0, 8)} />
          )}
        </Container>
      </section>

      <section className="section-featured py-5 bg-light">
        <Container>
          <div className="d-flex flex-wrap justify-content-between align-items-end mb-4 gap-2">
            <div>
              <h2 className="h3 mb-1">Featured Products</h2>
              <p className="text-muted mb-0">Our hand-picked organic favorites.</p>
            </div>
            <Button as={Link} to="/shop" variant="outline-success" size="sm">
              View all products
            </Button>
          </div>

          {featuredError && <ErrorState message={featuredError} />}

          {featuredLoading ? (
            <ProductSkeletons count={8} />
          ) : featuredItems.length === 0 ? (
            <p className="text-muted">No featured products are available yet. Check back soon!</p>
          ) : (
            <ProductGrid products={featuredItems} />
          )}
        </Container>
      </section>

      <section className="section-features py-5">
        <Container>
          <h2 className="h3 text-center mb-4">Why Choose Us</h2>
          <Row className="g-4">
            {FEATURES.map((feature) => (
              <Col key={feature.title} xs={6} md={3}>
                <div className="feature-card h-100 text-center p-4">
                  <div className="feature-icon mx-auto mb-3">
                    <feature.icon size={28} />
                  </div>
                  <h3 className="h6">{feature.title}</h3>
                  <p className="text-muted small mb-0">{feature.text}</p>
                </div>
              </Col>
            ))}
          </Row>
        </Container>
      </section>

      <section className="section-cta py-5">
        <Container>
          <div className="cta-panel rounded-4 p-4 p-md-5 text-center">
            <h2 className="h3 mb-2">Ready to shop fresh?</h2>
            <p className="mb-4">Browse the full organic collection and discover your new favorites.</p>
            <Button as={Link} to="/shop" variant="light" size="lg">
              Start Shopping
            </Button>
          </div>
        </Container>
      </section>
    </>
  );
}