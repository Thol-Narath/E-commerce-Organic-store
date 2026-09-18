import StorePagination from '../common/StorePagination';

/**
 * Server-side pagination control for product listings. Thin wrapper around the
 * shared StorePagination (smart page window + first/prev/next/last). Count text
 * is omitted here because product pages already render their own result count.
 */
export default function ProductPagination({ pagination = {}, onPageChange, disabled }) {
  return (
    <div className="product-pagination-wrap">
      <StorePagination
        pagination={pagination}
        onPageChange={onPageChange}
        disabled={disabled}
        showInfo={false}
        ariaLabel="Product pagination"
      />
    </div>
  );
}