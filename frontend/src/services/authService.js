import api, { getToken, setToken } from './api';

/**
 * Authentication API service.
 * All calls rely on the shared Axios instance (token injection + envelope).
 */
export const authService = {
  async register({ name, email, phone, password, password_confirmation }) {
    const { data } = await api.post('/auth/register', {
      name,
      email,
      phone,
      password,
      password_confirmation,
    });
    setToken(data.data.token);
    return data.data;
  },

  async login({ email, password }) {
    const { data } = await api.post('/auth/login', { email, password });
    setToken(data.data.token);
    return data.data;
  },

  async logout() {
    try {
      await api.post('/auth/logout');
    } finally {
      setToken(null);
    }
  },

  async me() {
    const { data } = await api.get('/auth/me');
    return data.data;
  },

  async getProfile() {
    const { data } = await api.get('/profile');
    return data.data;
  },

  async updateProfile(payload) {
    const { data } = await api.put('/profile', payload);
    return data.data;
  },

  hasToken() {
    return Boolean(getToken());
  },
};
