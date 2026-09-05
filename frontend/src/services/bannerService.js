import api from './api';

export const bannerService = {
  async getBanners() {
    const { data } = await api.get('/banners');
    return data.data?.items ?? data.data ?? [];
  },
};
