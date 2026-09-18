import api from './api';

export const contactService = {
  async sendMessage({ name, email, subject, message }) {
    const { data } = await api.post('/contact', { name, email, subject, message });
    return data;
  },
};