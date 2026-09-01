import { useEffect } from 'react';

/**
 * Set the document title with the store name as a consistent suffix, e.g.
 * `Products | Organic Store`.
 */
export default function usePageTitle(title) {
  useEffect(() => {
    const storeName = 'Organic Store';
    document.title = title ? `${title} | ${storeName}` : storeName;

    return () => {
      document.title = storeName;
    };
  }, [title]);
}