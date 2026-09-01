import api from './api';

/**
 * Public store settings (display only). The backend remains the source of
 * truth for prices, fees and totals — this only powers the checkout summary.
 */
export const settingsService = {
  /**
   * Public store + shipping information, e.g. { store, shipping }.
   */
  async publicSettings() {
    const { data } = await api.get('/settings/public');
    return data.data;
  },
};