import api from './api';

export const blogService = {
  async getBlogs(params = {}) {
    const { data } = await api.get('/blogs', { params });
    return data.data;
  },

  async getBlog(slug) {
    const { data } = await api.get(`/blogs/${slug}`);
    return data.data;
  },
};
