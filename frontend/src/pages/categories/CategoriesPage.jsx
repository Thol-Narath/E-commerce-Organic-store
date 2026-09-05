import { useEffect, useState } from 'react';
import { Container } from 'react-bootstrap';
import PageHeader from '../../components/common/PageHeader';
import CategoryGrid from '../../components/category/CategoryGrid';
import ErrorState from '../../components/common/ErrorState';
import EmptyState from '../../components/common/EmptyState';
import usePageTitle from '../../hooks/usePageTitle';
import { categoryService } from '../../services/categoryService';
import { getErrorMessage } from '../../utils/error';

export default function CategoriesPage() {
  usePageTitle('Categories');
  const [categories, setCategories] = useState(null);
  const [error, setError] = useState('');

  useEffect(() => {
    let cancelled = false;
    categoryService
      .getCategories()
      .then((data) => {
        if (!cancelled) setCategories(data || []);
      })
      .catch((err) => {
        if (!cancelled) setError(getErrorMessage(err));
      });
    return () => {
      cancelled = true;
    };
  }, []);

  return (
    <Container className="py-4">
      <PageHeader title="Categories" subtitle="Explore our range of organic product categories." />

      {error && <ErrorState message={error} />}

      {categories === null ? (
        <div className="row g-3 g-lg-4">
          {Array.from({ length: 8 }, (_, i) => (
            <div key={i} className="col-6 col-md-4 col-lg-3">
              <div className="category-card-modern h-100 d-flex flex-column align-items-center text-center p-4">
                <div className="category-icon-circle skeleton-shimmer mb-3" />
                <div className="skeleton-line skeleton-shimmer mb-2 w-50" />
                <div className="skeleton-line skeleton-shimmer w-75 mb-2" />
                <div className="skeleton-line skeleton-shimmer mt-auto w-25" style={{ height: '1.5rem', borderRadius: '999px' }} />
              </div>
            </div>
          ))}
        </div>
      ) : categories.length === 0 ? (
        <EmptyState
          title="No categories available"
          message="Categories will appear here as soon as the store adds them."
          actionLabel="View all products"
          actionTo="/shop"
        />
      ) : (
        <CategoryGrid categories={categories} />
      )}
    </Container>
  );
}