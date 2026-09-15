import api from './api';

/**
 * Admin supplier management service. Full CRUD for suppliers plus purchase
 * orders used to restock products (especially low-stock items) from a supplier.
 */
export const adminSupplierService = {
  async list(params = {}) {
    const { data } = await api.get('/admin/suppliers', { params });
    return data.data;
  },

  async get(id) {
    const { data } = await api.get(`/admin/suppliers/${id}`);
    return data.data;
  },

  async options() {
    const { data } = await api.get('/admin/suppliers/options');
    return data.data ?? [];
  },

  async create(payload) {
    const { data } = await api.post('/admin/suppliers', payload);
    return data.data;
  },

  async update(id, payload) {
    const { data } = await api.put(`/admin/suppliers/${id}`, payload);
    return data.data;
  },

  async remove(id) {
    const { data } = await api.delete(`/admin/suppliers/${id}`);
    return data.data;
  },

  async toggle(id) {
    const { data } = await api.patch(`/admin/suppliers/${id}/toggle`);
    return data.data;
  },

  async listOrders(params = {}) {
    const { data } = await api.get('/admin/supplier-orders', { params });
    return data.data;
  },

  async getOrder(id) {
    const { data } = await api.get(`/admin/supplier-orders/${id}`);
    return data.data;
  },

  async createOrder(payload) {
    const { data } = await api.post('/admin/supplier-orders', payload);
    return data.data;
  },

  async placeOrder(id, payload = {}) {
    const { data } = await api.post(`/admin/supplier-orders/${id}/place`, payload);
    return data.data;
  },

  async receiveOrder(id, received = {}) {
    const { data } = await api.post(`/admin/supplier-orders/${id}/receive`, { received });
    return data.data;
  },

  async cancelOrder(id, notes = '') {
    const { data } = await api.post(`/admin/supplier-orders/${id}/cancel`, { notes: notes || undefined });
    return data.data;
  },
};