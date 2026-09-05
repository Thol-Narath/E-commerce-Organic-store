import api from './api';

/**
 * Admin category management service. All endpoints are admin-only.
 *
 * File uploads are sent as multipart/form-data via POST with Laravel's
 * `_method: PUT` spoofing (PHP does not populate $_FILES for PUT requests).
 * Text-only updates use plain JSON.
 */
export const adminCategoryService = {
  async list(params = {}) {
    const { data } = await api.get('/admin/categories', { params });
    return data.data?.items ?? data.data ?? [];
  },

  async get(id) {
    const { data } = await api.get(`/admin/categories/${id}`);
    return data.data;
  },

  async create(payload, icon = null) {
    if (icon instanceof File) {
      const form = buildForm(payload, icon);
      const { data } = await api.post('/admin/categories', form);
      return data.data;
    }
    const { data } = await api.post('/admin/categories', payload);
    return data.data;
  },

  async update(id, payload, icon = null) {
    if (icon instanceof File) {
      const form = buildForm(payload, icon);
      form.append('_method', 'PUT');
      const { data } = await api.post(`/admin/categories/${id}`, form);
      return data.data;
    }

    const { data } = await api.put(`/admin/categories/${id}`, payload);
    return data.data;
  },

  async remove(id) {
    const { data } = await api.delete(`/admin/categories/${id}`);
    return data.data;
  },
};

/**
 * Build a multipart form body from a payload plus an optional uploaded icon
 * File. The icon is appended only when it is a real File object.
 */
function buildForm(payload, icon) {
  const form = new FormData();
  Object.entries(payload).forEach(([key, value]) => {
    if (value !== undefined && value !== null) form.append(key, value);
  });
  if (icon instanceof File) {
    form.append('icon', icon);
  }
  return form;
}
