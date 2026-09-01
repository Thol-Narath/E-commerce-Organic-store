import api from './api';

/**
 * Checkout service. The client only submits the chosen shipping address id;
 * every price, fee and total is calculated by the OrderService server-side.
 */
export const checkoutService = {
  /**
   * Convert the current cart into an order. Returns the created Order.
   * The cart is cleared server-side only when the order succeeds.
   */
  async placeOrder(addressId) {
    const { data } = await api.post('/checkout', { address_id: addressId });
    return data.data;
  },
};