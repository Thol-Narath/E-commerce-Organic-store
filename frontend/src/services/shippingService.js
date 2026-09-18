import api from './api';

/**
 * Public shipping method service. Returns the active methods offered at
 * checkout for display/selection only — the fee is always recomputed
 * server-side when the order is placed.
 */
export const shippingService = {
  /**
   * Active shipping methods, default first: [{ id, name, code, description,
   * base_rate, free_over, estimated_days }].
   */
  async list() {
    const { data } = await api.get('/shipping-methods');
    return data.data.methods ?? [];
  },

  /**
   * Display-only estimate of the shipping fee for a method given a subtotal.
   * Mirrors the backend rule: subtotal >= free_over means free shipping.
   */
  feeFor(method, subtotal) {
    if (!method) return '0.00';
    const freeOver = Number(method.free_over);
    if (method.free_over !== null && method.free_over !== '' && subtotal >= freeOver) {
      return '0.00';
    }
    return Number(method.base_rate ?? 0).toFixed(2);
  },
};