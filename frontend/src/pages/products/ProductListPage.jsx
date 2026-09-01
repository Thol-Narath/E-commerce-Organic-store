import { useEffect, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import { Col, Container, Row } from 'react-bootstrap';
import PageHeader from '../../components/common/PageHeader';
import ProductFiltersBar from '../../components/product/ProductFiltersBar';
import ProductGrid from '../../components/product/ProductGrid';
import ProductSkeletons from '../../components/product/ProductSkeletons';
import ProductPagination from '../../components/product/ProductPagination';
import EmptyState from '../../components/common/EmptyState';
import ErrorState from '../../components/common/ErrorState';
import usePageTitle from '../../hooks/usePageTitle';
import { useProductQuery } from '../../hooks/useProductQuery';
import { categoryService } from '../../services/categoryService';
import { getErrorMessage } from '../../utils/error';

export default function ProductListPage() {
  usePageTitle('Shop');
  const [searchParams, setSearchParams] = useSearchParams();

  const [categories, setCategories] = useState([]);
  const [categoriesError, setCategoriesError] = useState('');

  useEffect(() => {
    let cancelled = false;
    categoryService
      .getCategories()
      .then((data) => {
        if (!cancelled) setCategories(data || []);
      })
      .catch((err) => {
        if (!cancelled) setCategoriesError(getErrorMessage(err));
      });
    return () => {
      cancelled = true;
    };
  }, []);

  const {
    products,
    loading,
    error,
    page,
    sort,
    search,
    searchInput,
    setSearchInput,
    minPrice,
    maxPrice,
    categoryId,
    setParam,
    setPage,
    applySearch,
    clearSearch,
    resetFilters,
    hasActiveFilters,
  } = useProductQuery({ searchParams, setSearchParams });

  const items = products?.items || [];
  const pagination = products?.pagination || {};

  return (
    <Container className="py-4">
      <PageHeader title="Shop" subtitle="Browse the full range of organic products." />

      {categoriesError && (
        <Row className="mb-3">
          <Col>
            <ErrorState message={categoriesError} />
          </Col>
        </Row>
      )}

      <ProductFiltersBar
        categories={categories}
        searchInput={searchInput}
        onSearchInputChange={setSearchInput}
        onSubmitSearch={applySearch}
        onClearSearch={clearSearch}
        sort={sort}
        onSortChange={(value) => setParam('sort', value)}
        categoryId={categoryId}
        onCategoryChange={(value) => setParam('category_id', value)}
        minPrice={minPrice}
        maxPrice={maxPrice}
        onMinPriceChange={(value) => setParam('min_price', value)}
        onMaxPriceChange={(value) => setParam('max_price', value)}
        onReset={resetFilters}
        hasActiveFilters={hasActiveFilters}
        disabled={loading}
      />

      {error && <ErrorState message={error} />}

      {loading ? (
        <ProductSkeletons count={8} />
      ) : items.length === 0 ? (
        <EmptyState
          title="No products found"
          message="Try changing your search or filters, or clear them to see the full catalog."
          actionLabel={hasActiveFilters ? 'Clear filters' : undefined}
          onAction={hasActiveFilters ? resetFilters : undefined}
        />
      ) : (
        <>
          <p className="text-muted small mb-3" role="status">
            {search
              ? `${pagination.total || items.length} result${(pagination.total || items.length) > 1 ? 's' : ''} for "${search}"`
              : `${pagination.total || items.length} product${(pagination.total || items.length) > 1 ? 's' : ''}`}
          </p>
          <ProductGrid products={items} />
          <ProductPagination
            pagination={pagination}
            onPageChange={setPage}
            disabled={loading}
          />
        </>
      )}
    </Container>
  );
}