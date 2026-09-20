import { useEffect, useState } from 'react';
import { Link, Outlet, useLocation } from 'react-router-dom';
import { Nav } from 'react-bootstrap';
import { StoreIcon, TagIcon, TruckIcon, LeafIcon, BoxesIcon, ExternalLinkIcon, ImageIcon, SettingsIcon, FactoryIcon, MailIcon, MenuIcon, XIcon } from '../assets/icons';
import { settingsService } from '../services/settingsService';

/**
 * Admin section shell. On mobile it renders a sticky top app bar and turns the
 * sidebar into a slide-in drawer; on desktop it keeps a fixed left rail.
 */
export default function AdminLayout() {
  const { pathname } = useLocation();
  const [logo, setLogo] = useState(null);
  const [sidebarOpen, setSidebarOpen] = useState(false);

  // Re-fetch logo on every navigation so edits in Settings are visible immediately.
  useEffect(() => {
    settingsService
      .publicSettings()
      .then((data) => setLogo(data?.store?.logo || null))
      .catch(() => {});
  }, [pathname]);

  // Close the mobile drawer whenever the route changes.
  useEffect(() => {
    setSidebarOpen(false);
  }, [pathname]);

  const links = [
    { to: '/admin', label: 'Dashboard', icon: LeafIcon, match: (p) => p === '/admin' },
    { to: '/admin/orders', label: 'Orders', icon: TruckIcon, match: (p) => p.startsWith('/admin/orders') },
    { to: '/admin/products', label: 'Products', icon: StoreIcon, match: (p) => p.startsWith('/admin/products') },
    { to: '/admin/inventory', label: 'Inventory', icon: BoxesIcon, match: (p) => p === '/admin/inventory' || p.startsWith('/admin/inventory/') },
    { to: '/admin/shipping-methods', label: 'Shipping', icon: TruckIcon, match: (p) => p.startsWith('/admin/shipping-methods') },
    { to: '/admin/suppliers', label: 'Suppliers', icon: FactoryIcon, match: (p) => p.startsWith('/admin/suppliers') || p.startsWith('/admin/supplier-orders') },
    { to: '/admin/categories', label: 'Categories', icon: TagIcon, match: (p) => p.startsWith('/admin/categories') },
    { to: '/admin/banners', label: 'Banners', icon: ImageIcon, match: (p) => p.startsWith('/admin/banners') },
    { to: '/admin/messages', label: 'Messages', icon: MailIcon, match: (p) => p === '/admin/messages' || p.startsWith('/admin/messages/') },
    { to: '/admin/settings', label: 'Settings', icon: SettingsIcon, match: (p) => p.startsWith('/admin/settings') },
  ];

  return (
    <div className="admin-shell">
      {/* Mobile app bar */}
      <div className="admin-topbar d-md-none">
        <button
          type="button"
          className="admin-topbar-toggle"
          onClick={() => setSidebarOpen(true)}
          aria-label="Open menu"
        >
          <MenuIcon size={20} />
        </button>
        <div className="admin-topbar-brand">
          {logo ? (
            <img src={logo} alt="Store logo" className="admin-brand-logo" />
          ) : (
            <LeafIcon size={20} className="text-success" />
          )}
          <span className="fw-semibold">Organic Admin</span>
        </div>
      </div>

      {/* Backdrop behind the mobile drawer */}
      {sidebarOpen && (
        <div className="admin-sidebar-backdrop d-md-none" onClick={() => setSidebarOpen(false)} />
      )}

      <div className={`admin-sidebar d-md-flex flex-md-column flex-shrink-0 p-3 ${sidebarOpen ? 'open' : ''}`}>
        <button
          type="button"
          className="admin-sidebar-close d-md-none"
          onClick={() => setSidebarOpen(false)}
          aria-label="Close menu"
        >
          <XIcon size={20} />
        </button>
        <div className="admin-brand">
          {logo ? (
            <img
              src={logo}
              alt="Store logo"
              className="admin-brand-logo"
            />
          ) : (
            <LeafIcon size={22} className="text-success" />
          )}
          <span className="fw-semibold">Organic Admin</span>
        </div>
        <Link
          to="/shop"
          className="admin-view-store"
          title="Open the public storefront"
        >
          <ExternalLinkIcon size={16} />
          <span>View Store</span>
        </Link>
        <Nav className="flex-column flex-nowrap overflow-auto admin-nav gap-1" activeKey={pathname}>
          {links.map(({ to, label, icon: Icon, match }) => (
            <Nav.Item key={to}>
              <Nav.Link as={Link} to={to} className={match(pathname) ? 'active' : ''}>
                <Icon size={18} />
                <span>{label}</span>
              </Nav.Link>
            </Nav.Item>
          ))}
        </Nav>
      </div>
      <main className="admin-content p-3 p-md-4">
        <Outlet />
      </main>
    </div>
  );
}