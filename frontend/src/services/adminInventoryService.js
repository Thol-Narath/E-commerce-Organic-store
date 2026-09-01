import api from './api';

/**
 * Admin inventory management service (Phase 10). Stock overview, statistics,
 * per-product stock mutations and the admin-only movement ledger. Staff are
 * allowed by the backend to manage stock; the ledger endpoints return 403.
 */
export const adminInventoryService = {
  async list(params = {}) {
    const { data } = await api.get('/admin/inventory', { params });
    return data.data;
  },

  async getStatistics() {
    const { data } = await api.get('/admin/inventory/statistics');
    return data.data;
  },

  async get(id) {
    const { data } = await api.get(`/admin/inventory/${id}`);
    return data.data;
  },

  async getTransactions(id, params = {}) {
    const { data } = await api.get(`/admin/inventory/${id}/transactions`, { params });
    return data.data;
  },

  async addStock(id, quantity, reason = '') {
    const { data } = await api.post(`/admin/inventory/${id}/add`, {
      quantity,
      reason: reason || undefined,
    });
    return data.data;
  },

  async removeStock(id, quantity, reason) {
    const { data } = await api.post(`/admin/inventory/${id}/remove`, {
      quantity,
      reason,
    });
    return data.data;
  },

  async adjustStock(id, quantity, reason) {
    const { data } = await api.post(`/admin/inventory/${id}/adjust`, {
      quantity,
      reason,
    });
    return data.data;
  },

  async updateReorderLevel(id, reorderLevel) {
    const { data } = await api.patch(`/admin/inventory/${id}/reorder-level`, {
      reorder_level: reorderLevel,
    });
    return data.data;
  },
};