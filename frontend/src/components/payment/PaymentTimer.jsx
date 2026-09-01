import { useEffect, useRef, useState } from 'react';
import { ClockIcon } from '../../assets/icons';

function secondsTo(expiresAt) {
  if (!expiresAt) return null;
  const target = new Date(expiresAt).getTime();
  if (Number.isNaN(target)) return null;
  return Math.max(0, Math.floor((target - Date.now()) / 1000));
}

/**
 * Countdown to the payment expiry timestamp. Calls onExpire exactly once when
 * the attempt lapses. Renders nothing when no expiry is known.
 */
export default function PaymentTimer({ expiresAt, onExpire, className = '' }) {
  const [left, setLeft] = useState(() => secondsTo(expiresAt));
  const expiredRef = useRef(false);
  const onExpireRef = useRef(onExpire);

  useEffect(() => {
    onExpireRef.current = onExpire;
  }, [onExpire]);

  useEffect(() => {
    setLeft(secondsTo(expiresAt));
    expiredRef.current = false;
    if (!expiresAt) return undefined;

    const id = setInterval(() => {
      const next = secondsTo(expiresAt);
      setLeft(next);
      if (next === 0 && !expiredRef.current) {
        expiredRef.current = true;
        onExpireRef.current?.();
      }
    }, 1000);

    return () => clearInterval(id);
  }, [expiresAt]);

  if (left === null) return null;

  const text = `${String(Math.floor(left / 60)).padStart(2, '0')}:${String(left % 60).padStart(2, '0')}`;

  return (
    <span className={`payment-timer ${left <= 60 ? 'expiring' : ''} ${className}`}>
      <ClockIcon size={16} />
      <span>{left <= 60 ? 'Expiring soon: ' : 'Pay within: '}{text}</span>
    </span>
  );
}