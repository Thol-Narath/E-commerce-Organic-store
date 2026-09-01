import api from './api';

/**
 * Server-authoritative wishlist service. The wishlist lives on the backend and
 * each product can appear at most once (idempotent adds).
 */
export const wishlistService = {
  /**
   * The authenticated customer's wishlist.
   */
  async getWishlist() {
    const { data } = await api.get('/wishlist');
    return data.data;
  },

  /**
   * Add a product to the wishlist. Duplicates are prevented server-side.
   */
  async addToWishlist(productId) {
    const { data } = await api.post('/wishlist/items', { product_id: productId });
    return data.data;
  },

  /**
   * Remove a wishlist line.
   */
  async removeFromWishlist(wishlistItemId) {
    const { data } = await api.delete(`/wishlist/items/${wishlistItemId}`);
    return data.data;
  },

  /**
   * Move a wishlist line into the cart (quantity 1) and remove it from the
   * wishlist. Returns both refreshed payloads: { cart, wishlist }.
   */
  async moveToCart(wishlistItemId) {
    const { data } = await api.post(`/wishlist/items/${wishlistItemId}/move-to-cart`);
    return data.data;
  },
};