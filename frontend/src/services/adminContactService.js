import api from './api';

/**
 * Admin contact inbox service. All endpoints are admin-only.
 */
export const adminContactService = {
  async list(params = {}) {
    const { data } = await api.get('/admin/contact-messages', { params });
    return data.data;
  },

  async get(id) {
    const { data } = await api.get(`/admin/contact-messages/${id}`);
    return data.data?.messages ?? data.data;
  },

  async markRead(id, read = true) {
    const { data } = await api.patch(`/admin/contact-messages/${id}/read`, { read });
    return data.data;
  },

  async reply(id, reply) {
    const { data } = await api.post(`/admin/contact-messages/${id}/reply`, { reply });
    return data;
  },

  async remove(id) {
    const { data } = await api.delete(`/admin/contact-messages/${id}`);
    return data.data;
  },
};