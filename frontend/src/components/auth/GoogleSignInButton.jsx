import { useEffect, useRef } from 'react';
import { Button, Spinner } from 'react-bootstrap';

/**
 * Google sign-in button powered by Google Identity Services (GIS).
 *
 * Loads the GIS script, renders the Google "Sign in with Google" button into a
 * container, then passes the returned ID token to the backend
 * (POST /auth/google) via the `onToken` callback.
 */
export default function GoogleSignInButton({
  clientId = import.meta.env.VITE_GOOGLE_CLIENT_ID,
  onToken,
  onError,
  loading = false,
}) {
  const containerRef = useRef(null);
  const renderedRef = useRef(false);
  const onTokenRef = useRef(onToken);
  onTokenRef.current = onToken;

  useEffect(() => {
    if (!clientId || !containerRef.current || renderedRef.current) {
      return;
    }

    const loadGsi = () => {
      if (!window.google?.accounts) {
        const script = document.createElement('script');
        script.src = 'https://accounts.google.com/gsi/client';
        script.async = true;
        script.defer = true;
        script.onload = initGsi;
        document.body.appendChild(script);
        return;
      }
      initGsi();
    };

    const initGsi = () => {
      if (renderedRef.current) {
        return;
      }
      window.google.accounts.id.initialize({
        client_id: clientId,
        callback: (response) => {
          if (response?.credential) {
            onTokenRef.current?.(response.credential);
          }
        },
      });
      window.google.accounts.id.renderButton(containerRef.current, {
        theme: 'outline',
        size: 'large',
        width: containerRef.current?.clientWidth || 280,
        text: 'continue_with',
      });
      renderedRef.current = true;
    };

    loadGsi();

    return () => {
      if (window.google?.accounts) {
        try {
          window.google.accounts.id.cancel();
        } catch {
          // ignore cleanup errors
        }
      }
    };
  }, [clientId]);

  if (!clientId) {
    return (
      <p className="text-danger small mb-0 text-center">
        Google sign-in is not configured. Set <code>VITE_GOOGLE_CLIENT_ID</code>.
      </p>
    );
  }

  if (loading) {
    return (
      <Button variant="outline-secondary" className="w-100" disabled>
        <Spinner as="span" animation="border" size="sm" className="me-2" />
        Signing in with Google...
      </Button>
    );
  }

  return (
    <div>
      <div ref={containerRef} />
    </div>
  );
}
