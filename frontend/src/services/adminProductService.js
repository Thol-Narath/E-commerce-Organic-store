import api from './api';

/**
 * Admin product management service. All endpoints are admin-only.
 */
export const adminProductService = {
  async list(params = {}) {
    const { data } = await api.get('/admin/products', { params });
    return data.data;
  },

  async get(id) {
    const { data } = await api.get(`/admin/products/${id}`);
    return data.data;
  },

  async create(payload) {
    const { data } = await api.post('/admin/products', payload);
    return data.data;
  },

  async update(id, payload) {
    const { data } = await api.put(`/admin/products/${id}`, payload);
    return data.data;
  },

  async remove(id) {
    const { data } = await api.delete(`/admin/products/${id}`);
    return data.data;
  },

  async updateStatus(id, status) {
    const { data } = await api.patch(`/admin/products/${id}/status`, { status });
    return data.data;
  },

  async updateFeatured(id, isFeatured) {
    const { data } = await api.patch(`/admin/products/${id}/featured`, { is_featured: isFeatured });
    return data.data;
  },

  async uploadImage(id, image, altText = '') {
    const form = new FormData();
    form.append('image', image);
    if (altText) form.append('alt_text', altText);
    const { data } = await api.post(`/admin/products/${id}/images`, form);
    return data.data;
  },

  async setPrimaryImage(productId, imageId) {
    const { data } = await api.post(`/admin/products/${productId}/images/${imageId}/primary`);
    return data.data;
  },

  async deleteImage(productId, imageId) {
    const { data } = await api.delete(`/admin/products/${productId}/images/${imageId}`);
    return data.data;
  },
};
