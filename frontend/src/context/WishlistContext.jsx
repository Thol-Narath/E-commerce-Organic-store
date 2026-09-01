import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react';
import { useAuth } from './AuthContext';
import { wishlistService } from '../services/wishlistService';
import { getErrorMessage } from '../utils/error';

const EMPTY_WISHLIST = { id: null, items: [], total_items: 0 };

const WishlistContext = createContext(null);

/**
 * Server-authoritative wishlist state. Each product can be present at most
 * once; toggling from a product id adds or removes the matching line.
 */
export function WishlistProvider({ children }) {
  const { user } = useAuth();
  const [wishlist, setWishlist] = useState(EMPTY_WISHLIST);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  const [pendingItemIds, setPendingItemIds] = useState([]);
  const [pendingProductIds, setPendingProductIds] = useState([]);

  const apply = useCallback((payload) => {
    const items = payload?.items ?? [];
    setWishlist({
      id: payload?.id ?? null,
      items,
      total_items: payload?.total_items ?? items.length,
    });
  }, []);

  const fetchWishlist = useCallback(async () => {
    if (!user) {
      setWishlist(EMPTY_WISHLIST);
      setError('');
      return;
    }
    setLoading(true);
    setError('');
    try {
      apply(await wishlistService.getWishlist());
    } catch (err) {
      setError(getErrorMessage(err));
    } finally {
      setLoading(false);
    }
  }, [user, apply]);

  useEffect(() => {
    fetchWishlist();
  }, [fetchWishlist]);

  const markItemBusy = useCallback((id) => {
    setPendingItemIds((ids) => (ids.includes(id) ? ids : [...ids, id]));
  }, []);

  const unmarkItemBusy = useCallback((id) => {
    setPendingItemIds((ids) => ids.filter((itemId) => itemId !== id));
  }, []);

  const markProductBusy = useCallback((id) => {
    setPendingProductIds((ids) => (ids.includes(id) ? ids : [...ids, id]));
  }, []);

  const unmarkProductBusy = useCallback((id) => {
    setPendingProductIds((ids) => ids.filter((productId) => productId !== id));
  }, []);

  const addToWishlist = useCallback(
    async (productId) => {
      markProductBusy(productId);
      try {
        const payload = await wishlistService.addToWishlist(productId);
        apply(payload);
        return payload;
      } finally {
        unmarkProductBusy(productId);
      }
    },
    [apply, markProductBusy, unmarkProductBusy]
  );

  const removeFromWishlist = useCallback(
    async (wishlistItemId) => {
      markItemBusy(wishlistItemId);
      try {
        const payload = await wishlistService.removeFromWishlist(wishlistItemId);
        apply(payload);
        return payload;
      } finally {
        unmarkItemBusy(wishlistItemId);
      }
    },
    [apply, markItemBusy, unmarkItemBusy]
  );

  const toggleWishlist = useCallback(
    async (productId) => {
      const existing = wishlist.items.find((item) => item.product?.id === productId);
      if (existing) {
        const payload = await removeFromWishlist(existing.id);
        return { added: false, payload };
      }
      const payload = await addToWishlist(productId);
      return { added: true, payload };
    },
    [wishlist.items, addToWishlist, removeFromWishlist]
  );

  const moveToCart = useCallback(
    async (wishlistItemId) => {
      markItemBusy(wishlistItemId);
      try {
        const result = await wishlistService.moveToCart(wishlistItemId);
        apply(result.wishlist);
        return result;
      } finally {
        unmarkItemBusy(wishlistItemId);
      }
    },
    [apply, markItemBusy, unmarkItemBusy]
  );

  const isWishlisted = useCallback(
    (productId) => wishlist.items.some((item) => item.product?.id === productId),
    [wishlist.items]
  );

  const isProductBusy = useCallback((productId) => pendingProductIds.includes(productId), [pendingProductIds]);

  const isItemBusy = useCallback((id) => pendingItemIds.includes(id), [pendingItemIds]);

  const value = useMemo(
    () => ({
      wishlist,
      loading,
      error,
      totalItems: wishlist.total_items,
      addToWishlist,
      removeFromWishlist,
      toggleWishlist,
      moveToCart,
      fetchWishlist,
      isWishlisted,
      isProductBusy,
      isItemBusy,
    }),
    [wishlist, loading, error, addToWishlist, removeFromWishlist, toggleWishlist, moveToCart, fetchWishlist, isWishlisted, isProductBusy, isItemBusy]
  );

  return <WishlistContext.Provider value={value}>{children}</WishlistContext.Provider>;
}

export function useWishlist() {
  const context = useContext(WishlistContext);
  if (!context) {
    throw new Error('useWishlist must be used within a WishlistProvider');
  }
  return context;
}