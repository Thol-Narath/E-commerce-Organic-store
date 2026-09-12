import { useState, useEffect } from 'react';
import { Container } from 'react-bootstrap';
import { productService } from '../../services/productService';
import { categoryService } from '../../services/categoryService';
import SectionHeader from '../common/SectionHeader';
import PaginatedProductGrid from '../product/PaginatedProductGrid';

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
    setProducts([]);
    const params = { sort: 'best_selling', per_page: 10 };
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

        <PaginatedProductGrid
          products={products}
          loading={loading}
          emptyMessage="No products found in this category."
        />
      </Container>
    </section>
  );
}