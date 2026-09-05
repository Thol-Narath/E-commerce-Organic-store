import api from './api';

export const testimonialService = {
  async getTestimonials() {
    const { data } = await api.get('/testimonials');
    return data.data?.items ?? data.data ?? [];
  },
};
