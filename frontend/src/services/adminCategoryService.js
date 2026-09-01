import api from './api';

/**
 * Admin category management service. All endpoints are admin-only.
 */
export const adminCategoryService = {
  async list() {
    const { data } = await api.get('/admin/categories');
    return data.data;
  },

  async get(id) {
    const { data } = await api.get(`/admin/categories/${id}`);
    return data.data;
  },

  async create(payload, icon = null) {
    const { data } = await api.post('/admin/categories', this.toBody(payload, icon));
    return data.data;
  },

  async update(id, payload, icon = null) {
    const { data } = await api.put(`/admin/categories/${id}`, this.toBody(payload, icon));
    return data.data;
  },

  async remove(id) {
    const { data } = await api.delete(`/admin/categories/${id}`);
    return data.data;
  },

  /**
   * Build the request body. A file icon requires multipart/form-data so that
   * the uploaded image reaches the backend reliably.
   */
  toBody(payload, icon) {
    if (!icon) return payload;

    const form = new FormData();
    Object.entries(payload).forEach(([key, value]) => {
      if (value !== undefined && value !== null) form.append(key, value);
    });
    form.append('icon', icon);
    return form;
  },
};
