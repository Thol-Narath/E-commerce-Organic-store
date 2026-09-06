import api from './api';

/**
 * Customer order service. The backend enforces ownership (orders are always
 * scoped to the authenticated user) and all totals come from stored values.
 */
export const orderService = {
  /**
   * The customer's order history, newest first, paginated.
   */
  async list(page = 1, perPage = 10) {
    const { data } = await api.get('/orders', { params: { page, per_page: perPage } });
    return data.data;
  },

  /**
   * A single order resolved by its human-readable order number.
   */
  async get(orderNumber) {
    const { data } = await api.get(`/orders/${orderNumber}`);
    return data.data;
  },

  /**
   * Cancel a customer's own order (only allowed while cancellable).
   */
  async cancel(orderNumber) {
    const { data } = await api.post(`/orders/${orderNumber}/cancel`);
    return data.data;
  },
};