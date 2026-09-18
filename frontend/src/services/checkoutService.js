import api from './api';

/**
 * Checkout service. The client only submits the chosen shipping address id
 * (and optionally the shipping method id); every price, fee and total is
 * calculated by the OrderService server-side.
 */
export const checkoutService = {
  /**
   * Convert the current cart into an order. Returns the created Order.
   * The cart is cleared server-side only when the order succeeds.
   */
  async placeOrder(addressId, shippingMethodId = null) {
    const { data } = await api.post('/checkout', {
      address_id: addressId,
      shipping_method_id: shippingMethodId || undefined,
    });
    return data.data;
  },
};