import { useEffect, useState } from 'react';
import { Link, NavLink, useNavigate } from 'react-router-dom';
import { Button, NavDropdown } from 'react-bootstrap';
import { useAuth } from '../../context/AuthContext';
import { useCart } from '../../context/CartContext';
import { useWishlist } from '../../context/WishlistContext';
import { categoryService } from '../../services/categoryService';
import { settingsService } from '../../services/settingsService';
import {
  CartIcon,
  HeartIcon,
  LeafIcon,
  MenuIcon,
  PhoneIcon,
  SearchIcon,
  UserIcon,
  XIcon,
} from '../../assets/icons';
import ThemeToggle from './ThemeToggle';

const NAV_LINKS = [
  { to: '/', label: 'Home', end: true },
  { to: '/shop', label: 'Shop' },
  { to: '/about', label: 'About' },
  { to: '/shop?sort=best_sales', label: 'Best Sales' },
  { to: '/shop?sort=promotions', label: 'Promotions' },
  { to: '/contact', label: 'Contact' },
];

const LOCATIONS = [
  'New York',
  'Los Angeles',
  'Chicago',
  'Houston',
  'Phoenix',
  'Philadelphia',
];

export default function Header() {
  const { user, logout } = useAuth();
  const { totalItems: cartTotalItems } = useCart();
  const { totalItems: wishlistTotalItems } = useWishlist();
  const navigate = useNavigate();

  const [searchTerm, setSearchTerm] = useState('');
  const [searchCategory, setSearchCategory] = useState('');
  const [scrolled, setScrolled] = useState(false);
  const [menuOpen, setMenuOpen] = useState(false);
  const [deliveryLocation, setDeliveryLocation] = useState(LOCATIONS[0]);
  const [categories, setCategories] = useState([]);
  const [storeLogo, setStoreLogo] = useState('/logo.png');

  const closeMenu = () => setMenuOpen(false);

  useEffect(() => {
    const onScroll = () => setScrolled(window.scrollY > 8);
    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });
    return () => window.removeEventListener('scroll', onScroll);
  }, []);

  useEffect(() => {
    categoryService
      .getCategories()
      .then((data) => setCategories(data ?? []))
      .catch(() => {});
  }, []);

  useEffect(() => {
    settingsService
      .publicSettings()
      .then((data) => {
        if (data?.store?.logo) setStoreLogo(data.store.logo);
      })
      .catch(() => {});
  }, []);

  const handleSearch = (e) => {
    e.preventDefault();
    const term = searchTerm.trim();
    if (term) {
      let url = `/shop?search=${encodeURIComponent(term)}`;
      if (searchCategory) url += `&category=${encodeURIComponent(searchCategory)}`;
      navigate(url);
    } else {
      navigate('/shop');
    }
  };

  const handleLogout = async () => {
    await logout();
    navigate('/');
  };

  return (
    <header className="customer-header main-header">
      {/* ===== Top Bar (green) ===== */}
      <div className="dl-top-bar d-none d-md-flex">
        <div className="dl-top-bar-inner">
          <span className="dl-top-brand">
            <LeafIcon size={14} className="me-1" />
            Delicacy Organic
          </span>

          <NavDropdown
            title={
              <span className="dl-location-text">
                <span className="dl-location-label">Delivery Location</span>
                <span className="dl-location-value">{deliveryLocation}</span>
              </span>
            }
            id="dl-location-dropdown"
            align="center"
            className="dl-location-dropdown"
            onSelect={(loc) => loc && setDeliveryLocation(loc)}
          >
            {LOCATIONS.map((loc) => (
              <NavDropdown.Item key={loc} eventKey={loc} active={loc === deliveryLocation}>
                {loc}
              </NavDropdown.Item>
            ))}
          </NavDropdown>

          <a href="tel:+18005551234" className="dl-top-phone">
            <PhoneIcon size={13} />
            <span>+1 (800) 555-1234</span>
          </a>
        </div>
      </div>

      {/* ===== Main Navbar (white, sticky) ===== */}
      <nav
        className={`dl-main-nav ${scrolled ? 'is-scrolled' : ''}`}
        role="navigation"
        aria-label="Main navigation"
      >
        <div className="dl-main-nav-inner">
          {/* Brand */}
          <Link to="/" className="dl-brand">
            <span className="dl-brand-icon">
              <img src={storeLogo} alt="Delicacy Organic" className="dl-brand-logo" />
            </span>
            <span className="dl-brand-text">
              Delicacy<span className="dl-brand-accent">Organic</span>
            </span>
          </Link>

          {/* Search with category */}
          <form onSubmit={handleSearch} role="search" className="dl-search d-none d-lg-flex">
            <select
              className="dl-search-cat"
              value={searchCategory}
              onChange={(e) => setSearchCategory(e.target.value)}
              aria-label="Search category"
            >
              <option value="">All Categories</option>
              {categories.map((cat) => (
                <option key={cat.id} value={cat.slug}>
                  {cat.name}
                </option>
              ))}
            </select>
            <input
              type="search"
              className="dl-search-input"
              placeholder="Search fresh vegetables, fruits..."
              value={searchTerm}
              onChange={(e) => setSearchTerm(e.target.value)}
              aria-label="Search products"
            />
            <button type="submit" className="dl-search-btn" aria-label="Search">
              <SearchIcon size={18} />
            </button>
          </form>

          {/* Right actions */}
          <div className="dl-actions">
            <div className="header-theme-wrap">
              <ThemeToggle />
            </div>

            <Button
              as={Link}
              to="/wishlist"
              className="dl-icon-btn"
              title="Wishlist"
              aria-label={
                wishlistTotalItems > 0
                  ? `Wishlist, ${wishlistTotalItems} item(s)`
                  : 'Wishlist'
              }
            >
              <HeartIcon size={18} />
              {wishlistTotalItems > 0 && (
                <span className="header-badge">{wishlistTotalItems}</span>
              )}
            </Button>

            <Button
              as={Link}
              to="/cart"
              className="dl-icon-btn"
              title="Shopping cart"
              aria-label={
                cartTotalItems > 0
                  ? `Cart, ${cartTotalItems} item(s)`
                  : 'Shopping cart'
              }
            >
              <CartIcon size={18} />
              {cartTotalItems > 0 && (
                <span className="header-badge">{cartTotalItems}</span>
              )}
            </Button>

            {user ? (
              <div className="dl-user-menu">
                <NavDropdown
                  title={
                    <span className="dl-user-trigger">
                      <span className="header-avatar">
                        <UserIcon size={14} />
                      </span>
                      <span className="dl-user-name d-none d-lg-inline">{user.name}</span>
                    </span>
                  }
                  id="dl-user-dropdown"
                  align="end"
                >
                  <NavDropdown.Header>
                    Signed in as <strong>{user.name}</strong>
                  </NavDropdown.Header>
                  <NavDropdown.Divider />
                  <NavDropdown.Item as={Link} to="/profile">
                    My profile
                  </NavDropdown.Item>
                  <NavDropdown.Item as={Link} to="/orders">
                    My orders
                  </NavDropdown.Item>
                  <NavDropdown.Item as={Link} to="/profile/addresses">
                    Addresses
                  </NavDropdown.Item>
                  <NavDropdown.Item as={Link} to="/wishlist">
                    Wishlist
                  </NavDropdown.Item>
                  <NavDropdown.Divider />
                  <NavDropdown.Item onClick={handleLogout}>
                    Sign out
                  </NavDropdown.Item>
                </NavDropdown>
              </div>
            ) : (
              <div className="header-auth-buttons d-none d-lg-flex">
                <Link className="header-btn-signin" to="/login">
                  Sign In
                </Link>
                <Link className="header-btn-register" to="/register">
                  Register
                </Link>
              </div>
            )}

            <Link to="/gift-cards" className="dl-gift-btn d-none d-xl-inline-flex">
              Gift Cards
            </Link>

            <button
              type="button"
              className="header-hamburger d-lg-none"
              aria-controls="dl-mobile-nav"
              aria-expanded={menuOpen}
              aria-label={menuOpen ? 'Close menu' : 'Open navigation menu'}
              onClick={() => setMenuOpen((o) => !o)}
            >
              {menuOpen ? <XIcon size={22} /> : <MenuIcon size={22} />}
            </button>
          </div>
        </div>

        {/* Mobile collapse */}
        {menuOpen && (
          <div className="dl-mobile-collapse d-lg-none" id="dl-mobile-nav">
            {/* Mobile search */}
            <form onSubmit={handleSearch} className="dl-mobile-search">
              <select
                className="dl-search-cat"
                value={searchCategory}
                onChange={(e) => setSearchCategory(e.target.value)}
                aria-label="Search category"
              >
                <option value="">All Categories</option>
                {categories.map((cat) => (
                  <option key={cat.id} value={cat.slug}>
                    {cat.name}
                  </option>
                ))}
              </select>
              <input
                type="search"
                className="dl-search-input"
                placeholder="Search products..."
                value={searchTerm}
                onChange={(e) => setSearchTerm(e.target.value)}
                aria-label="Search products"
              />
              <button type="submit" className="dl-search-btn" aria-label="Search">
                <SearchIcon size={18} />
              </button>
            </form>

            <nav className="header-mobile-nav">
              {NAV_LINKS.map((link) => (
                <NavLink
                  key={link.to}
                  to={link.to}
                  end={link.end}
                  onClick={closeMenu}
                  className={({ isActive }) =>
                    `header-mobile-link py-2 ${isActive ? 'active' : ''}`
                  }
                >
                  {link.label}
                </NavLink>
              ))}
              {user && user.role === 'admin' && (
                <NavLink
                  to="/admin/products"
                  onClick={closeMenu}
                  className="header-mobile-link py-2"
                >
                  Admin
                </NavLink>
              )}
              {!user && (
                <div className="d-flex flex-column gap-2 mt-3 pt-3 border-top header-mobile-auth">
                  <Link
                    className="btn btn-outline-success w-100"
                    to="/login"
                    onClick={closeMenu}
                  >
                    Sign In
                  </Link>
                  <Link
                    className="btn btn-success w-100"
                    to="/register"
                    onClick={closeMenu}
                  >
                    Register
                  </Link>
                </div>
              )}
            </nav>
          </div>
        )}
      </nav>

      {/* ===== Secondary Nav (categories + links) ===== */}
      <div className="dl-secondary-nav d-none d-lg-block">
        <div className="dl-secondary-nav-inner">
          {/* All Categories dropdown */}
          <NavDropdown
            title={
              <span className="dl-cat-trigger">
                <MenuIcon size={15} />
                All Categories
              </span>
            }
            id="dl-all-categories"
            className="dl-categories-dropdown"
          >
            <NavDropdown.Item as={Link} to="/shop">
              All Products
            </NavDropdown.Item>
            {categories.length > 0 && <NavDropdown.Divider />}
            {categories.map((cat) => (
              <NavDropdown.Item key={cat.id} as={Link} to={`/shop?category=${cat.slug}`}>
                {cat.name}
                {cat.products_count != null && (
                  <span className="dl-cat-count">{cat.products_count}</span>
                )}
              </NavDropdown.Item>
            ))}
          </NavDropdown>

          {/* Nav links */}
          <nav className="dl-secondary-links">
            {NAV_LINKS.map((link) => (
              <NavLink
                key={link.to}
                to={link.to}
                end={link.end}
                className={({ isActive }) =>
                  `dl-secondary-link ${isActive ? 'active' : ''}`
                }
              >
                {link.label}
              </NavLink>
            ))}
            {user && user.role === 'admin' && (
              <NavLink to="/admin/products" className="dl-secondary-link">
                Admin
              </NavLink>
            )}
          </nav>
        </div>
      </div>
    </header>
  );
}
