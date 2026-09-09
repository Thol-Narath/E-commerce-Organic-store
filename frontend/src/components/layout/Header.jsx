import { useEffect, useMemo, useRef, useState } from 'react';
import { Link, NavLink, useNavigate } from 'react-router-dom';
import { Button, NavDropdown } from 'react-bootstrap';
import { useAuth } from '../../context/AuthContext';
import { useCart } from '../../context/CartContext';
import { useWishlist } from '../../context/WishlistContext';
import { categoryService } from '../../services/categoryService';
import { settingsService } from '../../services/settingsService';
import {
  CartIcon,
  ChevronDownIcon,
  HeartIcon,
  HomeIcon,
  LeafIcon,
  LogoutIcon,
  MapPinIcon,
  MenuIcon,
  PackageIcon,
  PhoneIcon,
  SearchIcon,
  UserCircleIcon,
  UserIcon,
  XIcon,
} from '../../assets/icons';
import ThemeToggle from './ThemeToggle';

const NAV_LINKS = [
  { to: '/', label: 'Home', end: true },
  { to: '/shop', label: 'Shop' },
  { to: '/about', label: 'About' },
  { to: '/best-sales', label: 'Best Sales' },
  { to: '/promotions', label: 'Promotions' },
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
  const [storeName, setStoreName] = useState('Delicacy Organic');
  const [logoHeight, setLogoHeight] = useState(42);
  const [accountOpen, setAccountOpen] = useState(false);
  const accountRef = useRef(null);

  const closeMenu = () => setMenuOpen(false);

  // Split the admin-customizable store name into base + accent word, so the
  // brand mark keeps its two-tone style ("Delicacy Organic" -> Delicacy|Organic).
  const brandParts = useMemo(() => {
    const name = (storeName || 'Delicacy Organic').trim();
    const spaceIndex = name.indexOf(' ');
    if (spaceIndex === -1) return { base: name, accent: null };
    return { base: name.slice(0, spaceIndex), accent: name.slice(spaceIndex + 1) };
  }, [storeName]);

  useEffect(() => {
    const handleOutsideClick = (e) => {
      if (accountRef.current && !accountRef.current.contains(e.target)) {
        setAccountOpen(false);
      }
    };
    const handleKeyDown = (e) => {
      if (e.key === 'Escape') setAccountOpen(false);
    };
    document.addEventListener('mousedown', handleOutsideClick);
    document.addEventListener('keydown', handleKeyDown);
    return () => {
      document.removeEventListener('mousedown', handleOutsideClick);
      document.removeEventListener('keydown', handleKeyDown);
    };
  }, []);

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
        if (data?.store?.name) setStoreName(data.store.name);
        if (data?.store?.logo_height) setLogoHeight(data.store.logo_height);
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
    setAccountOpen(false);
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
            {storeName}
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
            <span className="dl-brand-icon" style={{ width: `${logoHeight}px`, height: `${logoHeight}px` }}>
              <img src={storeLogo} alt={storeName} className="dl-brand-logo" />
            </span>
            <span className="dl-brand-text">
              {brandParts.base}
              {brandParts.accent && <span className="dl-brand-accent">{brandParts.accent}</span>}
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
              to="/account/wishlist"
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
              <div className="dl-user-menu" ref={accountRef}>
                {/* Trigger */}
                <button
                  type="button"
                  className="dl-account-trigger"
                  onClick={() => setAccountOpen((o) => !o)}
                  aria-expanded={accountOpen}
                  aria-haspopup="true"
                  aria-label="Account menu"
                >
                  <span className="header-avatar header-avatar-ring">
                    {user.avatar ? (
                      <img src={user.avatar} alt={user.name} className="header-avatar-img" />
                    ) : (
                      <span className="header-avatar-initial">
                        {(user.name || '?').charAt(0).toUpperCase()}
                      </span>
                    )}
                  </span>
                  <span className="dl-account-meta d-none d-md-block">
                    <span className="dl-account-name">{user.name}</span>
                  </span>
                  <ChevronDownIcon
                    size={16}
                    className={`dl-account-chevron ${accountOpen ? 'is-open' : ''}`}
                  />
                </button>

                {/* Dropdown */}
                {accountOpen && (
                  <div className="dl-account-menu" role="menu" aria-label="Account">
                    <div className="dl-account-menu-header">
                      <p className="dl-account-menu-name text-truncate">{user.name}</p>
                      <p className="dl-account-menu-email text-truncate">{user.email}</p>
                    </div>

                    <div className="dl-account-menu-links">
                      <Link to="/account/profile" role="menuitem" onClick={() => setAccountOpen(false)}>
                        <UserCircleIcon size={16} />
                        My Profile
                      </Link>
                      <Link to="/account/orders" role="menuitem" onClick={() => setAccountOpen(false)}>
                        <PackageIcon size={16} />
                        My Orders
                      </Link>
                      <Link to="/account/addresses" role="menuitem" onClick={() => setAccountOpen(false)}>
                        <MapPinIcon size={16} />
                        Addresses
                      </Link>
                      <Link to="/account/wishlist" role="menuitem" onClick={() => setAccountOpen(false)}>
                        <HeartIcon size={16} />
                        Wishlist
                      </Link>
                    </div>

                    <div className="dl-account-menu-actions">
                      <button type="button" role="menuitem" onClick={handleLogout}>
                        <LogoutIcon size={16} />
                        Sign Out
                      </button>
                    </div>
                  </div>
                )}
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
