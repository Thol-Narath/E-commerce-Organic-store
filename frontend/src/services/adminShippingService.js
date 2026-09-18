import api from './api';

/**
 * Admin shipping method management. Full CRUD plus quick active/default
 * toggles. Admin controls which methods are offered at checkout and their
 * rates — React never prices an order.
 */
export const adminShippingService = {
  async list(params = {}) {
    const { data } = await api.get('/admin/shipping-methods', { params });
    return data.data;
  },

  async get(id) {
    const { data } = await api.get(`/admin/shipping-methods/${id}`);
    return data.data;
  },

  async create(payload) {
    const { data } = await api.post('/admin/shipping-methods', payload);
    return data.data;
  },

  async update(id, payload) {
    const { data } = await api.put(`/admin/shipping-methods/${id}`, payload);
    return data.data;
  },

  async remove(id) {
    const { data } = await api.delete(`/admin/shipping-methods/${id}`);
    return data.data;
  },

  async toggle(id) {
    const { data } = await api.patch(`/admin/shipping-methods/${id}/toggle`);
    return data.data;
  },

  async setDefault(id) {
    const { data } = await api.patch(`/admin/shipping-methods/${id}/default`);
    return data.data;
  },
};