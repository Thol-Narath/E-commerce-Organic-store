import { useState, useEffect } from 'react';
import { Container } from 'react-bootstrap';
import { productService } from '../../services/productService';
import SectionHeader from '../common/SectionHeader';
import PaginatedProductGrid from '../product/PaginatedProductGrid';

export default function BestSellingProducts() {
  const [products, setProducts] = useState([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    let cancelled = false;
    productService.getProducts({ sort: 'best_selling', per_page: 10 }).then((data) => {
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

        <PaginatedProductGrid products={products} loading={loading} />
      </Container>
    </section>
  );
}