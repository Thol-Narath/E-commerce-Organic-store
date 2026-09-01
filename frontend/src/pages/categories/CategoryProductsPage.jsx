import { useCallback, useEffect, useState } from 'react';
import { Link, useParams, useSearchParams } from 'react-router-dom';
import { Breadcrumb, Col, Container, Row } from 'react-bootstrap';
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
import { normalizeError } from '../../services/api';

export default function CategoryProductsPage() {
  const { slug } = useParams();
  const [searchParams, setSearchParams] = useSearchParams();

  const [category, setCategory] = useState(null);
  const [categoryLoading, setCategoryLoading] = useState(true);
  const [categoryError, setCategoryError] = useState('');
  const [notFound, setNotFound] = useState(false);

  usePageTitle(category?.name ? `${category.name} products` : 'Category');

  useEffect(() => {
    let cancelled = false;
    setCategoryLoading(true);
    setCategoryError('');
    setNotFound(false);
    setCategory(null);

    categoryService
      .getCategory(slug)
      .then((data) => {
        if (!cancelled) setCategory(data);
      })
      .catch((err) => {
        if (cancelled) return;
        const apiError = normalizeError(err);
        setCategoryError(apiError.message);
        if (apiError.status === 404) setNotFound(true);
      })
      .finally(() => {
        if (!cancelled) setCategoryLoading(false);
      });

    return () => {
      cancelled = true;
    };
  }, [slug]);

  const fetchFn = useCallback(
    (params) => categoryService.getCategoryProducts(slug, params),
    [slug]
  );

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
    setParam,
    setPage,
    applySearch,
    clearSearch,
    resetFilters,
    hasActiveFilters,
  } = useProductQuery({ searchParams, setSearchParams, fetchFn });

  if (categoryLoading) {
    return (
      <Container className="py-4">
        <ProductSkeletons count={8} />
      </Container>
    );
  }

  if (notFound || (!category && categoryError)) {
    return (
      <Container className="py-5">
        <EmptyState
          title="Category Not Found"
          message="The category you are looking for does not exist or is no longer available."
          actionLabel="Browse all categories"
          actionTo="/categories"
        />
      </Container>
    );
  }

  if (categoryError) {
    return (
      <Container className="py-5">
        <ErrorState message={categoryError} onRetry={() => window.location.reload()} />
      </Container>
    );
  }

  const items = products?.items || [];
  const pagination = products?.pagination || {};
  const count = category?.products_count ?? pagination.total ?? null;

  return (
    <Container className="py-4">
      <Breadcrumb className="mb-3">
        <Breadcrumb.Item linkAs={Link} linkProps={{ to: '/' }}>Home</Breadcrumb.Item>
        <Breadcrumb.Item linkAs={Link} linkProps={{ to: '/categories' }}>Categories</Breadcrumb.Item>
        <Breadcrumb.Item active>{category.name}</Breadcrumb.Item>
      </Breadcrumb>

      <PageHeader
        title={category.name}
        subtitle={
          count !== null
            ? count > 0
              ? `${count} product${count > 1 ? 's' : ''} in this category`
              : 'No products in this category yet'
            : undefined
        }
      />
      {category.description && <p className="text-muted mb-4">{category.description}</p>}

      <ProductFiltersBar
        categories={[]}
        searchInput={searchInput}
        onSearchInputChange={setSearchInput}
        onSubmitSearch={applySearch}
        onClearSearch={clearSearch}
        sort={sort}
        onSortChange={(value) => setParam('sort', value)}
        categoryId=""
        onCategoryChange={() => {}}
        minPrice={minPrice}
        maxPrice={maxPrice}
        onMinPriceChange={(value) => setParam('min_price', value)}
        onMaxPriceChange={(value) => setParam('max_price', value)}
        onReset={resetFilters}
        hasActiveFilters={hasActiveFilters}
        disabled={loading}
      />

      {error && (
        <Row className="mb-3">
          <Col>
            <ErrorState message={error} />
          </Col>
        </Row>
      )}

      {loading ? (
        <ProductSkeletons count={8} />
      ) : items.length === 0 ? (
        <EmptyState
          title="No products found"
          message="Try changing your search, or browse the full catalog instead."
          actionLabel={hasActiveFilters ? 'Clear filters' : 'Browse all products'}
          onAction={hasActiveFilters ? resetFilters : undefined}
          actionTo={hasActiveFilters ? undefined : '/shop'}
        />
      ) : (
        <>
          <ProductGrid products={items} />
          <ProductPagination pagination={pagination} onPageChange={setPage} disabled={loading} />
        </>
      )}
    </Container>
  );
}