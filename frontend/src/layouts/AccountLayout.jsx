import { useState } from 'react';
import { Link, NavLink, useNavigate } from 'react-router-dom';
import { Button, Nav } from 'react-bootstrap';
import { useAuth } from '../context/AuthContext';
import { useToast } from '../context/ToastContext';
import ImageWithFallback from '../components/common/ImageWithFallback';
import {
  ChevronRightIcon,
  HeartIcon,
  LogoutIcon,
  MapPinIcon,
  PackageIcon,
  UserCircleIcon,
} from '../assets/icons';

const NAV_ITEMS = [
  { to: '/account/profile', end: true, label: 'My Profile', icon: UserCircleIcon },
  { to: '/account/orders', label: 'My Orders', icon: PackageIcon },
  { to: '/account/addresses', label: 'Addresses', icon: MapPinIcon },
  { to: '/account/wishlist', label: 'Wishlist', icon: HeartIcon },
];

export default function AccountLayout({ children }) {
  const { user, logout } = useAuth();
  const { showToast } = useToast();
  const navigate = useNavigate();

  const initial = (user?.name || '?').charAt(0).toUpperCase();

  const handleLogout = async () => {
    try {
      await logout();
      showToast('You have been signed out.');
      navigate('/');
    } catch (err) {
      showToast('Something went wrong while signing out.', 'danger');
    }
  };

  return (
    <div className="account-layout">
      <div className="container py-4 py-md-5">
        <div className="account-grid">
          {/* Sidebar */}
          <aside className="account-sidebar account-card shadow-sm">
            <div className="account-user-card">
              <div className="account-avatar-frame">
                {user?.avatar ? (
                  <ImageWithFallback src={user.avatar} alt={user?.name || 'Account'} />
                ) : (
                  <span>{initial}</span>
                )}
              </div>
              <div className="account-user-meta">
                <div className="account-user-name">{user?.name}</div>
                <div className="account-user-email">{user?.email}</div>
              </div>
            </div>

            <nav className="account-nav" aria-label="Account navigation">
              {NAV_ITEMS.map(({ to, end, label, icon: Icon }) => (
                <NavLink
                  key={to}
                  to={to}
                  end={end}
                  className={({ isActive }) => `account-nav-link ${isActive ? 'active' : ''}`}
                >
                  <Icon size={18} />
                  <span>{label}</span>
                  <ChevronRightIcon size={15} className="account-nav-chevron" />
                </NavLink>
              ))}
            </nav>

            <div className="account-nav-logout">
              <button type="button" className="account-nav-link w-100 border-0 bg-transparent" onClick={handleLogout}>
                <LogoutIcon size={18} />
                <span>Logout</span>
              </button>
            </div>
          </aside>

          {/* Mobile nav */}
          <div className="account-mobile-nav d-lg-none mb-3">
            {NAV_ITEMS.map(({ to, end, label, icon: Icon }) => (
              <NavLink
                key={to}
                to={to}
                end={end}
                className={({ isActive }) => `account-mobile-tab ${isActive ? 'active' : ''}`}
              >
                <Icon size={15} />
                {label}
              </NavLink>
            ))}
          </div>

          {/* Main content */}
          <main className="account-content">{children}</main>
        </div>
      </div>
    </div>
  );
}
