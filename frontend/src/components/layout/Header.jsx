import { useState } from 'react';
import { Link, NavLink, useNavigate } from 'react-router-dom';
import { Button, Container, Form, Nav, Navbar, NavDropdown } from 'react-bootstrap';
import { useAuth } from '../../context/AuthContext';
import { useCart } from '../../context/CartContext';
import { useWishlist } from '../../context/WishlistContext';
import { CartIcon, HeartIcon, LeafIcon, SearchIcon, UserIcon } from '../../assets/icons';

/**
 * Customer storefront header: announcement bar, brand, search, account menu,
 * wishlist + cart shortcuts with live item counts and responsive navigation.
 */
export default function Header() {
  const { user, logout } = useAuth();
  const { totalItems: cartTotalItems } = useCart();
  const { totalItems: wishlistTotalItems } = useWishlist();
  const navigate = useNavigate();
  const [searchTerm, setSearchTerm] = useState('');

  const handleSearch = (e) => {
    e.preventDefault();
    const term = searchTerm.trim();
    navigate(term ? `/shop?search=${encodeURIComponent(term)}` : '/shop');
  };

  const handleLogout = async () => {
    await logout();
    navigate('/');
  };

  return (
    <header className="customer-header">
      <div className="top-bar d-none d-md-block text-center py-2 small">
        <Container>
          Fresh &amp; organic produce, delivered with care.
        </Container>
      </div>

      <Navbar bg="white" expand="lg" sticky="top" className="border-bottom main-navbar">
        <Container>
          <Navbar.Brand as={Link} to="/" className="brand d-flex align-items-center gap-2">
            <LeafIcon size={32} className="brand-icon" />
            <span className="brand-text">
              Organic<span className="brand-text-accent">Store</span>
            </span>
          </Navbar.Brand>

          <Form onSubmit={handleSearch} role="search" className="header-search mx-auto d-none d-lg-block">
            <div className="position-relative">
              <SearchIcon size={18} className="header-search-icon" />
              <Form.Control
                type="search"
                placeholder="Search organic products..."
                value={searchTerm}
                onChange={(e) => setSearchTerm(e.target.value)}
                aria-label="Search products"
              />
            </div>
          </Form>

          <div className="d-flex align-items-center gap-2 order-lg-3">
            <Button
              as={Link}
              to="/wishlist"
              variant="outline-secondary"
              className="header-icon-btn"
              title="Wishlist"
              aria-label={wishlistTotalItems > 0 ? `Wishlist, ${wishlistTotalItems} item(s)` : 'Wishlist'}
            >
              <HeartIcon size={20} />
              {wishlistTotalItems > 0 && <span className="header-badge">{wishlistTotalItems}</span>}
            </Button>

            <Button
              as={Link}
              to="/cart"
              variant="outline-secondary"
              className="header-icon-btn"
              title="Shopping cart"
              aria-label={cartTotalItems > 0 ? `Cart, ${cartTotalItems} item(s)` : 'Shopping cart'}
            >
              <CartIcon size={20} />
              {cartTotalItems > 0 && <span className="header-badge">{cartTotalItems}</span>}
            </Button>

            {user ? (
              <NavDropdown
                title={
                  <span className="d-inline-flex align-items-center gap-2 header-user">
                    <UserIcon size={18} />
                    <span className="d-none d-sm-inline">{user.name}</span>
                  </span>
                }
                id="header-user-menu"
                align="end"
              >
                <NavDropdown.Item as={Link} to="/profile">My profile</NavDropdown.Item>
                <NavDropdown.Item as={Link} to="/orders">My orders</NavDropdown.Item>
                <NavDropdown.Item as={Link} to="/profile/addresses">Addresses</NavDropdown.Item>
                <NavDropdown.Item as={Link} to="/wishlist">Wishlist</NavDropdown.Item>
                <NavDropdown.Divider />
                <NavDropdown.Item onClick={handleLogout}>Sign out</NavDropdown.Item>
              </NavDropdown>
            ) : (
              <div className="d-none d-md-flex gap-2">
                <Button as={Link} to="/login" variant="outline-success" size="sm">
                  Sign In
                </Button>
                <Button as={Link} to="/register" variant="success" size="sm">
                  Register
                </Button>
              </div>
            )}

            <Navbar.Toggle aria-controls="customer-nav" className="ms-1" />
          </div>

          <Navbar.Collapse id="customer-nav" className="order-lg-2">
            <Form onSubmit={handleSearch} role="search" className="header-search-mobile d-lg-none my-3">
              <div className="position-relative">
                <SearchIcon size={18} className="header-search-icon" />
                <Form.Control
                  type="search"
                  placeholder="Search organic products..."
                  value={searchTerm}
                  onChange={(e) => setSearchTerm(e.target.value)}
                  aria-label="Search products"
                />
              </div>
            </Form>

            <Nav className="me-auto customer-nav" navbarScroll>
              <Nav.Link as={NavLink} to="/" end>Home</Nav.Link>
              <Nav.Link as={NavLink} to="/shop">Shop</Nav.Link>
              <Nav.Link as={NavLink} to="/categories">Categories</Nav.Link>
              <Nav.Link as={NavLink} to="/about">About</Nav.Link>
              <Nav.Link as={NavLink} to="/contact">Contact</Nav.Link>
              {user && user.role === 'admin' && (
                <Nav.Link as={Link} to="/admin/products" className="text-success fw-semibold">
                  Admin
                </Nav.Link>
              )}
            </Nav>

            {!user && (
              <div className="d-md-none d-flex flex-column gap-2 pb-3">
                <Button as={Link} to="/login" variant="outline-success">
                  Sign In
                </Button>
                <Button as={Link} to="/register" variant="success">
                  Register
                </Button>
              </div>
            )}
          </Navbar.Collapse>
        </Container>
      </Navbar>
    </header>
  );
}