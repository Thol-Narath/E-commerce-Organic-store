import { useEffect } from 'react';
import { Outlet, useLocation } from 'react-router-dom';
import Header from '../components/layout/Header';
import Footer from '../components/layout/Footer';

/**
 * Reusable shell for all customer-facing pages.
 * Contains the store header, the routed page content and the footer.
 */
export default function CustomerLayout() {
  const { pathname } = useLocation();

  // Move to the top of the page when navigating between customer pages.
  useEffect(() => {
    window.scrollTo({ top: 0, behavior: 'instant' });
  }, [pathname]);

  return (
    <div className="customer-app d-flex flex-column min-vh-100">
      <Header />
      <main id="main-content" className="flex-grow-1">
        <Outlet />
      </main>
      <Footer />
    </div>
  );
}