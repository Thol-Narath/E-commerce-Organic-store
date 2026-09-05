import api from './api';

export const adminBannerService = {
  async list(params = {}) {
    const { data } = await api.get('/admin/banners', { params });
    return data.data?.items ?? data.data ?? [];
  },

  async get(id) {
    const { data } = await api.get(`/admin/banners/${id}`);
    return data.data;
  },

  async create(payload, image = null) {
    if (image instanceof File) {
      const form = new FormData();
      Object.entries(payload).forEach(([key, value]) => {
        if (value !== undefined && value !== null) form.append(key, value);
      });
      form.append('image', image);
      const { data } = await api.post('/admin/banners', form);
      return data.data;
    }
    const { data } = await api.post('/admin/banners', payload);
    return data.data;
  },

  async update(id, payload, image = null) {
    if (image instanceof File) {
      const form = new FormData();
      Object.entries(payload).forEach(([key, value]) => {
        if (value !== undefined && value !== null) form.append(key, value);
      });
      form.append('_method', 'PUT');
      form.append('image', image);
      const { data } = await api.post(`/admin/banners/${id}`, form);
      return data.data;
    }

    const { data } = await api.put(`/admin/banners/${id}`, payload);
    return data.data;
  },

  async remove(id) {
    const { data } = await api.delete(`/admin/banners/${id}`);
    return data.data;
  },

  async toggle(id) {
    const { data } = await api.patch(`/admin/banners/${id}/toggle`);
    return data.data;
  },

  async reorder(orders) {
    const { data } = await api.post('/admin/banners/reorder', { orders });
    return data.data;
  },
};
