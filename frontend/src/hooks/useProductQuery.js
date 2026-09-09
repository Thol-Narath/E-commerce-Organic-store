import { useEffect, useMemo, useState } from 'react';
import { productService } from '../services/productService';
import { getErrorMessage } from '../utils/error';
import { buildParams, cleanParams } from '../utils/queryState';

const DEFAULT_SORT = 'newest';
const DEFAULT_PER_PAGE = 12;

/**
 * Shared logic for product listing pages that mirror filters in the URL.
 *
 * Reads `search`, `category_id`, `min_price`, `max_price`, `sort` and `page`
 * from `searchParams` and keeps them in sync when the user changes a filter.
 *
 * @param {URLSearchParams} searchParams   From useSearchParams().
 * @param {Function} setSearchParams       From useSearchParams().
 * @param {Function} [fetchFn]             Override the HTTP call (used for category pages).
 * @param {Object} [baseParams]            Extra fixed params merged into every request.
 */
export function useProductQuery({ searchParams, setSearchParams, fetchFn, baseParams = {} }) {
  const search = searchParams.get('search') || '';
  const categoryId = searchParams.get('category_id') || '';
  const minPrice = searchParams.get('min_price') || '';
  const maxPrice = searchParams.get('max_price') || '';
  const sort = searchParams.get('sort') || DEFAULT_SORT;
  const page = useMemo(() => {
    const raw = Number.parseInt(searchParams.get('page') || '1', 10);
    return Number.isNaN(raw) || raw < 1 ? 1 : raw;
  }, [searchParams]);

  // Mirror the URL search value into a locally-editable input box.
  const [searchInput, setSearchInput] = useState(search);
  useEffect(() => {
    setSearchInput(search);
  }, [search]);

  const [products, setProducts] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    let cancelled = false;
    setLoading(true);
    setError('');

    const params = cleanParams({
      ...baseParams,
      search: search || undefined,
      category_id: categoryId || undefined,
      min_price: minPrice || undefined,
      max_price: maxPrice || undefined,
      sort,
      page,
      per_page: DEFAULT_PER_PAGE,
    });

    const request = fetchFn || productService.getProducts;

    request(params)
      .then((data) => {
        if (!cancelled) setProducts(data);
      })
      .catch((err) => {
        if (!cancelled) setError(getErrorMessage(err));
      })
      .finally(() => {
        if (!cancelled) setLoading(false);
      });

    return () => {
      cancelled = true;
    };
  }, [search, categoryId, minPrice, maxPrice, sort, page, fetchFn, baseParams]);

  const setParam = (key, value) => {
    setSearchParams(buildParams(searchParams, key, value), { replace: true });
  };

  const setPage = (pageNumber) => {
    setParam('page', pageNumber === 1 ? '' : String(pageNumber));
  };

  const applySearch = (e) => {
    if (e && e.preventDefault) e.preventDefault();
    setParam('search', searchInput.trim());
  };

  const clearSearch = () => {
    setSearchInput('');
    setParam('search', '');
  };

  const resetFilters = () => {
    setSearchInput('');
    setSearchParams({}, { replace: true });
  };

  const hasActiveFilters = Boolean(search || categoryId || minPrice || maxPrice);

  return {
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
  };
}