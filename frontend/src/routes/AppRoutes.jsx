import { Routes, Route, Navigate } from 'react-router-dom';
import CustomerLayout from '../layouts/CustomerLayout';
import AdminLayout from '../layouts/AdminLayout';
import ProtectedRoute from './ProtectedRoute';
import HomePage from '../pages/home/HomePage';
import ProductListPage from '../pages/products/ProductListPage';
import ProductDetailsPage from '../pages/products/ProductDetailsPage';
import CategoriesPage from '../pages/categories/CategoriesPage';
import CategoryProductsPage from '../pages/categories/CategoryProductsPage';
import ProfilePage from '../pages/profile/ProfilePage';
import AboutPage from '../pages/AboutPage';
import ContactPage from '../pages/ContactPage';
import NotFoundPage from '../pages/NotFoundPage';
import Login from '../pages/auth/Login';
import Register from '../pages/auth/Register';
import CartPage from '../pages/cart/CartPage';
import WishlistPage from '../pages/wishlist/WishlistPage';
import AddressesPage from '../pages/addresses/AddressesPage';
import CheckoutPage from '../pages/checkout/CheckoutPage';
import CheckoutSuccessPage from '../pages/checkout/CheckoutSuccessPage';
import OrdersPage from '../pages/orders/OrdersPage';
import OrderDetailPage from '../pages/orders/OrderDetailPage';
import PaymentPage from '../pages/payment/PaymentPage';
import PaymentSuccessPage from '../pages/payment/PaymentSuccessPage';
import PaymentFailedPage from '../pages/payment/PaymentFailedPage';
import PaymentExpiredPage from '../pages/payment/PaymentExpiredPage';
import AdminProducts from '../pages/admin/AdminProducts';
import AdminProductForm from '../pages/admin/AdminProductForm';
import AdminCategories from '../pages/admin/AdminCategories';
import AdminDashboard from '../pages/admin/AdminDashboard';
import AdminOrders from '../pages/admin/AdminOrders';
import AdminOrderDetail from '../pages/admin/AdminOrderDetail';
import AdminInventory from '../pages/admin/AdminInventory';
import AdminInventoryDetail from '../pages/admin/AdminInventoryDetail';
import AdminBanners from '../pages/admin/AdminBanners';
import AdminSettings from '../pages/admin/AdminSettings';

export default function AppRoutes() {
  return (
    <Routes>
      <Route element={<CustomerLayout />}>
        <Route path="/" element={<HomePage />} />
        <Route path="/shop" element={<ProductListPage />} />
        <Route path="/products/:slug" element={<ProductDetailsPage />} />
        <Route path="/categories" element={<CategoriesPage />} />
        <Route path="/categories/:slug" element={<CategoryProductsPage />} />
        <Route path="/about" element={<AboutPage />} />
        <Route path="/contact" element={<ContactPage />} />
        <Route path="/login" element={<Login />} />
        <Route path="/register" element={<Register />} />
        <Route
          path="/profile"
          element={
            <ProtectedRoute>
              <ProfilePage />
            </ProtectedRoute>
          }
        />
        <Route
          path="/cart"
          element={
            <ProtectedRoute>
              <CartPage />
            </ProtectedRoute>
          }
        />
        <Route
          path="/wishlist"
          element={
            <ProtectedRoute>
              <WishlistPage />
            </ProtectedRoute>
          }
        />
        <Route
          path="/profile/addresses"
          element={
            <ProtectedRoute>
              <AddressesPage />
            </ProtectedRoute>
          }
        />
        <Route
          path="/orders"
          element={
            <ProtectedRoute>
              <OrdersPage />
            </ProtectedRoute>
          }
        />
        <Route
          path="/orders/:orderNumber"
          element={
            <ProtectedRoute>
              <OrderDetailPage />
            </ProtectedRoute>
          }
        />
        <Route
          path="/checkout"
          element={
            <ProtectedRoute>
              <CheckoutPage />
            </ProtectedRoute>
          }
        />
        <Route
          path="/checkout/success"
          element={
            <ProtectedRoute>
              <CheckoutSuccessPage />
            </ProtectedRoute>
          }
        />
        <Route
          path="/payment/:orderNumber"
          element={
            <ProtectedRoute>
              <PaymentPage />
            </ProtectedRoute>
          }
        />
        <Route
          path="/payment/success/:orderNumber"
          element={
            <ProtectedRoute>
              <PaymentSuccessPage />
            </ProtectedRoute>
          }
        />
        <Route
          path="/payment/failed/:orderNumber"
          element={
            <ProtectedRoute>
              <PaymentFailedPage />
            </ProtectedRoute>
          }
        />
        <Route
          path="/payment/expired/:orderNumber"
          element={
            <ProtectedRoute>
              <PaymentExpiredPage />
            </ProtectedRoute>
          }
        />
      </Route>

      <Route
        path="/admin"
        element={
          <ProtectedRoute roles={['admin']}>
            <AdminLayout />
          </ProtectedRoute>
        }
      >
        <Route index element={<AdminDashboard />} />
        <Route path="orders" element={<AdminOrders />} />
        <Route path="orders/:id" element={<AdminOrderDetail />} />
        <Route path="products" element={<AdminProducts />} />
        <Route path="products/new" element={<AdminProductForm />} />
        <Route path="products/:id/edit" element={<AdminProductForm />} />
        <Route path="inventory" element={<AdminInventory />} />
        <Route path="inventory/:id" element={<AdminInventoryDetail />} />
        <Route path="categories" element={<AdminCategories />} />
        <Route path="banners" element={<AdminBanners />} />
        <Route path="settings" element={<AdminSettings />} />
      </Route>

      <Route path="*" element={<NotFoundPage />} />
    </Routes>
  );
}