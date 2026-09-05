import { useState, useEffect } from 'react';
import { Container } from 'react-bootstrap';
import { productService } from '../../services/productService';
import { categoryService } from '../../services/categoryService';
import SectionHeader from '../common/SectionHeader';
import HomeProductCard from '../product/HomeProductCard';

export default function MostPopularProducts() {
  const [categories, setCategories] = useState([]);
  const [activeTab, setActiveTab] = useState('all');
  const [products, setProducts] = useState([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    categoryService.getCategories().then((data) => {
      setCategories(data || []);
    }).catch(() => {});
  }, []);

  useEffect(() => {
    let cancelled = false;
    setLoading(true);
    const params = { sort: 'best_selling', per_page: 8 };
    if (activeTab !== 'all') {
      params.category_slug = activeTab;
    }
    productService.getProducts(params).then((data) => {
      if (!cancelled) setProducts(data?.items || []);
    }).catch(() => {}).finally(() => {
      if (!cancelled) setLoading(false);
    });
    return () => { cancelled = true; };
  }, [activeTab]);

  const tabs = [{ slug: 'all', name: 'All' }, ...categories];

  return (
    <section className="section-popular py-5">
      <Container>
        <SectionHeader
          title="Most Popular Products"
          subtitle="Discover what our customers love most"
        />

        <div className="popular-tabs mb-4">
          {tabs.map((tab) => (
            <button
              key={tab.slug}
              type="button"
              className={`popular-tab ${activeTab === tab.slug ? 'active' : ''}`}
              onClick={() => setActiveTab(tab.slug)}
            >
              {tab.name}
            </button>
          ))}
        </div>

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
          <p className="text-muted text-center">No products found in this category.</p>
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
