import { createContext, useCallback, useContext, useMemo, useState } from 'react';
import { Toast, ToastContainer } from 'react-bootstrap';
import { CheckIcon, ShieldIcon } from '../assets/icons';

const ToastContext = createContext(null);

/**
 * Global toast notifications. `showToast(message, variant)` accepts
 * 'success' | 'danger' (defaults to 'success'). Toasts auto-dismiss.
 */
export function ToastProvider({ children }) {
  const [toasts, setToasts] = useState([]);

  const dismiss = useCallback((id) => {
    setToasts((current) => current.filter((toast) => toast.id !== id));
  }, []);

  const showToast = useCallback((message, variant = 'success') => {
    const id = `${Date.now()}-${Math.random()}`;
    setToasts((current) => [...current, { id, message, variant }]);
    window.setTimeout(() => dismiss(id), 4500);
  }, [dismiss]);

  const value = useMemo(() => ({ showToast }), [showToast]);

  return (
    <ToastContext.Provider value={value}>
      {children}
      <ToastContainer position="bottom-end" className="p-3 toast-stack">
        {toasts.map((toast) => (
          <Toast
            key={toast.id}
            onClose={() => dismiss(toast.id)}
            bg={toast.variant === 'danger' ? 'danger' : 'success'}
            delay={4500}
            autohide
            className="align-items-center border-0 shadow"
          >
            <Toast.Body className="d-flex align-items-center gap-2 text-white fw-semibold">
              {toast.variant === 'danger' ? <ShieldIcon size={18} /> : <CheckIcon size={18} />}
              <span>{toast.message}</span>
            </Toast.Body>
          </Toast>
        ))}
      </ToastContainer>
    </ToastContext.Provider>
  );
}

export function useToast() {
  const context = useContext(ToastContext);
  if (!context) {
    throw new Error('useToast must be used within a ToastProvider');
  }
  return context;
}