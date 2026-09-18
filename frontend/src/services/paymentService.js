import api from './api';

/**
 * Payment (Phase 8) service. The React client only sends the chosen payment
 * method and currency; the Laravel backend creates the gateway transaction,
 * holds the merchant keys, computes the amount (including KHR conversion) and
 * verifies every payment (webhook / check-transaction). React receives display
 * data only (QR string, deeplink, hosted checkout URL).
 */
export const paymentService = {
  /**
   * The payment methods + currencies the store currently accepts
   * (backend-enabled and derived from server config).
   */
  async methods() {
    const { data } = await api.get('/payment-methods');
    const payload = data.data || {};
    return {
      methods: payload.methods || [],
      currencies: payload.currencies || [],
    };
  },

  /**
   * Start a payment attempt for the pending order in the chosen currency
   * ('USD' or 'KHR'). Returns the payment resource plus order number/status.
   */
  async create(orderNumber, paymentMethod, currency = 'USD') {
    const { data } = await api.post(`/orders/${orderNumber}/payments`, {
      payment_method: paymentMethod,
      currency,
    });
    return data.data;
  },

  /**
   * Current status of the order + its latest payment attempt.
   * Lightweight, does not touch PayWay — safe to poll.
   */
  async status(orderNumber) {
    const { data } = await api.get(`/orders/${orderNumber}/payment-status`);
    return data.data;
  },

  /**
   * Ask the backend to re-check the pending attempt against PayWay
   * (check-transaction-2). Used on demand, not for hot polling.
   */
  async refresh(orderNumber, paymentId) {
    const { data } = await api.post(`/orders/${orderNumber}/payments/${paymentId}/refresh`);
    return data.data;
  },
};