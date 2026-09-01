import api from './api';

/**
 * Payment (Phase 8) service. The React client only ever sends the chosen
 * payment method; the Laravel backend creates the PayWay transaction, holds
 * the merchant keys, and verifies every payment (webhook / check-transaction).
 * React receives display data only (QR string, deeplink, hosted checkout URL).
 */
export const paymentService = {
  /**
   * The payment methods the store currently accepts (backend-enabled).
   */
  async methods() {
    const { data } = await api.get('/payment-methods');
    return data.data.methods || [];
  },

  /**
   * Start a payment attempt for the pending order.
   * Returns the payment resource plus order number/status.
   */
  async create(orderNumber, paymentMethod) {
    const { data } = await api.post(`/orders/${orderNumber}/payments`, { payment_method: paymentMethod });
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