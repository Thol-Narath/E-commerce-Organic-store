import api from './api';

/**
 * Customer-facing catalog service for products.
 * All endpoints are public and require no authentication.
 */
export const productService = {
  /**
   * List active products with optional search/filter/sort/pagination.
   * Supported params: search, category_id, category_slug, min_price,
   * max_price, featured, best_seller, discounted, sort, page, per_page.
   */
  async getProducts(params = {}) {
    const { data } = await api.get('/products', { params });
    return data.data;
  },

  /**
   * Featured active products.
   */
  async getFeaturedProducts(params = {}) {
    const { data } = await api.get('/products/featured', { params });
    return data.data;
  },

  /**
   * Public detail of an active product (resolves by slug or id).
   */
  async getProduct(slug) {
    const { data } = await api.get(`/products/${slug}`);
    return data.data;
  },
};