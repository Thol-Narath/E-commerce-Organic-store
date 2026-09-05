import api from './api';

/**
 * Customer-facing catalog service for categories.
 * All endpoints are public and require no authentication.
 */
export const categoryService = {
  /**
   * List active categories (with products_count).
   */
  async getCategories(params = {}) {
    const { data } = await api.get('/categories', { params });
    return data.data?.items ?? data.data ?? [];
  },

  /**
   * Public category detail by slug.
   */
  async getCategory(slug) {
    const { data } = await api.get(`/categories/${slug}`);
    return data.data;
  },

  /**
   * Active products belonging to a category (supports search/filter/sort/page).
   */
  async getCategoryProducts(slug, params = {}) {
    const { data } = await api.get(`/categories/${slug}/products`, { params });
    return data.data;
  },
};