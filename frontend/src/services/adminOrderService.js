import api from './api';

/**
 * Admin order management service (Phase 9). All endpoints are admin-only, with
 * a couple of staff-readable routes; the frontend admin section is admin-only.
 */
export const adminOrderService = {
  async list(params = {}) {
    const { data } = await api.get('/admin/orders', { params });
    return data.data;
  },

  async get(id) {
    const { data } = await api.get(`/admin/orders/${id}`);
    return data.data;
  },

  async getStatistics() {
    const { data } = await api.get('/admin/orders/statistics');
    return data.data;
  },

  async getRevenueTrend(days = 30) {
    const { data } = await api.get('/admin/dashboard/revenue-trend', { params: { days } });
    return data.data;
  },

  async updateStatus(id, status, note = '') {
    const { data } = await api.patch(`/admin/orders/${id}/status`, { status, note });
    return data.data;
  },

  async cancel(id, note = '') {
    const { data } = await api.post(`/admin/orders/${id}/cancel`, { note });
    return data.data;
  },

  async addNote(id, note) {
    const { data } = await api.post(`/admin/orders/${id}/notes`, { note });
    return data.data;
  },
};