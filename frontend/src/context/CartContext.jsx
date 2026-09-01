import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react';
import { useAuth } from './AuthContext';
import { cartService } from '../services/cartService';
import { getErrorMessage } from '../utils/error';

const EMPTY_CART = { id: null, items: [], subtotal: '0.00', total_items: 0 };

const CartContext = createContext(null);

/**
 * Server-authoritative cart state. The backend is the single source of truth;
 * every mutation returns a fully refreshed cart payload which is applied here.
 */
export function CartProvider({ children }) {
  const { user } = useAuth();
  const [cart, setCart] = useState(EMPTY_CART);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  const [pendingItemIds, setPendingItemIds] = useState([]);
  const [clearing, setClearing] = useState(false);

  const apply = useCallback((payload) => {
    const items = payload?.items ?? [];
    setCart({
      id: payload?.id ?? null,
      items,
      subtotal: payload?.subtotal ?? '0.00',
      total_items: payload?.total_items ?? items.reduce((sum, item) => sum + item.quantity, 0),
    });
  }, []);

  const fetchCart = useCallback(async () => {
    if (!user) {
      setCart(EMPTY_CART);
      setError('');
      return;
    }
    setLoading(true);
    setError('');
    try {
      apply(await cartService.getCart());
    } catch (err) {
      setError(getErrorMessage(err));
    } finally {
      setLoading(false);
    }
  }, [user, apply]);

  useEffect(() => {
    fetchCart();
  }, [fetchCart]);

  const markBusy = useCallback((id) => {
    setPendingItemIds((ids) => (ids.includes(id) ? ids : [...ids, id]));
  }, []);

  const unmarkBusy = useCallback((id) => {
    setPendingItemIds((ids) => ids.filter((itemId) => itemId !== id));
  }, []);

  const addToCart = useCallback(
    async (productId, quantity = 1) => {
      const payload = await cartService.addToCart(productId, quantity);
      apply(payload);
      return payload;
    },
    [apply]
  );

  const updateCartItem = useCallback(
    async (cartItemId, quantity) => {
      markBusy(cartItemId);
      try {
        const payload = await cartService.updateCartItem(cartItemId, quantity);
        apply(payload);
        return payload;
      } finally {
        unmarkBusy(cartItemId);
      }
    },
    [apply, markBusy, unmarkBusy]
  );

  const removeCartItem = useCallback(
    async (cartItemId) => {
      markBusy(cartItemId);
      try {
        const payload = await cartService.removeCartItem(cartItemId);
        apply(payload);
        return payload;
      } finally {
        unmarkBusy(cartItemId);
      }
    },
    [apply, markBusy, unmarkBusy]
  );

  const clearCart = useCallback(async () => {
    setClearing(true);
    try {
      const payload = await cartService.clearCart();
      apply(payload);
      return payload;
    } finally {
      setClearing(false);
    }
  }, [apply]);

  const replaceCart = useCallback(
    (payload) => {
      apply(payload);
    },
    [apply]
  );

  const isItemBusy = useCallback((id) => pendingItemIds.includes(id), [pendingItemIds]);

  const value = useMemo(
    () => ({
      cart,
      loading,
      error,
      totalItems: cart.total_items,
      subtotal: cart.subtotal,
      addToCart,
      updateCartItem,
      removeCartItem,
      clearCart,
      fetchCart,
      replaceCart,
      isItemBusy,
      clearing,
      syncing: pendingItemIds.length > 0 || clearing,
    }),
    [cart, loading, error, addToCart, updateCartItem, removeCartItem, clearCart, fetchCart, replaceCart, isItemBusy, clearing, pendingItemIds.length]
  );

  return <CartContext.Provider value={value}>{children}</CartContext.Provider>;
}

export function useCart() {
  const context = useContext(CartContext);
  if (!context) {
    throw new Error('useCart must be used within a CartProvider');
  }
  return context;
}