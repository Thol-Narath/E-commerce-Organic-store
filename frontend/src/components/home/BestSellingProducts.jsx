import { useState, useEffect } from 'react';
import { Container } from 'react-bootstrap';
import { productService } from '../../services/productService';
import SectionHeader from '../common/SectionHeader';
import HomeProductCard from '../product/HomeProductCard';
import ProductSkeletons from '../product/ProductSkeletons';

export default function BestSellingProducts() {
  const [products, setProducts] = useState([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    let cancelled = false;
    productService.getProducts({ sort: 'best_selling', per_page: 8 }).then((data) => {
      if (!cancelled) setProducts(data?.items || []);
    }).catch(() => {}).finally(() => {
      if (!cancelled) setLoading(false);
    });
    return () => { cancelled = true; };
  }, []);

  return (
    <section className="section-best-selling py-5 bg-light">
      <Container>
        <SectionHeader
          title="Best Selling Products"
          subtitle="Our most popular items loved by customers"
          link="/shop"
          linkText="View All"
        />

        {loading ? (
          <div className="row g-3 g-lg-4">
            {Array.from({ length: 4 }, (_, i) => (
              <div key={i} className="col-6 col-md-4 col-lg-3">
                <div className="product-skeleton">
                  <div className="product-skeleton-image skeleton-shimmer" />
                  <div className="p-3">
                    <div className="skeleton-line skeleton-shimmer mb-2 w-50" />
                    <div className="skeleton-line skeleton-shimmer mb-2 w-75" />
                    <div className="skeleton-line skeleton-shimmer mb-3 w-40" />
                    <div className="skeleton-btn skeleton-shimmer" />
                  </div>
                </div>
              </div>
            ))}
          </div>
        ) : products.length === 0 ? (
          <p className="text-muted text-center">No products available yet.</p>
        ) : (
          <div className="row g-3 g-lg-4">
            {products.map((product) => (
              <div key={product.id} className="col-6 col-md-4 col-lg-3">
                <HomeProductCard product={product} />
              </div>
            ))}
          </div>
        )}
      </Container>
    </section>
  );
}
