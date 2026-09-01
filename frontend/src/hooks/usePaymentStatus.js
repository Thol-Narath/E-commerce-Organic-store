import { useCallback, useEffect, useRef, useState } from 'react';
import { paymentService } from '../services/paymentService';
import { getErrorMessage } from '../utils/error';

/**
 * Server-authoritative payment status polling.
 *
 * The browser never talks to ABA PayWay directly; it only polls the store's
 * own payment-status endpoint (a cheap DB read) and the backend reconciles
 * against the gateway/webhook. Polling stops as soon as a terminal state is
 * reached, or manually via stopPolling().
 */
export function usePaymentStatus(orderNumber, intervalMs = 4000) {
  const [status, setStatus] = useState(null);
  const [error, setError] = useState('');
  const timerRef = useRef(null);

  const stopPolling = useCallback(() => {
    if (timerRef.current) {
      clearInterval(timerRef.current);
      timerRef.current = null;
    }
  }, []);

  const pollOnce = useCallback(
    async (silent = false) => {
      try {
        const data = await paymentService.status(orderNumber);
        setStatus(data);
        setError('');
        return data;
      } catch (err) {
        if (!silent) setError(getErrorMessage(err));
        return null;
      }
    },
    [orderNumber]
  );

  const startPolling = useCallback(() => {
    stopPolling();
    timerRef.current = setInterval(() => pollOnce(true), intervalMs);
  }, [intervalMs, pollOnce, stopPolling]);

  useEffect(() => stopPolling, [stopPolling]);

  return { status, error, pollOnce, startPolling, stopPolling };
}