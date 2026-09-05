import { useEffect, useState } from 'react';
import { Link, Outlet, useLocation } from 'react-router-dom';
import { Nav } from 'react-bootstrap';
import { StoreIcon, TagIcon, TruckIcon, LeafIcon, BoxesIcon, ExternalLinkIcon, ImageIcon, SettingsIcon } from '../assets/icons';
import { settingsService } from '../services/settingsService';

/**
 * Admin section shell. Renders a responsive sidebar (horizontal scroll bar on
 * mobile) and the active admin page.
 */
export default function AdminLayout() {
  const { pathname } = useLocation();
  const [logo, setLogo] = useState(null);

  // Re-fetch logo on every navigation so edits in Settings are visible immediately.
  useEffect(() => {
    settingsService
      .publicSettings()
      .then((data) => setLogo(data?.store?.logo || null))
      .catch(() => {});
  }, [pathname]);

  const links = [
    { to: '/admin', label: 'Dashboard', icon: LeafIcon, match: (p) => p === '/admin' },
    { to: '/admin/orders', label: 'Orders', icon: TruckIcon, match: (p) => p.startsWith('/admin/orders') },
    { to: '/admin/products', label: 'Products', icon: StoreIcon, match: (p) => p.startsWith('/admin/products') },
    { to: '/admin/inventory', label: 'Inventory', icon: BoxesIcon, match: (p) => p === '/admin/inventory' || p.startsWith('/admin/inventory/') },
    { to: '/admin/categories', label: 'Categories', icon: TagIcon, match: (p) => p.startsWith('/admin/categories') },
    { to: '/admin/banners', label: 'Banners', icon: ImageIcon, match: (p) => p.startsWith('/admin/banners') },
    { to: '/admin/settings', label: 'Settings', icon: SettingsIcon, match: (p) => p.startsWith('/admin/settings') },
  ];

  return (
    <div className="admin-shell">
      <div className="admin-sidebar d-md-flex flex-md-column flex-shrink-0 p-3">
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
        <Nav className="flex-md-column flex-row flex-nowrap overflow-auto admin-nav gap-1" activeKey={pathname}>
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