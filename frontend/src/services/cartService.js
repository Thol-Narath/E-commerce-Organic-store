import api from './api';

/**
 * Server-authoritative cart service.
 * The backend owns all quantities, prices and totals; these methods only
 * transport the client's intent and return the full refreshed cart payload.
 */
export const cartService = {
  /**
   * The authenticated customer's cart (created on demand by the backend).
   */
  async getCart() {
    const { data } = await api.get('/cart');
    return data.data;
  },

  /**
   * Add a product (quantity defaults to 1). Adds merge into an existing line.
   */
  async addToCart(productId, quantity = 1) {
    const { data } = await api.post('/cart/items', { product_id: productId, quantity });
    return data.data;
  },

  /**
   * Set an existing cart item's quantity.
   */
  async updateCartItem(cartItemId, quantity) {
    const { data } = await api.patch(`/cart/items/${cartItemId}`, { quantity });
    return data.data;
  },

  /**
   * Remove a single cart line.
   */
  async removeCartItem(cartItemId) {
    const { data } = await api.delete(`/cart/items/${cartItemId}`);
    return data.data;
  },

  /**
   * Remove every item from the cart (the cart row itself is kept).
   */
  async clearCart() {
    const { data } = await api.delete('/cart');
    return data.data;
  },
};