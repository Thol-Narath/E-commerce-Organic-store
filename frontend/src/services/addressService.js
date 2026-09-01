import api from './api';

/**
 * Customer shipping-address service. The backend enforces ownership
 * (cross-user access returns 404) and default-address rules.
 */
export const addressService = {
  /** All of the authenticated customer's addresses, default first. */
  async list() {
    const { data } = await api.get('/addresses');
    return data.data;
  },

  async get(id) {
    const { data } = await api.get(`/addresses/${id}`);
    return data.data;
  },

  async create(payload) {
    const { data } = await api.post('/addresses', payload);
    return data.data;
  },

  async update(id, payload) {
    const { data } = await api.patch(`/addresses/${id}`, payload);
    return data.data;
  },

  async remove(id) {
    await api.delete(`/addresses/${id}`);
  },

  /** Promote this address to the user's default (others are cleared). */
  async setDefault(id) {
    const { data } = await api.patch(`/addresses/${id}/default`);
    return data.data;
  },
};