import { Button, Col, Row } from 'react-bootstrap';
import ProductSearch from './ProductSearch';
import ProductSort from './ProductSort';
import ProductCategoryFilter from './ProductCategoryFilter';
import ProductPriceFilter from './ProductPriceFilter';

/**
 * Composable, responsive filter bar used by the shop and category pages.
 * All filter values live in the URL; this bar only emits change events.
 */
export default function ProductFiltersBar({
  categories = [],
  searchInput,
  onSearchInputChange,
  onSubmitSearch,
  onClearSearch,
  sort,
  onSortChange,
  categoryId,
  onCategoryChange,
  minPrice,
  maxPrice,
  onMinPriceChange,
  onMaxPriceChange,
  onReset,
  hasActiveFilters,
  disabled,
}) {
  return (
    <section className="product-filters-bar border rounded-3 bg-white p-3 p-md-4 mb-4" aria-label="Product filters">
      <Row className="g-3 align-items-end">
        <Col lg={4} xl={5}>
          <ProductSearch
            value={searchInput}
            onChange={onSearchInputChange}
            onSubmit={onSubmitSearch}
            onClear={onClearSearch}
            disabled={disabled}
          />
        </Col>
        <Col xs={12} sm={6} md={4} lg={2}>
          <ProductCategoryFilter
            categories={categories}
            value={categoryId}
            onChange={onCategoryChange}
            disabled={disabled}
          />
        </Col>
        <Col xs={12} sm={6} md={4} lg={2}>
          <ProductSort value={sort} onChange={onSortChange} disabled={disabled} />
        </Col>
        <Col xs={12} md={4} lg={4} xl={3}>
          <div className="d-flex gap-2">
            <ProductPriceFilter
              minValue={minPrice}
              maxValue={maxPrice}
              onMinChange={onMinPriceChange}
              onMaxChange={onMaxPriceChange}
              disabled={disabled}
            />
          </div>
        </Col>
      </Row>

      {hasActiveFilters && (
        <div className="d-flex justify-content-end mt-3">
          <Button variant="link" className="text-decoration-none p-0 text-danger" onClick={onReset}>
            Reset all filters
          </Button>
        </div>
      )}
    </section>
  );
}