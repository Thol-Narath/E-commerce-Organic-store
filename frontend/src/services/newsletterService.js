import api from './api';

export const newsletterService = {
  async subscribe(email) {
    const { data } = await api.post('/newsletter/subscribe', { email });
    return data;
  },
};
