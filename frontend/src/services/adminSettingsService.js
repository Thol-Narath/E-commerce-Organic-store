import api from './api';

export const adminSettingsService = {
  async getLogo() {
    const { data } = await api.get('/admin/settings/logo');
    return data.data;
  },

  async uploadLogo(file) {
    const formData = new FormData();
    formData.append('logo', file);
    const { data } = await api.post('/admin/settings/logo', formData);
    return data.data;
  },

  async removeLogo() {
    const { data } = await api.delete('/admin/settings/logo');
    return data.data;
  },

  async updateStoreBranding(payload) {
    const { data } = await api.put('/admin/settings/store-branding', payload);
    return data.data;
  },

  async updateContact(payload) {
    const { data } = await api.put('/admin/settings/contact', payload);
    return data.data;
  },
};