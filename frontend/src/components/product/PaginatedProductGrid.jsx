import { useEffect, useState } from 'react';
import { Row } from 'react-bootstrap';
import HomeProductCard from './HomeProductCard';
import StorePagination from '../common/StorePagination';

/**
 * Returns the number of visible product columns for the current viewport,
 * matching Bootstrap's col-6 / col-md-4 / col-lg-3 breakpoints.
 */
function useVisibleColumns() {
  const getColumns = () => {
    const width = window.innerWidth;
    if (width >= 992) return 4;
    if (width >= 768) return 3;
    return 2;
  };

  const [columns, setColumns] = useState(getColumns);

  useEffect(() => {
    const onResize = () => setColumns(getColumns());
    window.addEventListener('resize', onResize);
    return () => window.removeEventListener('resize', onResize);
  }, []);

  return columns;
}

/**
 * Responsive product grid that always shows exactly two rows of cards and
 * paginates the rest. Each page holds columns × 2 products, so desktop shows
 * 8 (4 × 2), tablet 6 (3 × 2) and mobile 4 (2 × 2).
 */
export default function PaginatedProductGrid({
  products = [],
  loading = false,
  emptyMessage = 'No products available yet.',
}) {
  const columns = useVisibleColumns();
  const pageSize = columns * 2;
  const totalPages = Math.max(1, Math.ceil(products.length / pageSize));
  const [page, setPage] = useState(1);

  useEffect(() => {
    setPage(1);
  }, [products]);

  useEffect(() => {
    if (page > totalPages) setPage(totalPages);
  }, [totalPages, page]);

  if (loading) {
    return (
      <Row className="g-3 g-lg-4">
        {Array.from({ length: pageSize }, (_, i) => (
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
      </Row>
    );
  }

  if (products.length === 0) {
    return <p className="text-muted text-center">{emptyMessage}</p>;
  }

  const start = (page - 1) * pageSize;
  const pageItems = products.slice(start, start + pageSize);

  return (
    <>
      <Row className="g-3 g-lg-4">
        {pageItems.map((product) => (
          <div key={product.id} className="col-6 col-md-4 col-lg-3">
            <HomeProductCard product={product} />
          </div>
        ))}
      </Row>

      {totalPages > 1 && (
        <StorePagination
          pagination={{ current_page: page, last_page: totalPages, per_page: pageSize, total: products.length }}
          onPageChange={setPage}
          ariaLabel="Product pages"
        />
      )}
    </>
  );
}