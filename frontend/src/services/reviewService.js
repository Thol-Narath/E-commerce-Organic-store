import api from './api';

/**
 * Customer-facing review/rating service.
 * Customers can rate a product only after they purchased it (backend enforces).
 */
export const reviewService = {
  /**
   * Rate a product (1-5 stars). Upserts the customer's own rating.
   */
  async rate(productId, rating) {
    const { data } = await api.post('/reviews', {
      product_id: productId,
      rating: Math.round(rating),
    });
    return data.data;
  },
};