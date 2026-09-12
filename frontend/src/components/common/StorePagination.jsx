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

function rangeText(pagination) {
  const { current_page = 1, per_page = 0, last_page = 1, total = 0 } = pagination;
  if (!total || last_page <= 1) return null;
  const start = (current_page - 1) * per_page + 1;
  const end = Math.min(current_page * per_page, total);
  return `Showing ${start}${end > start ? `–${end}` : ''} of ${total}`;
}

/**
 * Shared server-side pagination control. Renders first/prev/next/last arrows,
 * a smart page window (never floods the DOM on huge page counts) and an
 * optional "Showing X–Y of Z" summary line. Styled with .store-pagination.
 */
export default function StorePagination({
  pagination = {},
  onPageChange,
  disabled = false,
  showInfo = true,
  ariaLabel = 'Pagination',
}) {
  const { current_page: current = 1, last_page: last = 1 } = pagination;

  const items = useMemo(() => pageWindow(current, last), [current, last]);
  const info = rangeText(pagination);

  if (last <= 1) return null;

  const handle = (target) => {
    if (!disabled && target >= 1 && target <= last && target !== current) {
      onPageChange?.(target);
    }
  };

  return (
    <nav aria-label={ariaLabel} className="store-pagination-wrap">
      <Pagination className="store-pagination mb-0" disabled={disabled}>
        <Pagination.First
          onClick={() => handle(1)}
          disabled={disabled || current <= 1}
          aria-label="Go to first page"
        />
        <Pagination.Prev
          onClick={() => handle(current - 1)}
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
              onClick={() => handle(item)}
              disabled={disabled}
              aria-label={`Go to page ${item}`}
            >
              {item}
            </Pagination.Item>
          )
        )}

        <Pagination.Next
          onClick={() => handle(current + 1)}
          disabled={disabled || current >= last}
          aria-label="Next page"
        />
        <Pagination.Last
          onClick={() => handle(last)}
          disabled={disabled || current >= last}
          aria-label="Go to last page"
        />
      </Pagination>

      {showInfo && info && <p className="store-pagination-info mb-0">{info}</p>}
    </nav>
  );
}