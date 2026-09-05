import api from './api';

export const statsService = {
  async getStats() {
    const { data } = await api.get('/stats');
    return data.data;
  },
};
