import { useMemo } from 'react';
import { Pagination } from 'react-bootstrap';

function pageWindow(current, last) {
  if (last <= 7) {
    return Array.from({ length: last }, (_, i) => i + 1);
  }

  const pages = new Set([1, last, current - 1, current, current + 1]);
  const result = [];
  let previous = 0;

  Array.from(pages)
    .filter((p) => p >= 1 && p <= last)
    .sort((a, b) => a - b)
    .forEach((p) => {
      if (p - previous > 1) result.push('ellipsis');
      result.push(p);
      previous = p;
    });

  return result;
}

/**
 * Server-side pagination control with first/last handling and a safe page
 * window so very large page counts do not flood the DOM.
 */
export default function ProductPagination({ pagination = {}, onPageChange, disabled }) {
  const { current_page: current = 1, last_page: last = 1 } = pagination;

  const items = useMemo(() => pageWindow(current, last), [current, last]);

  if (current <= 0 || last <= 1) return null;

  return (
    <nav aria-label="Product pagination" className="d-flex justify-content-center mt-4">
      <Pagination className="mb-0" disabled={disabled}>
        <Pagination.Prev
          onClick={() => onPageChange(current - 1)}
          disabled={disabled || current <= 1}
          aria-label="Previous page"
        />

        {items.map((item, index) =>
          item === 'ellipsis' ? (
            <Pagination.Ellipsis key={`ellipsis-${index}`} disabled />
          ) : (
            <Pagination.Item
              key={item}
              active={item === current}
              onClick={() => onPageChange(item)}
              disabled={disabled}
              aria-label={`Go to page ${item}`}
            >
              {item}
            </Pagination.Item>
          )
        )}

        <Pagination.Next
          onClick={() => onPageChange(current + 1)}
          disabled={disabled || current >= last}
          aria-label="Next page"
        />
      </Pagination>
    </nav>
  );
}