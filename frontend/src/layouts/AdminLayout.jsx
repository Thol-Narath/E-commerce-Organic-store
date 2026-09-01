import { Link, Outlet, useLocation } from 'react-router-dom';
import { Nav } from 'react-bootstrap';
import { StoreIcon, TagIcon, TruckIcon, LeafIcon, BoxesIcon } from '../assets/icons';

/**
 * Admin section shell. Renders a responsive sidebar (horizontal scroll bar on
 * mobile) and the active admin page. Only the sections that exist today are
 * listed — Dashboard, Orders, Products, Categories, Inventory.
 */
export default function AdminLayout() {
  const { pathname } = useLocation();

  const links = [
    { to: '/admin', label: 'Dashboard', icon: LeafIcon, match: (p) => p === '/admin' },
    { to: '/admin/orders', label: 'Orders', icon: TruckIcon, match: (p) => p.startsWith('/admin/orders') },
    { to: '/admin/products', label: 'Products', icon: StoreIcon, match: (p) => p.startsWith('/admin/products') },
    { to: '/admin/inventory', label: 'Inventory', icon: BoxesIcon, match: (p) => p === '/admin/inventory' || p.startsWith('/admin/inventory/') },
    { to: '/admin/categories', label: 'Categories', icon: TagIcon, match: (p) => p.startsWith('/admin/categories') },
  ];

  return (
    <div className="admin-shell">
      <div className="admin-sidebar d-md-flex flex-md-column flex-shrink-0 p-3">
        <div className="admin-brand">
          <LeafIcon size={22} className="text-success" />
          <span className="fw-semibold">Organic Admin</span>
        </div>
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